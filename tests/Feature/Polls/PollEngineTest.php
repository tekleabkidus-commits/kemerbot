<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollInstance;
use App\Models\User;
use App\Services\Bot\PollService;
use App\Services\Broadcasts\BroadcastLifecycle;
use App\Services\Broadcasts\BroadcastTestSender;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('sends native polls and correlates every instance', function () {
    User::factory()->count(2)->create();
    User::factory()->amharic()->create();
    $poll = Poll::factory()->create();

    app(BroadcastLifecycle::class)->start($poll->broadcast);

    $broadcast = $poll->broadcast->refresh();
    $pollCalls = fakeTelegram()->callsTo('sendPoll');

    expect($broadcast->sent)->toBe(3)
        ->and($pollCalls)->toHaveCount(3)
        ->and(PollInstance::query()->count())->toBe(3)
        ->and(PollInstance::query()->pluck('tg_poll_id')->unique())->toHaveCount(3);

    // Amharic users get the Amharic question and options.
    $amCall = collect($pollCalls)->first(fn (array $c) => str_contains($c['params']['question'], 'ማን'));
    expect($amCall)->not->toBeNull()
        ->and($amCall['params']['options'][0])->toBe('ቅዱስ ጊዮርጊስ');
});

it('aggregates poll updates per instance and sums the totals', function () {
    $poll = Poll::factory()->create();
    PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'inst-1']);
    PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'inst-2']);

    postWebhook([
        'update_id' => 700001,
        'poll' => [
            'id' => 'inst-1',
            'options' => [['text' => 'A', 'voter_count' => 3], ['text' => 'B', 'voter_count' => 1]],
        ],
    ]);
    postWebhook([
        'update_id' => 700002,
        'poll' => [
            'id' => 'inst-2',
            'options' => [['text' => 'A', 'voter_count' => 2], ['text' => 'B', 'voter_count' => 4]],
        ],
    ]);

    expect($poll->refresh()->answer_counts)->toEqual(['0' => 5, '1' => 5]);
});

it('handles re-votes idempotently: latest instance counts replace, never stack', function () {
    $poll = Poll::factory()->create();
    PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'inst-1']);

    postWebhook([
        'update_id' => 700003,
        'poll' => ['id' => 'inst-1', 'options' => [['text' => 'A', 'voter_count' => 1], ['text' => 'B', 'voter_count' => 0]]],
    ]);
    // The voter retracts and votes B instead — Telegram resends authoritative counts.
    postWebhook([
        'update_id' => 700004,
        'poll' => ['id' => 'inst-1', 'options' => [['text' => 'A', 'voter_count' => 0], ['text' => 'B', 'voter_count' => 1]]],
    ]);

    expect($poll->refresh()->answer_counts)->toEqual(['0' => 0, '1' => 1]);
});

it('ignores updates for unknown poll instances', function () {
    postWebhook([
        'update_id' => 700005,
        'poll' => ['id' => 'ghost', 'options' => [['text' => 'A', 'voter_count' => 9]]],
    ]);

    expect(Poll::query()->count())->toBe(0);
});

it('treats poll answers as user activity with blocked recovery', function () {
    $poll = Poll::factory()->create();
    $user = User::factory()->blocked()->create(['last_active_at' => now()->subDays(10)]);
    PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'inst-9', 'user_id' => $user->id]);

    postWebhook([
        'update_id' => 700006,
        'poll_answer' => [
            'poll_id' => 'inst-9',
            'user' => ['id' => $user->tg_chat_id, 'first_name' => $user->first_name],
            'option_ids' => [0],
        ],
    ]);

    $user->refresh();

    expect($user->last_active_at->isToday())->toBeTrue()
        ->and($user->blocked_bot)->toBeFalse();
});

it('test-sends polls without creating instances', function () {
    app(SettingsService::class)->set('broadcast.test_recipient_chat_ids', [111]);
    $poll = Poll::factory()->create();

    $result = app(BroadcastTestSender::class)->send($poll->broadcast->load('poll'));

    expect($result['sent'])->toBe(1)
        ->and(fakeTelegram()->callsTo('sendPoll')[0]['params']['question'])->toStartWith('[TEST]')
        ->and(PollInstance::query()->count())->toBe(0);
});

it('processes an update touching only its own instance state — O(options), not O(recipients)', function () {
    $poll = Poll::factory()->create();
    $service = app(PollService::class);

    // 300 sibling instances, each with its own previous-counts key.
    $instances = PollInstance::factory()->for($poll)->count(300)->create();

    foreach ($instances as $i => $instance) {
        $service->seedPreviousCounts($instance->tg_poll_id, [1, 0]);
    }
    $poll->update(['answer_counts' => ['0' => 300, '1' => 0]]);

    // One voter on ONE instance changes their vote.
    $target = $instances->first();
    postWebhook([
        'update_id' => 710001,
        'poll' => ['id' => $target->tg_poll_id, 'options' => [
            ['text' => 'A', 'voter_count' => 0], ['text' => 'B', 'voter_count' => 1],
        ]],
    ]);

    // Totals moved by exactly the delta — computed WITHOUT reading the other
    // 299 instances (their prev keys are untouched).
    expect($poll->refresh()->answer_counts)->toEqual(['0' => 299, '1' => 1])
        ->and($instances[5]->refresh()->previous_counts)->toBe([1, 0])
        ->and($target->refresh()->previous_counts)->toBe([0, 1]);
});

it('keeps previous poll counts after Redis eviction', function () {
    $poll = Poll::factory()->create();
    $instance = PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'durable-check']);
    $service = app(PollService::class);
    $service->ingestPollUpdate(['id' => 'durable-check', 'options' => [['voter_count' => 1]]], 710002);
    Redis::connection()->flushdb();
    $service->ingestPollUpdate(['id' => 'durable-check', 'options' => [['voter_count' => 1]]], 710003);
    expect($instance->refresh()->previous_counts)->toBe([1])->and($poll->refresh()->answer_counts)->toEqual([1]);
});

it('migrates legacy aggregation state so historical votes are never double-counted', function () {
    $poll = Poll::factory()->create(['answer_counts' => ['0' => 3, '1' => 1]]);
    PollInstance::factory()->for($poll)->create(['tg_poll_id' => 'legacy-1']);
    Redis::hset("poll:{$poll->id}:counts", 'legacy-1', json_encode([3, 1]));

    $this->artisan('polls:migrate-aggregation-state')->assertSuccessful();

    // Legacy hash gone; the SAME counts arriving again produce zero delta.
    postWebhook([
        'update_id' => 710003,
        'poll' => ['id' => 'legacy-1', 'options' => [
            ['text' => 'A', 'voter_count' => 3], ['text' => 'B', 'voter_count' => 1],
        ]],
    ]);

    expect((array) Redis::hgetall("poll:{$poll->id}:counts"))->toBe([])
        ->and($poll->refresh()->answer_counts)->toEqual(['0' => 3, '1' => 1]);

    // And a real new vote still lands as a delta.
    postWebhook([
        'update_id' => 710004,
        'poll' => ['id' => 'legacy-1', 'options' => [
            ['text' => 'A', 'voter_count' => 4], ['text' => 'B', 'voter_count' => 1],
        ]],
    ]);

    expect($poll->refresh()->answer_counts)->toEqual(['0' => 4, '1' => 1]);
});
