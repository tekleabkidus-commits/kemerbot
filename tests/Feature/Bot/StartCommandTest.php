<?php

declare(strict_types=1);

use App\Events\UserJoined;
use App\Models\MediaFile;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('creates a new user and sends the welcome as the /start reply', function () {
    postWebhook(telegramMessageUpdate(2001, '/start', ['first_name' => 'Sara', 'username' => 'sara_bets']));

    $user = User::query()->where('tg_chat_id', 2001)->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Sara')
        ->and($user->username)->toBe('sara_bets')
        ->and($user->joined_at)->not->toBeNull()
        ->and($user->last_active_at)->not->toBeNull();

    $sent = fakeTelegram()->sentTo(2001);

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['params']['text'])->toContain('Sara')
        ->and($sent[0]['params']['text'])->toContain('SunBet');
});

it('fires UserJoined for new users only', function () {
    Event::fake([UserJoined::class]);

    postWebhook(telegramMessageUpdate(2001, '/start'));
    postWebhook(telegramMessageUpdate(2001, '/start'));

    Event::assertDispatchedTimes(UserJoined::class, 1);
});

it('is idempotent on repeated /start', function () {
    postWebhook(telegramMessageUpdate(2001, '/start'));
    postWebhook(telegramMessageUpdate(2001, '/start'));

    expect(User::query()->where('tg_chat_id', 2001)->count())->toBe(1)
        // Each /start still gets its welcome reply (spec §4.2).
        ->and(fakeTelegram()->sentTo(2001))->toHaveCount(2);
});

it('refreshes profile fields when telegram reports changes', function () {
    User::factory()->create(['tg_chat_id' => 2001, 'first_name' => 'Old', 'username' => null]);

    postWebhook(telegramMessageUpdate(2001, '/start', ['first_name' => 'New', 'username' => 'newname']));

    $user = User::query()->where('tg_chat_id', 2001)->first();

    expect($user->first_name)->toBe('New')
        ->and($user->username)->toBe('newname');
});

it('attaches the main menu keyboard to the welcome when menus exist', function () {
    $item = MenuItem::factory()->url('https://sunbet.et')->create();
    MenuItemTranslation::factory()->for($item)->create(['label' => 'Visit site']);

    postWebhook(telegramMessageUpdate(2001, '/start'));

    $keyboard = fakeTelegram()->lastSentTo(2001)['params']['reply_markup'];

    expect($keyboard['inline_keyboard'][0][0])->toMatchArray([
        'text' => 'Visit site',
        'url' => 'https://sunbet.et',
    ]);
});

it('sends welcome media and persists the telegram file_id for reuse', function () {
    $media = MediaFile::factory()->create();
    app(SettingsService::class)->set('welcome.media_file_id', $media->id);

    postWebhook(telegramMessageUpdate(2001, '/start'));

    $media->refresh();

    expect(fakeTelegram()->lastSentTo(2001)['method'])->toBe('sendPhoto')
        ->and($media->tg_file_id)->not->toBeNull();

    // Second send must reuse the stored file_id, not re-upload (spec §4.13).
    postWebhook(telegramMessageUpdate(2002, '/start'));

    expect(fakeTelegram()->lastSentTo(2002)['params']['photo'])->toBe($media->tg_file_id);
});
