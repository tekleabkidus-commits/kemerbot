<?php

declare(strict_types=1);

use App\Models\Setting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\QueryException;

it('round-trips scalar and array jsonb values', function () {
    Setting::query()->create(['key' => 'telegram.send_rate', 'value' => 25]);
    Setting::query()->create(['key' => 'welcome.message', 'value' => ['en' => 'Hi', 'am' => 'ሰላም']]);

    // toEqual, not toBe: Postgres jsonb does not preserve object key order.
    expect(Setting::query()->where('key', 'telegram.send_rate')->first()->value)->toBe(25)
        ->and(Setting::query()->where('key', 'welcome.message')->first()->value)
        ->toEqual(['en' => 'Hi', 'am' => 'ሰላም']);
});

it('seeds default settings including a bilingual welcome message', function () {
    $this->seed(SettingsSeeder::class);

    $welcome = Setting::query()->where('key', 'welcome.message')->first();

    expect($welcome->value)->toHaveKeys(['en', 'am'])
        ->and(Setting::query()->where('key', 'bot.default_language')->first()->value)->toBe('en')
        ->and(Setting::query()->where('key', 'telegram.send_rate')->first()->value)->toBe(25);
});

it('never overwrites admin-edited values on re-seed', function () {
    $this->seed(SettingsSeeder::class);
    Setting::query()->where('key', 'bot.default_language')->first()->update(['value' => 'am']);

    $this->seed(SettingsSeeder::class);

    expect(Setting::query()->where('key', 'bot.default_language')->first()->value)->toBe('am');
});

it('never contains telegram secrets', function () {
    $this->seed(SettingsSeeder::class);

    $dump = Setting::query()->get()->toJson();

    expect($dump)->not->toContain('token')
        ->and($dump)->not->toContain('secret');
});

it('rejects duplicate keys', function () {
    Setting::query()->create(['key' => 'dup', 'value' => 1]);

    expect(fn () => Setting::query()->create(['key' => 'dup', 'value' => 2]))
        ->toThrow(QueryException::class);
});
