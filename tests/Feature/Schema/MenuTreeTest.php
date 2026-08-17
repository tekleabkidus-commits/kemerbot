<?php

declare(strict_types=1);

use App\Enums\MenuActionType;
use App\Models\MediaFile;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;

it('builds a hierarchical tree ordered by position', function () {
    $root = MenuItem::factory()->submenu()->create();
    $second = MenuItem::factory()->for($root, 'parent')->create(['position' => 1]);
    $first = MenuItem::factory()->for($root, 'parent')->create(['position' => 0]);

    expect($root->children->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->parent->is($root))->toBeTrue();
});

it('cascades children and translations when a parent is deleted', function () {
    $root = MenuItem::factory()->submenu()->create();
    $child = MenuItem::factory()->for($root, 'parent')->create();
    MenuItemTranslation::factory()->for($child)->create();

    $root->delete();

    expect(MenuItem::query()->count())->toBe(0)
        ->and(MenuItemTranslation::query()->count())->toBe(0);
});

it('supports all four action types', function () {
    $reply = MenuItem::factory()->create();
    $submenu = MenuItem::factory()->submenu()->create();
    $url = MenuItem::factory()->url('https://sunbet.et/promo')->create();
    $webapp = MenuItem::factory()->webapp()->create();

    expect($reply->action_type)->toBe(MenuActionType::Reply)
        ->and($submenu->action_type)->toBe(MenuActionType::Submenu)
        ->and($url->action_type)->toBe(MenuActionType::Url)
        ->and($url->url)->toBe('https://sunbet.et/promo')
        ->and($webapp->action_type)->toBe(MenuActionType::Webapp);
});

it('keeps the menu item but nulls media_file_id when media is deleted', function () {
    $media = MediaFile::factory()->create();
    $item = MenuItem::factory()->create(['media_file_id' => $media->id]);

    $media->delete();

    expect($item->refresh()->media_file_id)->toBeNull();
});
