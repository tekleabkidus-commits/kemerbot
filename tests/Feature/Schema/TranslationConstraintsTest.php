<?php

declare(strict_types=1);

use App\Enums\BotLanguage;
use App\Models\AutomationStep;
use App\Models\AutomationStepTranslation;
use App\Models\Broadcast;
use App\Models\BroadcastButton;
use App\Models\BroadcastButtonTranslation;
use App\Models\BroadcastTranslation;
use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Illuminate\Database\QueryException;

it('allows one translation per language per menu item', function () {
    $item = MenuItem::factory()->create();
    MenuItemTranslation::factory()->for($item)->create();
    MenuItemTranslation::factory()->for($item)->amharic()->create();

    expect($item->translations()->count())->toBe(2);
});

it('rejects duplicate menu item translations for the same language', function () {
    $item = MenuItem::factory()->create();
    MenuItemTranslation::factory()->for($item)->create();

    expect(fn () => MenuItemTranslation::factory()->for($item)->create())
        ->toThrow(QueryException::class);
});

it('rejects duplicate keyword reply translations for the same language', function () {
    $reply = KeywordReply::factory()->create();
    KeywordReplyTranslation::factory()->for($reply)->create();

    expect(fn () => KeywordReplyTranslation::factory()->for($reply)->create())
        ->toThrow(QueryException::class);
});

it('rejects duplicate broadcast translations for the same language', function () {
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create();

    expect(fn () => BroadcastTranslation::factory()->for($broadcast)->create())
        ->toThrow(QueryException::class);
});

it('rejects duplicate broadcast button translations for the same language', function () {
    $button = BroadcastButton::factory()->create();
    BroadcastButtonTranslation::factory()->for($button, 'button')->create(['broadcast_button_id' => $button->id]);

    expect(fn () => BroadcastButtonTranslation::factory()->create(['broadcast_button_id' => $button->id]))
        ->toThrow(QueryException::class);
});

it('rejects duplicate automation step translations for the same language', function () {
    $step = AutomationStep::factory()->create();
    AutomationStepTranslation::factory()->create(['automation_step_id' => $step->id]);

    expect(fn () => AutomationStepTranslation::factory()->create(['automation_step_id' => $step->id]))
        ->toThrow(QueryException::class);
});

it('rejects languages outside en/am via check constraint', function () {
    $item = MenuItem::factory()->create();

    // Raw insert to bypass the enum cast and hit the DB constraint itself.
    expect(fn () => DB::table('menu_item_translations')->insert([
        'menu_item_id' => $item->id,
        'lang' => 'fr',
        'label' => 'Menu',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('casts lang to the BotLanguage enum', function () {
    $translation = MenuItemTranslation::factory()->amharic()->create();

    expect($translation->refresh()->lang)->toBe(BotLanguage::Am);
});
