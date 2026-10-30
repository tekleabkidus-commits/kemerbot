<?php

declare(strict_types=1);

use App\Enums\KeywordMatchType;
use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;
use Database\Seeders\SettingsSeeder;

beforeEach(fn () => $this->seed(SettingsSeeder::class));

function anyMessageRule(string $text, bool $active = true): KeywordReply
{
    $rule = KeywordReply::factory()->create([
        'match_type' => KeywordMatchType::Any,
        'keywords' => [],
        'is_active' => $active,
    ]);
    KeywordReplyTranslation::factory()->for($rule)->create(['reply_text' => $text]);

    return $rule;
}

it('replies to any unmatched text with one configured response', function () {
    anyMessageRule('Thanks! We received your message.');

    postWebhook(telegramMessageUpdate(8101, 'Hello there'));
    postWebhook(telegramMessageUpdate(8101, 'How does this work?'));

    $texts = collect(fakeTelegram()->sentTo(8101))->pluck('params.text')->all();
    expect($texts)->toBe([
        'Thanks! We received your message.',
        'Thanks! We received your message.',
    ]);
});

it('uses specific keyword rules before the catch-all reply', function () {
    anyMessageRule('General support response');
    $rule = KeywordReply::factory()->create(['keywords' => ['help'], 'match_type' => KeywordMatchType::Exact]);
    KeywordReplyTranslation::factory()->for($rule)->create(['reply_text' => 'Specific help response']);

    postWebhook(telegramMessageUpdate(8102, 'help'));

    expect(fakeTelegram()->sentTo(8102))->toHaveCount(1)
        ->and(fakeTelegram()->lastSentTo(8102)['params']['text'])->toBe('Specific help response');
});

it('replies to a photo without a caption', function () {
    anyMessageRule('Thanks for contacting support.');
    $update = telegramMessageUpdate(8103, 'placeholder');
    unset($update['message']['text']);
    $update['message']['photo'] = [['file_id' => 'photo-id', 'width' => 100, 'height' => 100]];

    postWebhook($update);

    expect(fakeTelegram()->lastSentTo(8103)['params']['text'])->toBe('Thanks for contacting support.');
});

it('ignores disabled catch-all rules', function () {
    anyMessageRule('Should not send', false);
    postWebhook(telegramMessageUpdate(8104, 'anything'));
    expect(fakeTelegram()->nothingSent())->toBeTrue();
});

it('keeps /start welcome and /stop preference responses instead of sending a catch-all', function () {
    anyMessageRule('Generic response');

    postWebhook(telegramMessageUpdate(8105, '/start'));
    postWebhook(telegramMessageUpdate(8105, '/stop'));

    $texts = collect(fakeTelegram()->sentTo(8105))->pluck('params.text')->all();
    expect($texts)->toHaveCount(2)
        ->and($texts[0])->toContain('Welcome')
        ->and($texts[1])->toContain('paused promotional messages');
});
