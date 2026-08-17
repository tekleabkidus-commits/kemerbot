<?php

declare(strict_types=1);

use App\Telegram\StartPayloadParser;
use App\Telegram\StartPayloadType;

$parser = fn () => new StartPayloadParser;

it('parses empty payloads as none', function () use ($parser) {
    expect($parser()->parse(null)->type)->toBe(StartPayloadType::None)
        ->and($parser()->parse('')->type)->toBe(StartPayloadType::None)
        ->and($parser()->parse('   ')->type)->toBe(StartPayloadType::None);
});

it('parses referral payloads', function () use ($parser) {
    $payload = $parser()->parse('ref_42');

    expect($payload->type)->toBe(StartPayloadType::Referral)
        ->and($payload->referrerUserId)->toBe(42);
});

it('treats malformed ref payloads as invalid, never as source codes', function (string $raw) use ($parser) {
    expect($parser()->parse($raw)->type)->toBe(StartPayloadType::Invalid);
})->with(['ref_', 'ref_abc', 'ref_12a', 'ref', 'ref_-5', 'ref_9999999999999999999']);

it('parses safe-charset codes as sources', function (string $raw) use ($parser) {
    $payload = $parser()->parse($raw);

    expect($payload->type)->toBe(StartPayloadType::Source)
        ->and($payload->source)->toBe($raw);
})->with(['summer24', 'tiktok_bio', 'FB-Promo-1', 'a']);

it('rejects unsafe or overlong payloads as invalid', function (string $raw) use ($parser) {
    expect($parser()->parse($raw)->type)->toBe(StartPayloadType::Invalid);
})->with([
    'has space',
    'semi;colon',
    'sql\'inject',
    '<script>',
    'ወደ',
    str_repeat('a', 65),
]);
