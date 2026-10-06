<?php

namespace App\Console\Commands;

use App\Jobs\PrepareBroadcastJob;
use App\Jobs\ProcessTelegramUpdate;
use App\Jobs\SendDirectMessageJob;
use App\Models\AutomationStepDelivery;
use App\Models\Broadcast;
use App\Services\Automations\AutomationStateAdvancer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverWorkspace extends Command
{
    protected $signature = 'workspace:recover';

    protected $description = 'Recover durable inbound updates and interrupted campaign/journey work';

    public function handle(): int
    {
        DB::table('telegram_updates')->where('status', 'processing')->where('claimed_at', '<', now()->subMinutes(5))->update(['status' => 'pending']);
        DB::table('telegram_updates')->where('status', 'pending')->where('attempts', '>=', 10)->update(['status' => 'failed', 'updated_at' => now()]);
        DB::table('telegram_updates')->where('status', 'pending')->orderBy('id')->limit(1000)->get()->each(function ($row) {
            DB::table('telegram_updates')->where('id', $row->id)->update(['updated_at' => now()]);
            ProcessTelegramUpdate::dispatch(json_decode($row->payload, true))->onQueue(config('telegram.queues.interactive'));
        });
        DB::table('direct_message_deliveries')->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(5))->update(['status' => 'pending', 'available_at' => now()]);
        DB::table('direct_message_deliveries')->where('status', 'pending')->where('attempts', '>=', 3)->update(['status' => 'failed', 'last_error' => 'Recovery retry budget exhausted', 'updated_at' => now()]);
        DB::table('direct_message_deliveries')->where('status', 'pending')->where('available_at', '<=', now())->orderBy('created_at')->limit(1000)->get()->each(fn ($row) => SendDirectMessageJob::dispatch($row->user_id, $row->admin_id ?? 0, $row->text, $row->id)->onQueue(config('telegram.queues.interactive')));
        Broadcast::query()->where('status', 'preparing')->where('updated_at', '<', now()->subSeconds(780))->limit(100)->get()->each(fn ($b) => PrepareBroadcastJob::dispatch($b->id)->onQueue(config('telegram.queues.broadcast')));
        AutomationStepDelivery::query()->whereIn('status', ['sent', 'failed'])->whereHas('state', fn ($q) => $q->where('status', 'active')->whereNull('next_step_at'))
            ->with('state.automation.steps', 'step')->limit(1000)->get()->each(function ($d) {
                if ($d->step && $d->state->current_step_no === $d->step->step_no) {
                    if ($d->status->value === 'failed' && preg_match('/blocked|messaging preference|exit condition|no longer active/', (string) $d->last_error)) {
                        DB::transaction(fn () => app(AutomationStateAdvancer::class)->cancel($d->state));
                    } else {
                        DB::transaction(fn () => app(AutomationStateAdvancer::class)->advancePast($d->state, $d->step->step_no));
                    }
                }
            });
        DB::table('direct_message_deliveries')->whereIn('status', ['sent', 'failed'])->where('updated_at', '<', now()->subDays((int) config('telegram.message_retention_days')))->delete();
        DB::table('contact_send_slots')->where('reserved_at', '<', now()->subDays(7))->delete();
        DB::table('telegram_updates')->where('status', 'processed')->where('processed_at', '<', now()->subDays(30))->delete();
        $this->info('Workspace recovery complete');

        return self::SUCCESS;
    }
}
