<?php

declare(strict_types=1);

use App\Enums\MenuActionType;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SettingsSeeder;

// Regression: MenuActionType::Invite was missing from the badge-color match,
// 500ing /admin/menu-items the moment an invite item existed (prod incident).
it('renders menu-items with the full seeded menu including the invite item', function () {
    $this->seed(DatabaseSeeder::class); // MenuSeeder ships an invite item

    $this->actingAs(Admin::query()->first())
        ->get('/admin/menu-items')
        ->assertOk();
});

it('covers every menu action type in the index badge', function () {
    foreach (MenuActionType::cases() as $case) {
        $item = MenuItem::factory()->create(['action_type' => $case->value]);
        MenuItemTranslation::factory()->for($item)->create(['label' => 'Item '.$case->value]);
    }

    $this->actingAs(Admin::factory()->owner()->create())
        ->get('/admin/menu-items')
        ->assertOk();
});

it('ignores stale menu callbacks pointing at an invite item without crashing', function () {
    $this->seed(SettingsSeeder::class);
    $item = MenuItem::factory()->create(['action_type' => 'invite']);
    MenuItemTranslation::factory()->for($item)->create();

    postWebhook(telegramCallbackUpdate(14001, "menu:{$item->id}"));

    expect(fakeTelegram()->callsTo('answerCallbackQuery'))->toHaveCount(1)
        ->and(fakeTelegram()->nothingSent())->toBeTrue();
});
