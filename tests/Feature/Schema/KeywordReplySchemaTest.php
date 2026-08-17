<?php

declare(strict_types=1);

use App\Enums\KeywordMatchType;
use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;

it('stores keywords as a jsonb array including amharic text', function () {
    $reply = KeywordReply::factory()->contains()->create([
        'keywords' => ['bonus', 'ቦነስ'],
    ]);

    $reply->refresh();

    expect($reply->keywords)->toBe(['bonus', 'ቦነስ'])
        ->and($reply->match_type)->toBe(KeywordMatchType::Contains);
});

it('stores optional buttons with per-language labels', function () {
    $reply = KeywordReply::factory()->create([
        'buttons' => [[
            'kind' => 'url',
            'url' => 'https://kemerbet.co',
            'label' => ['en' => 'Open KemerBet', 'am' => 'KemerBet ይክፈቱ'],
        ]],
    ]);

    expect($reply->refresh()->buttons[0]['label']['am'])->toBe('KemerBet ይክፈቱ');
});

it('cascades translations when a keyword reply is deleted', function () {
    $reply = KeywordReply::factory()->create();
    KeywordReplyTranslation::factory()->for($reply)->create();
    KeywordReplyTranslation::factory()->for($reply)->amharic()->create();

    $reply->delete();

    expect(KeywordReplyTranslation::query()->count())->toBe(0);
});
