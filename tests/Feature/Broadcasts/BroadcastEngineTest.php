<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Enums\FailureCategory;
use App\Jobs\PrepareBroadcastJob;
use App\Jobs\SendBroadcastChunkJob;
use App\Models\Broadcast;
use App\Models\BroadcastFailure;
use App\Models\BroadcastTranslation;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Broadcasts\AudienceSnapshot;
use App\Services\Broadcasts\BroadcastChunkSender;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\InvalidBroadcastTransition;
use App\Services\Telegram\TelegramResponse;
use Illuminate\Support\Facades\Queue;

function makeBroadcast(array $attrs = []): Broadcast
{
    $broadcast = Broadcast::factory()->create($attrs);
    BroadcastTranslation::factory()->for($broadcast)->create(['text' => 'Hello {first_name}!']);

    return $broadcast;
}

function runChunksUntilDone(Broadcast $broadcast, int $safety = 50): void
{
    // Drive the chunk chain by hand (Queue::fake intercepts self-dispatch).
    while ($safety-- > 0 && $broadcast->refresh()->status === BroadcastStatus::Sending) {
        app()->make(SendBroadcastChunkJob::class, ['broadcastId' => $broadcast->id])->handle(
            app(AudienceSnapshot::class),
            app(BroadcastChunkSender::class),
            app(BroadcastLifecycle::class),
        );
    }
}

it('sends a full broadcast end to end with correct counters', function () {
    User::factory()->count(7)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->queued)->toBe(7)
        ->and($broadcast->audience_snapshot_count)->toBe(7)
        ->and($broadcast->sent)->toBe(7)
        ->and($broadcast->blocked)->toBe(0)
        ->and($broadcast->failed)->toBe(0)
        ->and($broadcast->started_at)->not->toBeNull()
        ->and($broadcast->finished_at)->not->toBeNull()
        ->and(app(AudienceSnapshot::class)->remaining($broadcast->id))->toBe(0);
});

it('processes in chunks of the configured size', function () {
    config()->set('telegram.broadcast_chunk_size', 3);
    User::factory()->count(7)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);

    // 7 users at chunk size 3 → 3 chunk batches, one counter UPDATE each.
    expect($broadcast->refresh()->sent)->toBe(7);
});

it('personalizes per user', function () {
    User::factory()->create(['first_name' => 'Sara']);
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);

    expect(fakeTelegram()->callsTo('sendMessage')[0]['params']['text'])->toBe('Hello Sara!');
});

it('marks 403 users blocked and counts them separately', function () {
    $victim = User::factory()->create();
    User::factory()->count(2)->create();
    fakeTelegram()->blockNextSend($victim->tg_chat_id);

    $broadcast = makeBroadcast();
    app(BroadcastLifecycle::class)->start($broadcast);

    $broadcast->refresh();
    $failure = BroadcastFailure::query()->where('user_id', $victim->id)->first();

    expect($broadcast->sent)->toBe(2)
        ->and($broadcast->blocked)->toBe(1)
        ->and($broadcast->failed)->toBe(0)
        ->and($victim->refresh()->blocked_bot)->toBeTrue()
        ->and($failure->category)->toBe(FailureCategory::Blocked)
        ->and($broadcast->sent + $broadcast->blocked + $broadcast->failed)->toBeLessThanOrEqual($broadcast->queued);
});

it('skips users who blocked the bot before the snapshot', function () {
    User::factory()->count(2)->create();
    User::factory()->blocked()->create();

    $broadcast = makeBroadcast();
    app(BroadcastLifecycle::class)->start($broadcast);

    // Blocked users never enter the snapshot at all.
    expect($broadcast->refresh()->queued)->toBe(2)
        ->and($broadcast->sent)->toBe(2);
});

it('retries transient network failures by re-queueing, then succeeds', function () {
    config()->set('telegram.send_max_attempts', 3);
    $user = User::factory()->create();
    fakeTelegram()->queueResponse($user->tg_chat_id, TelegramResponse::networkFailure('timeout'), times: 2);

    $broadcast = makeBroadcast();
    app(BroadcastLifecycle::class)->start($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->sent)->toBe(1)
        ->and($broadcast->failed)->toBe(0)
        ->and(BroadcastFailure::query()->count())->toBe(0);
});

