<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Jobs\WorkerPingJob;
use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Production diagnostic for environments without log access (run from the
 * Laravel Cloud Commands console). Plain-text output, never secrets: every
 * printed string passes through a redactor that strips the bot token,
 * webhook secret, and DB/Redis passwords.
 */
class DiagnoseKemerbot extends Command
{
    protected $signature = 'kemerbot:diagnose';

    protected $description = 'Print render, audience, queue, webhook and media diagnostics (secret-free)';

    public function handle(): int
    {
        $this->line('KemerBot diagnose — '.now()->toIso8601String().' (UTC)');
        $this->line(str_repeat('=', 60));

        $this->section('1) MenuItems admin page render', fn () => $this->renderCheck());
        $this->section('2) Audience', fn () => $this->audience());
        $this->section('3) Queues & workers', fn () => $this->queues());
        $this->section('4) Webhook health', fn () => $this->webhook());
        $this->section('5) Media', fn () => $this->media());

        return self::SUCCESS;
    }

    private function section(string $title, callable $body): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', 60));

        try {
            $body();
        } catch (Throwable $e) {
            $this->line('SECTION FAILED: '.get_class($e).': '.$this->redact($e->getMessage()));
        }
    }

    /** Server-side render of the Filament MenuItems index as an owner admin. */
    private function renderCheck(): void
    {
        $admin = Admin::query()->where('role', AdminRole::Owner)->first()
            ?? Admin::query()->first();

        if ($admin === null) {
            $this->line('No admin account exists — cannot render.');

            return;
        }

        $captured = null;

        // Unhandled request exceptions are reported to the logger with an
        // `exception` context entry — capture it without needing log access.
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            if (($event->context['exception'] ?? null) instanceof Throwable && $captured === null) {
                $captured = $event->context['exception'];
            }
        });

        Auth::guard('web')->login($admin);

        try {
            $request = Request::create(url('/admin/menu-items'), 'GET');
            $request->headers->set('Accept', 'text/html');

            $response = app(HttpKernel::class)->handle($request);

            $this->line("Rendered as: admin #{$admin->id} ({$admin->role->value})");
            $this->line('HTTP status: '.$response->getStatusCode());
        } finally {
            Auth::guard('web')->logout();
        }

        if ($captured === null) {
            $this->line($response->getStatusCode() < 500
                ? 'No exception raised during render.'
                : 'Status >= 500 but no exception captured — check the response body path.');

            return;
        }

        $this->line('Exception: '.get_class($captured));
        $this->line('Message:   '.$this->redact($captured->getMessage()));
        $this->line('Location:  '.$this->shortPath($captured->getFile()).':'.$captured->getLine());
        $this->line('Top frames:');

        foreach (array_slice($captured->getTrace(), 0, 10) as $i => $frame) {
            $where = isset($frame['file'])
                ? $this->shortPath($frame['file']).':'.($frame['line'] ?? '?')
                : '[internal]';
            $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
            $this->line(sprintf('  #%-2d %s  %s', $i, $where, $this->redact($call)));
        }
    }

    private function audience(): void
    {
        $this->line('Total users: '.number_format(User::query()->count()));

        $newest = User::query()->latest('joined_at')->first();

        $this->line($newest === null
            ? 'Newest user: none'
            : 'Newest user joined_at: '.$newest->joined_at->toIso8601String()
                .' ('.$newest->joined_at->diffForHumans().')');
    }

    private function queues(): void
    {
        $lanes = array_values(config('telegram.queues')) + [3 => 'default'];

        foreach ($lanes as $lane) {
            $pending = (int) Redis::llen('queues:'.$lane);
            $delayed = (int) Redis::zcard('queues:'.$lane.':delayed');
            $reserved = (int) Redis::zcard('queues:'.$lane.':reserved');
            $this->line(sprintf('Lane %-22s pending=%d delayed=%d reserved=%d', $lane.':', $pending, $delayed, $reserved));
        }

        $failedCount = (int) DB::table('failed_jobs')->count();
        $lastFailed = DB::table('failed_jobs')->max('failed_at');
        $this->line("Failed jobs: {$failedCount}".($lastFailed !== null ? " (latest: {$lastFailed})" : ''));

        // Live probe: a no-op job per lane; a running worker consumes it
        // within the wait window and stamps a Redis key.
        foreach ($lanes as $lane) {
            Redis::del('kemerbot:diag:ping:'.$lane);
            WorkerPingJob::dispatch($lane)->onQueue($lane);
        }

        $deadline = microtime(true) + 6;
        $seen = [];

        while (microtime(true) < $deadline && count($seen) < count($lanes)) {
            foreach ($lanes as $lane) {
                if (! isset($seen[$lane]) && Redis::get('kemerbot:diag:ping:'.$lane)) {
                    $seen[$lane] = true;
                }
            }

            usleep(200_000);
        }

        foreach ($lanes as $lane) {
            $this->line(sprintf(
                'Worker probe %-15s %s',
                $lane.':',
                isset($seen[$lane]) ? 'CONSUMED (worker alive)' : 'NOT consumed within 6s — no worker on this lane?',
            ));
        }
    }

    private function webhook(): void
    {
        $lastOk = Redis::get('telegram:last_webhook_ok_at');

        $this->line($lastOk
            ? 'Last update received: '.$lastOk.' ('.Carbon::parse($lastOk)->diffForHumans().')'
            : 'Last update received: never (no webhook traffic recorded)');

        $lastError = Redis::get('telegram:last_api_error');

        if ($lastError) {
            $decoded = (array) json_decode((string) $lastError, true);
            $this->line('Last Telegram API error: '.$this->redact(
                ($decoded['code'] ?? 'network').' on '.($decoded['method'] ?? '?')
                .' — '.($decoded['description'] ?? '').' at '.($decoded['at'] ?? '?'),
            ));
        } else {
            $this->line('Last Telegram API error: none recorded');
        }

        if (blank(config('telegram.bot_token'))) {
            $this->line('getWebhookInfo: skipped (TELEGRAM_BOT_TOKEN not set)');

            return;
        }

        $info = app(TelegramClient::class)->getWebhookInfo();

        if (! $info->successful()) {
            $this->line('getWebhookInfo failed: '.$this->redact((string) $info->description));

            return;
        }

        $result = is_array($info->result) ? $info->result : [];

        foreach (['url', 'pending_update_count', 'last_error_date', 'last_error_message'] as $key) {
            if (array_key_exists($key, $result)) {
                $value = $key === 'last_error_date'
                    ? Carbon::createFromTimestampUTC((int) $result[$key])->toIso8601String()
                    : (string) $result[$key];
                $this->line("getWebhookInfo {$key}: ".$this->redact($value));
            }
        }
    }

    private function media(): void
    {
        $this->line('media_files rows: '.MediaFile::query()->count());
        $this->line('with cached telegram file_id: '.MediaFile::query()->whereNotNull('tg_file_id')->count());

        $disk = (string) config('filesystems.default');
        $driver = (string) config("filesystems.disks.{$disk}.driver");

        $this->line("Media resolves to disk '{$disk}' (driver: {$driver}) via Storage default disk.");

        if ($driver === 's3') {
            $this->line('S3 bucket configured: '.(blank(config("filesystems.disks.{$disk}.bucket")) ? 'NO' : 'yes'));
            $this->line('S3 key/secret present: '.(blank(config("filesystems.disks.{$disk}.key")) ? 'NO' : 'yes'));
        }

        if ($driver === 'local') {
            $this->line('NOTE: local disk is per-instance storage — uploads made on a web'
                .' instance are invisible to workers on other instances (HARDENING item 13, pending P1).');
        }
    }

    private function redact(string $text): string
    {
        $secrets = array_filter([
            (string) config('telegram.bot_token'),
            (string) config('telegram.webhook_secret'),
            (string) config('database.connections.pgsql.password'),
            (string) config('database.redis.default.password'),
        ], fn (string $v): bool => $v !== '');

        return str_replace($secrets, '[REDACTED]', $text);
    }

    private function shortPath(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
