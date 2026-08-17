<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\BroadcastTranslation;
use App\Models\KeywordReply;
use App\Models\KeywordReplyTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\User;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->owner = Admin::factory()->owner()->create();
});

it('renders every panel page for an owner', function (string $path) {
    // Seed representative data so tables render real rows.
    User::factory()->count(2)->create();
    $item = MenuItem::factory()->create();
    MenuItemTranslation::factory()->for($item)->create();
    $reply = KeywordReply::factory()->create();
    KeywordReplyTranslation::factory()->for($reply)->create();
    $broadcast = Broadcast::factory()->create();
    BroadcastTranslation::factory()->for($broadcast)->create();
    AuditLog::factory()->create();

    $this->actingAs($this->owner)->get($path)->assertOk();
})->with([
    '/admin',
    '/admin/broadcasts',
    '/admin/broadcasts/create',
    '/admin/menu-items',
    '/admin/menu-items/create',
    '/admin/keyword-replies',
    '/admin/keyword-replies/create',
    '/admin/users',
    '/admin/admins',
    '/admin/audit-logs',
    '/admin/settings',
    '/admin/automations',
    '/admin/automations/create',
    '/admin/tracking-links',
    '/admin/tracking-links/create',
    '/admin/polls',
]);

it('lets a viewer browse but hides admin-only pages', function () {
    $viewer = Admin::factory()->viewer()->create();

    $this->actingAs($viewer)->get('/admin/broadcasts')->assertOk();
    $this->actingAs($viewer)->get('/admin/users')->assertOk();
    $this->actingAs($viewer)->get('/admin/admins')->assertForbidden();
    $this->actingAs($viewer)->get('/admin/audit-logs')->assertForbidden();
});

it('blocks marketers from admin management and audit logs', function () {
    $marketer = Admin::factory()->marketer()->create();

    $this->actingAs($marketer)->get('/admin/admins')->assertForbidden();
    $this->actingAs($marketer)->get('/admin/audit-logs')->assertForbidden();
    $this->actingAs($marketer)->get('/admin/broadcasts/create')->assertOk();
});

it('blocks viewers from create pages', function () {
    $viewer = Admin::factory()->viewer()->create();

    $this->actingAs($viewer)->get('/admin/broadcasts/create')->assertForbidden();
    $this->actingAs($viewer)->get('/admin/menu-items/create')->assertForbidden();
    $this->actingAs($viewer)->get('/admin/keyword-replies/create')->assertForbidden();
});