it('records a permanent failure after exhausting retry attempts', function () {
    config()->set('telegram.send_max_attempts', 2);
    $user = User::factory()->create();
    fakeTelegram()->queueResponse($user->tg_chat_id, TelegramResponse::networkFailure('down'), times: 10);

    $broadcast = makeBroadcast();
    app(BroadcastLifecycle::class)->start($broadcast);

    $broadcast->refresh();
    $failure = BroadcastFailure::query()->where('user_id', $user->id)->first();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->sent)->toBe(0)
        ->and($broadcast->failed)->toBe(1)
        ->and($failure->category)->toBe(FailureCategory::Network)
        ->and($failure->attempts)->toBeGreaterThanOrEqual(2);
});

it('records invalid-request failures without retrying', function () {
    $user = User::factory()->create();
    fakeTelegram()->queueResponse($user->tg_chat_id, TelegramResponse::failure(400, 'Bad Request: chat not found'));

    $broadcast = makeBroadcast();
    app(BroadcastLifecycle::class)->start($broadcast);

    expect($broadcast->refresh()->failed)->toBe(1)
        ->and(BroadcastFailure::query()->first()->category)->toBe(FailureCategory::Invalid);
});

it('honors 429 retry_after and still delivers', function () {
    $user = User::factory()->create();
    fakeTelegram()->queueResponse(
        $user->tg_chat_id,
        TelegramResponse::failure(429, 'Too Many Requests', retryAfter: 1),
    );

    $broadcast = makeBroadcast();
    app(BroadcastLifecycle::class)->start($broadcast);

    expect($broadcast->refresh()->sent)->toBe(1)
        ->and(fakeTelegram()->sentTo($user->tg_chat_id))->toHaveCount(2);
});

it('freezes the audience at prepare time', function () {
    Queue::fake();
    User::factory()->count(2)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    // A user joining AFTER the snapshot must not receive this broadcast.
    User::factory()->create();

    runChunksUntilDone($broadcast);

    expect($broadcast->refresh()->queued)->toBe(2)
        ->and($broadcast->sent)->toBe(2);
});

it('resumes safely after a simulated worker death', function () {
    Queue::fake();
    config()->set('telegram.broadcast_chunk_size', 2);
    User::factory()->count(5)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    // One chunk runs, then the worker "dies" (no further dispatches executed).
    (new SendBroadcastChunkJob($broadcast->id))->handle(
        app(AudienceSnapshot::class),
        app(BroadcastChunkSender::class),
        app(BroadcastLifecycle::class),
    );

    expect($broadcast->refresh()->sent)->toBe(2)
        ->and(app(AudienceSnapshot::class)->remaining($broadcast->id))->toBe(3);

    // A fresh worker picks up: remaining Redis ids are simply consumed.
    runChunksUntilDone($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->sent)->toBe(5)
        ->and($broadcast->sent + $broadcast->blocked + $broadcast->failed)->toBeLessThanOrEqual($broadcast->queued);
});

it('stops queueing on cancellation and reports sent-before-cancel', function () {
    Queue::fake();
    config()->set('telegram.broadcast_chunk_size', 2);
    User::factory()->count(6)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    (new SendBroadcastChunkJob($broadcast->id))->handle(
        app(AudienceSnapshot::class),
        app(BroadcastChunkSender::class),
        app(BroadcastLifecycle::class),
    );

    app(BroadcastLifecycle::class)->cancel($broadcast->refresh());

    // The next (already-queued) chunk job notices the flag and does nothing.
    (new SendBroadcastChunkJob($broadcast->id))->handle(
        app(AudienceSnapshot::class),
        app(BroadcastChunkSender::class),
        app(BroadcastLifecycle::class),
    );

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Cancelled)
        ->and($broadcast->sent)->toBe(2)
        ->and($broadcast->cancelled_at)->not->toBeNull()
        ->and(app(AudienceSnapshot::class)->remaining($broadcast->id))->toBe(0);
});

it('prevents double sends with an atomic claim', function () {
    Queue::fake();
    User::factory()->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);

    expect(fn () => app(BroadcastLifecycle::class)->start($broadcast))
        ->toThrow(InvalidBroadcastTransition::class);
});

it('completes immediately on an empty audience', function () {
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->queued)->toBe(0)
        ->and($broadcast->sent)->toBe(0);
});

it('persists the media file_id on first send and reuses it for the rest', function () {
    User::factory()->count(2)->create();
    $media = MediaFile::factory()->create();
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create([
        'text' => 'With media',
        'media_file_id' => $media->id,
    ]);

    app(BroadcastLifecycle::class)->start($broadcast);

    $photoCalls = fakeTelegram()->callsTo('sendPhoto');

    expect($media->refresh()->tg_file_id)->not->toBeNull()
        ->and($photoCalls)->toHaveCount(2)
        // Second send reuses the file_id instead of re-uploading (spec §4.13).
        ->and($photoCalls[1]['params']['photo'])->toBe($media->tg_file_id);
});

