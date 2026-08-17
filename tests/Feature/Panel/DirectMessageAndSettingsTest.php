<?php

declare(strict_types=1);

use App\Enums\MessageDirection;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Jobs\SendDirectMessageJob;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Bot\BotMessageSender;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

it('delivers a direct message and records it in the conversation', function () {
    $admin = Admin::factory()->marketer()->create();
    $user = User::factory()->create();

    (new SendDirectMessageJob($user->id, $admin->id, 'Hi from KemerBet support!'))
        ->handle(app(BotMessageSender::class));

    $message = TelegramMessage::query()->first();

    expect(fakeTelegram()->lastSentTo($user->tg_chat_id)['params']['text'])->toBe('Hi from KemerBet support!')
        ->and($message->direction)->toBe(MessageDirection::AdminOutbound)
        ->and($message->admin_id)->toBe($admin->id)
        ->and($message->tg_message_id)->not->toBeNull();
});

it('records nothing when the user has blocked the bot', function () {
    $admin = Admin::factory()->marketer()->create();
    $user = User::factory()->blocked()->create();

    (new SendDirectMessageJob($user->id, $admin->id, 'hello?'))
        ->handle(app(BotMessageSender::class));

    expect(fakeTelegram()->nothingSent())->toBeTrue()
        ->and(TelegramMessage::query()->count())->toBe(0);
});

it('audits the dm action and dispatches on the interactive queue', function () {
    $admin = Admin::factory()->owner()->create();
    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(ViewUser::class, ['record' => $user->id])
        ->callAction('send_message', ['text' => 'Welcome back!']);

    expect(AuditLog::query()->where('action', 'direct_message.sent')->exists())->toBeTrue()
        ->and(TelegramMessage::query()->where('direction', MessageDirection::AdminOutbound)->count())->toBe(1);
});

it('audits settings changes with old and new values', function () {
    $owner = Admin::factory()->owner()->create();

    Livewire::actingAs($owner)
        ->test(Settings::class)
        ->fillForm(['default_language' => 'am'])
        ->call('save');

    $entry = AuditLog::query()->where('action', 'settings.updated')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->meta['changed']['bot.default_language'])
        ->toEqual(['from' => 'en', 'to' => 'am']);
});

it('ignores infrastructure keys when a marketer submits them', function () {
    $marketer = Admin::factory()->marketer()->create();

    Livewire::actingAs($marketer)
        ->test(Settings::class)
        ->fillForm(['welcome_en' => 'New welcome!', 'send_rate' => 1])
        ->call('save');

    $settings = app(SettingsService::class);

    expect($settings->get('welcome.message')['en'])->toBe('New welcome!')
        // Disabled + policy-filtered: the send rate never changes.
        ->and($settings->get('telegram.send_rate'))->toBe(25);
});
