<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;

it('stores telegram chat ids as big integers', function () {
    $user = User::factory()->create(['tg_chat_id' => 8_876_543_210]);

    expect($user->refresh()->tg_chat_id)->toBe(8_876_543_210);
});

it('casts activity and channel fields', function () {
    $user = User::factory()->blocked()->inChannel()->create();

    $user->refresh();

    expect($user->blocked_bot)->toBeTrue()
        ->and($user->blocked_at)->not->toBeNull()
        ->and($user->in_channel)->toBeTrue()
        ->and($user->channel_checked_at)->not->toBeNull()
        ->and($user->joined_at)->not->toBeNull();
});

it('supports referral relationships', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by_user_id' => $referrer->id]);

    expect($referred->referredBy->is($referrer))->toBeTrue()
        ->and($referrer->referrals()->count())->toBe(1);
});

it('nulls referred_by_user_id when the referrer is deleted', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by_user_id' => $referrer->id]);

    $referrer->delete();

    expect($referred->refresh()->referred_by_user_id)->toBeNull();
});

it('rejects duplicate tg_chat_id', function () {
    User::factory()->create(['tg_chat_id' => 12345]);

    expect(fn () => User::factory()->create(['tg_chat_id' => 12345]))
        ->toThrow(QueryException::class);
});

it('rejects a referral pointing at a missing user', function () {
    expect(fn () => User::factory()->create(['referred_by_user_id' => 999_999]))
        ->toThrow(QueryException::class);
});
