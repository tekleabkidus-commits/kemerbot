<?php

declare(strict_types=1);

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use App\Filament\Resources\Broadcasts\Pages\CreateBroadcast;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->actingAs(Admin::factory()->marketer()->create());
});

it('creates and immediately sends a broadcast through the wizard', function () {
    User::factory()->count(3)->create();
    User::factory()->amharic()->count(2)->create();

    Livewire::test(CreateBroadcast::class)
        ->fillForm([
            'type' => 'standard',
            'text_en' => 'Big weekend odds, {first_name}!',
            'text_am' => 'ታላቅ የሳምንቱ መጨረሻ ዕድሎች {first_name}!',
            'buttons_data' => [[
                'kind' => 'url',
                'url' => 'https://kemerbet.co',
                'label' => ['en' => 'Bet now', 'am' => 'አሁን ይወራረዱ'],
                'row' => 0,
            ]],
            'timing_mode' => 'now',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $broadcast = Broadcast::query()->first();

    expect($broadcast)->not->toBeNull()
        ->and($broadcast->type)->toBe(BroadcastType::Standard)
        ->and($broadcast->status)->toBe(BroadcastStatus::Completed)
        ->and($broadcast->queued)->toBe(5)
        ->and($broadcast->sent)->toBe(5)
        ->and($broadcast->translations()->count())->toBe(2)
        ->and($broadcast->buttons()->count())->toBe(1)
        ->and($broadcast->buttons()->first()->translations()->count())->toBe(2);

    // Amharic users got the Amharic rendering.
    $amText = collect(fakeTelegram()->callsTo('sendMessage'))
        ->pluck('params.text')
        ->filter(fn (string $t) => str_contains($t, 'ታላቅ'));

    expect($amText)->toHaveCount(2);
});

it('creates a scheduled broadcast without sending', function () {
    Livewire::test(CreateBroadcast::class)
        ->fillForm([
            'type' => 'standard',
            'text_en' => 'Scheduled blast',
            'timing_mode' => 'scheduled',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $broadcast = Broadcast::query()->first();

    expect($broadcast->status)->toBe(BroadcastStatus::Scheduled)
        ->and($broadcast->scheduled_at)->not->toBeNull()
        ->and($broadcast->sent)->toBe(0)
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});

it('creates a recurring broadcast armed at the next occurrence', function () {
    Livewire::test(CreateBroadcast::class)
        ->fillForm([
            'type' => 'standard',
            'text_en' => 'Every Saturday!',
            'timing_mode' => 'recurring',
            'rec_frequency' => 'weekly',
            'rec_day' => 'saturday',
            'rec_time' => '09:00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $broadcast = Broadcast::query()->first();

    expect($broadcast->status)->toBe(BroadcastStatus::Scheduled)
        ->and($broadcast->recurrence)->toEqual(['frequency' => 'weekly', 'day' => 'saturday', 'time' => '09:00'])
        ->and($broadcast->scheduled_at->isFuture())->toBeTrue();
});

it('requires english content', function () {
    Livewire::test(CreateBroadcast::class)
        ->fillForm([
            'type' => 'standard',
            'text_en' => null,
            'timing_mode' => 'now',
        ])
        ->call('create')
        ->assertHasFormErrors(['text_en' => 'required']);

    expect(Broadcast::query()->count())->toBe(0);
});
