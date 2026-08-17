<?php

declare(strict_types=1);

use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
});

function keywordRule(array $keywords, string $matchType, string $reply, array $attrs = []): KeywordReply
{
    $rule = KeywordReply::factory()->create(array_merge([
        'keywords' => $keywords,
        'match_type' => $matchType,
    ], $attrs));
    KeywordReplyTranslation::factory()->for($rule)->create(['reply_text' => $reply]);

    return $rule;
}

it('replies on exact match, case- and whitespace-insensitive', function () {
    keywordRule(['bonus'], 'exact', 'Here is your bonus info!');

    postWebhook(telegramMessageUpdate(7001, '  BONUS '));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toBe('Here is your bonus info!');
});

it('replies on contains match inside a sentence', function () {
    keywordRule(['promo'], 'contains', 'Latest promos: sunbet.et/promo');

    postWebhook(telegramMessageUpdate(7001, 'any promo running today?'));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toContain('Latest promos');
});

it('prefers exact matches over contains matches', function () {
    keywordRule(['bonus'], 'contains', 'contains reply', ['position' => 0]);
    keywordRule(['bonus'], 'exact', 'exact reply', ['position' => 5]);

    postWebhook(telegramMessageUpdate(7001, 'bonus'));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toBe('exact reply');
});

it('resolves overlapping contains rules by longest keyword', function () {
    keywordRule(['bet'], 'contains', 'short reply', ['position' => 0]);
    keywordRule(['betting tips'], 'contains', 'long reply', ['position' => 5]);

    postWebhook(telegramMessageUpdate(7001, 'send betting tips please'));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toBe('long reply');
});

it('breaks ties by lowest position', function () {
    keywordRule(['odds'], 'contains', 'second', ['position' => 2]);
    keywordRule(['odds'], 'contains', 'first', ['position' => 1]);

    postWebhook(telegramMessageUpdate(7001, 'what are the odds'));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toBe('first');
});

it('matches amharic keywords with unicode normalization', function () {
    keywordRule(['ቦነስ'], 'exact', 'የቦነስ መረጃ እነሆ!');

    postWebhook(telegramMessageUpdate(7001, 'ቦነስ', ['language_code' => 'am']));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toBe('የቦነስ መረጃ እነሆ!');
});

it('replies in the user language with english fallback', function () {
    $rule = keywordRule(['help'], 'exact', 'How can we help?');
    KeywordReplyTranslation::factory()->for($rule)->amharic()->create(['reply_text' => 'እንዴት እንርዳዎት?']);

    postWebhook(telegramMessageUpdate(7001, 'help', ['language_code' => 'am']));
    postWebhook(telegramMessageUpdate(7002, 'help', ['language_code' => 'en']));

    expect(fakeTelegram()->lastSentTo(7001)['params']['text'])->toBe('እንዴት እንርዳዎት?')
        ->and(fakeTelegram()->lastSentTo(7002)['params']['text'])->toBe('How can we help?');
});

it('stays silent when nothing matches', function () {
    keywordRule(['bonus'], 'exact', 'Bonus!');

    postWebhook(telegramMessageUpdate(7001, 'completely unrelated'));

    expect(fakeTelegram()->nothingSent())->toBeTrue();
});

it('ignores inactive rules', function () {
    keywordRule(['bonus'], 'exact', 'Bonus!', ['is_active' => false]);

    postWebhook(telegramMessageUpdate(7001, 'bonus'));

    expect(fakeTelegram()->nothingSent())->toBeTrue();
});

it('attaches url buttons from the rule definition', function () {
    keywordRule(['app'], 'exact', 'Get the app:', [
        'buttons' => [[
            'kind' => 'url',
            'url' => 'https://sunbet.et/app',
            'label' => ['en' => 'Open app', 'am' => 'መተግበሪያ ክፈት'],
        ]],
    ]);

    postWebhook(telegramMessageUpdate(7001, 'app'));

    $keyboard = fakeTelegram()->lastSentTo(7001)['params']['reply_markup'];

    expect($keyboard['inline_keyboard'][0][0])->toMatchArray([
        'text' => 'Open app',
        'url' => 'https://sunbet.et/app',
    ]);
});
