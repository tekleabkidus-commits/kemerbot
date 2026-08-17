<?php

declare(strict_types=1);

use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastTranslation;
use App\Models\User;
use App\Services\Broadcasts\BroadcastRenderer;

$render = fn (Broadcast $broadcast, User $user) => app(BroadcastRenderer::class)->render($broadcast->load('translations', 'buttons.translations'), $user);

it('renders the user language with english fallback', function () use ($render) {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create(['text' => 'English text']);
    BroadcastTranslation::factory()->for($broadcast)->amharic()->create(['text' => 'የአማርኛ ጽሑፍ']);

    $amUser = User::factory()->amharic()->create();
    $enUser = User::factory()->create();

    expect($render($broadcast, $amUser)->text)->toBe('የአማርኛ ጽሑፍ')
        ->and($render($broadcast, $enUser)->text)->toBe('English text');
});

it('falls back to english when the amharic translation is missing', function () use ($render) {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create(['text' => 'Only English']);

    $amUser = User::factory()->amharic()->create();

    expect($render($broadcast, $amUser)->text)->toBe('Only English');
});

it('personalizes tokens and blanks unknown ones', function () use ($render) {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create(['text' => 'Hi {first_name}{mystery}!']);

    $user = User::factory()->create(['first_name' => 'Abel']);

    expect($render($broadcast, $user)->text)->toBe('Hi Abel!');
});

it('builds the inline keyboard with rows, url and tracked callback buttons', function () use ($render) {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create();

    $url = BroadcastButton::factory()->for($broadcast)->create(['row' => 0, 'position' => 0]);
    BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $url->id, 'label' => 'Open site']);

    $callback = BroadcastButton::factory()->for($broadcast)->callback()->create(['row' => 1, 'position' => 0]);
    BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $callback->id, 'label' => 'Claim bonus']);

    $keyboard = $render($broadcast, User::factory()->create())->replyMarkup['inline_keyboard'];

    expect($keyboard[0][0])->toMatchArray(['text' => 'Open site', 'url' => 'https://kemerbet.co'])
        ->and($keyboard[1][0])->toMatchArray([
            'text' => 'Claim bonus',
            'callback_data' => "bc:{$broadcast->id}:{$callback->id}",
        ]);
});

it('renders button labels in the user language', function () use ($render) {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create();
    $button = BroadcastButton::factory()->for($broadcast)->create();
    BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $button->id, 'label' => 'Bet now']);
    BroadcastButtonTranslation::factory()->amharic()->create(['broadcast_button_id' => $button->id]);

    $keyboard = $render($broadcast, User::factory()->amharic()->create())->replyMarkup['inline_keyboard'];

    expect($keyboard[0][0]['text'])->toBe('አሁን ይወራረዱ');
});

it('formats the match promo caption with kickoff in Addis time', function () {
    $broadcast = Broadcast::factory()->matchCard()->create([
        'template_fields' => [
            'home_team' => 'St. George',
            'away_team' => 'Fasil Kenema',
            // 18:00 UTC = 21:00 Addis (UTC+3).
            'kickoff_at' => '2026-08-22T18:00:00Z',
            'odds' => ['home' => '2.10', 'draw' => '3.20', 'away' => '3.50'],
            'cta' => 'Bet now on kemerbet.co',
        ],
    ]);
    BroadcastTranslation::factory()->for($broadcast)->create(['text' => 'Weekend derby!']);

    $message = app(BroadcastRenderer::class)
        ->render($broadcast->load('translations', 'buttons.translations'), User::factory()->create());

    expect($message->text)->toContain('St. George vs Fasil Kenema')
        ->and($message->text)->toContain('21:00')
        ->and($message->text)->toContain('2.10')
        ->and($message->text)->toContain('3.20')
        ->and($message->text)->toContain('3.50')
        ->and($message->text)->toContain('Weekend derby!')
        ->and($message->text)->toContain('👉 Bet now on kemerbet.co');
});