it('cleans up redis keys on terminal states', function () {
    User::factory()->count(3)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);

    expect(app(AudienceSnapshot::class)->remaining($broadcast->id))->toBe(0);
});

it('never loses recipients claimed by a crashed worker — the HARDENING §3 scenario', function () {
    Queue::fake();
    config()->set('telegram.broadcast_chunk_size', 3);
    config()->set('telegram.broadcast_claim_timeout_seconds', 0); // stale immediately
    User::factory()->count(6)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    $snapshot = app(AudienceSnapshot::class);

    // "worker claims 1,2,3 … worker crashes before sending them":
    // a dead consumer reads three entries and never acknowledges anything.
    $claimed = $snapshot->claimBatch($broadcast->id, 'dead-worker', 3);
    expect($claimed)->toHaveCount(3);

    // A fresh worker takes over: the pending entries are reclaimed, then the
    // unread remainder — users 1–3 are NOT permanently skipped.
    runChunksUntilDone($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->sent)->toBe(6)
        ->and($broadcast->sent + $broadcast->blocked + $broadcast->failed)->toBe($broadcast->queued);
});

it('cannot appear complete while another worker still holds claimed recipients', function () {
    Queue::fake();
    config()->set('telegram.broadcast_claim_timeout_seconds', 300); // claims are fresh
    User::factory()->count(2)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    // A live consumer holds both entries, unacknowledged.
    app(AudienceSnapshot::class)->claimBatch($broadcast->id, 'busy-worker', 2);

    // Another chunk job finds nothing claimable — and must NOT complete.
    (new SendBroadcastChunkJob($broadcast->id))->handle(
        app(AudienceSnapshot::class),
        app(BroadcastChunkSender::class),
        app(BroadcastLifecycle::class),
    );

    expect($broadcast->refresh()->status)->toBe(BroadcastStatus::Sending);
});

it('acknowledges reclaimed duplicates without re-sending or double-counting', function () {
    Queue::fake();
    config()->set('telegram.broadcast_claim_timeout_seconds', 0);
    $user = User::factory()->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    $snapshot = app(AudienceSnapshot::class);

    // Crash window: the message reached Telegram and bookkeeping recorded it,
    // but the worker died before XACK — the entry stays pending.
    $snapshot->claimBatch($broadcast->id, 'dead-worker', 1);
    $snapshot->markDone($broadcast->id, $user->id);
    Broadcast::query()->whereKey($broadcast->id)->update(['sent' => 1]);

    runChunksUntilDone($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->sent)->toBe(1) // not double-counted
        ->and(fakeTelegram()->sentTo($user->tg_chat_id))->toHaveCount(0); // not re-sent
});

it('hands two concurrent workers disjoint recipient batches', function () {
    Queue::fake();
    config()->set('telegram.broadcast_claim_timeout_seconds', 300);
    User::factory()->count(6)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    $snapshot = app(AudienceSnapshot::class);
    $batchA = $snapshot->claimBatch($broadcast->id, 'worker-a', 3);
    $batchB = $snapshot->claimBatch($broadcast->id, 'worker-b', 3);

    $idsA = array_column($batchA, 'user_id');
    $idsB = array_column($batchB, 'user_id');

    expect($batchA)->toHaveCount(3)
        ->and($batchB)->toHaveCount(3)
        ->and(array_intersect($idsA, $idsB))->toBe([]);
});

it('revives a stalled sending broadcast via the recovery command', function () {
    Queue::fake();
    config()->set('telegram.broadcast_claim_timeout_seconds', 0);
    User::factory()->count(2)->create();
    $broadcast = makeBroadcast();

    app(BroadcastLifecycle::class)->start($broadcast);
    (new PrepareBroadcastJob($broadcast->id))->handle(app(BroadcastLifecycle::class));

    // The whole chain died: no chunk job will ever run again on its own.
    Broadcast::query()->whereKey($broadcast->id)
        ->update(['updated_at' => now()->subMinutes(10)]);

    $this->artisan('broadcasts:recover-stalled')->assertSuccessful();
    Queue::assertPushed(SendBroadcastChunkJob::class);

    // Driving the revived chain finishes the campaign.
    runChunksUntilDone($broadcast);

    expect($broadcast->refresh()->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->sent)->toBe(2);
});
