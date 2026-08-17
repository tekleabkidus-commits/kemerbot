<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Broadcasts\AudienceSnapshot;
use Illuminate\Support\Facades\Redis;

it('prunes telegram messages older than the retention window', function () {
    $user = User::factory()->create();
    TelegramMessage::factory()->for($user)->create(['sent_at' => now()->subDays(100)]);
    $recent = TelegramMessage::factory()->for($user)->create(['sent_at' => now()->subDays(10)]);

    $this->artisan('model:prune', ['--model' => [TelegramMessage::class]])->assertSuccessful();

    expect(TelegramMessage::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('sets a TTL backstop on snapshot keys at build time', function () {
    User::factory()->count(2)->create();
    $broadcast = Broadcast::factory()->create();

    app(AudienceSnapshot::class)->build($broadcast);

    $ttl = (int) Redis::ttl("broadcast:{$broadcast->id}:audience");

    expect($ttl)->toBeGreaterThan(0)
        ->and($ttl)->toBeLessThanOrEqual(48 * 3600);
});

it('sweeps snapshots for terminal and missing broadcasts, keeps active ones', function () {
    User::factory()->count(2)->create();

    $sending = Broadcast::factory()->create(['status' => 'sending']);
    $cancelled = Broadcast::factory()->create(['status' => 'cancelled']);

    $snapshot = app(AudienceSnapshot::class);
    $snapshot->build($sending);
    $snapshot->build($cancelled);
    Redis::rpush('broadcast:999999:audience', '1:1'); // broadcast row is gone

    $this->artisan('broadcasts:sweep-orphans')->assertSuccessful();

    expect($snapshot->remaining($sending->id))->toBe(2)
        ->and($snapshot->remaining($cancelled->id))->toBe(0)
        ->and((int) Redis::llen('broadcast:999999:audience'))->toBe(0);
});
