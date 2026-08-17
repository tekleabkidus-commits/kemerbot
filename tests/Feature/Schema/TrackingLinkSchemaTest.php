<?php

declare(strict_types=1);

use App\Models\TrackingLink;
use Illuminate\Database\QueryException;

it('tracks clicks, joins and conversion', function () {
    $link = TrackingLink::factory()->create(['clicks_count' => 200, 'joins_count' => 50]);

    expect($link->conversionRate())->toBe(25.0);
});

it('returns null conversion with zero clicks', function () {
    $link = TrackingLink::factory()->create();

    expect($link->conversionRate())->toBeNull();
});

it('rejects duplicate codes', function () {
    TrackingLink::factory()->create(['code' => 'promo1']);

    expect(fn () => TrackingLink::factory()->create(['code' => 'promo1']))
        ->toThrow(QueryException::class);
});
