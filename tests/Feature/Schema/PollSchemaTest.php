<?php

declare(strict_types=1);

use App\Models\Poll;
use App\Models\PollInstance;
use Illuminate\Database\QueryException;

it('stores question and options as language maps', function () {
    $poll = Poll::factory()->create();

    $poll->refresh();

    expect($poll->question)->toHaveKeys(['en', 'am'])
        ->and($poll->options['en'])->toHaveCount(3)
        ->and($poll->options['am'])->toHaveCount(3)
        ->and($poll->is_anonymous)->toBeTrue();
});

it('correlates sent telegram polls back to the parent poll', function () {
    $poll = Poll::factory()->create();
    $instance = PollInstance::factory()->for($poll)->create(['tg_poll_id' => '5217643301']);

    expect($instance->poll->is($poll))->toBeTrue()
        ->and($poll->instances()->count())->toBe(1);
});

it('cascades instances when a poll is deleted', function () {
    $poll = Poll::factory()->create();
    PollInstance::factory()->for($poll)->count(2)->create();

    $poll->delete();

    expect(PollInstance::query()->count())->toBe(0);
});

it('rejects duplicate telegram poll ids', function () {
    PollInstance::factory()->create(['tg_poll_id' => 'dup-poll-id']);

    expect(fn () => PollInstance::factory()->create(['tg_poll_id' => 'dup-poll-id']))
        ->toThrow(QueryException::class);
});

it('allows only one poll definition per broadcast', function () {
    $poll = Poll::factory()->create();

    expect(fn () => Poll::factory()->create(['broadcast_id' => $poll->broadcast_id]))
        ->toThrow(QueryException::class);
});
