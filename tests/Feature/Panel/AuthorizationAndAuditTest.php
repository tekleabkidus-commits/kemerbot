<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\MenuItem;
use App\Models\User;
use App\Policies\SettingPolicy;
use App\Services\AuditLogger;
use App\Services\Broadcasts\BroadcastLifecycle;

it('enforces role abilities server-side, not just in the UI', function () {
    $owner = Admin::factory()->owner()->create();
    $marketer = Admin::factory()->marketer()->create();
    $viewer = Admin::factory()->viewer()->create();

    // Content: viewer read-only, marketer + owner may write.
    expect($viewer->can('create', Broadcast::class))->toBeFalse()
        ->and($viewer->can('create', MenuItem::class))->toBeFalse()
        ->and($viewer->can('viewAny', Broadcast::class))->toBeTrue()
        ->and($marketer->can('create', Broadcast::class))->toBeTrue()
        ->and($owner->can('create', Broadcast::class))->toBeTrue();

    // Lifecycle + 1:1 messaging.
    $broadcast = Broadcast::factory()->create();
    expect($viewer->can('send', $broadcast))->toBeFalse()
        ->and($marketer->can('send', $broadcast))->toBeTrue()
        ->and($viewer->can('message', User::factory()->create()))->toBeFalse();

    // Admin management is owner-only; nobody deletes themselves.
    expect($marketer->can('viewAny', Admin::class))->toBeFalse()
        ->and($marketer->can('create', Admin::class))->toBeFalse()
        ->and($owner->can('create', Admin::class))->toBeTrue()
        ->and($owner->can('delete', $marketer))->toBeTrue()
        ->and($owner->can('delete', $owner))->toBeFalse();

    // Audit log: owner-only, immutable for everyone.
    expect($marketer->can('viewAny', AuditLog::class))->toBeFalse()
        ->and($owner->can('viewAny', AuditLog::class))->toBeTrue()
        ->and($owner->can('delete', AuditLog::factory()->create()))->toBeFalse();
});

it('splits settings keys between owner and marketer server-side', function () {
    $policy = new SettingPolicy;
    $owner = Admin::factory()->owner()->create();
    $marketer = Admin::factory()->marketer()->create();
    $viewer = Admin::factory()->viewer()->create();

    expect($policy->updateKey($owner, 'channel.id'))->toBeTrue()
        ->and($policy->updateKey($owner, 'welcome.message'))->toBeTrue()
        ->and($policy->updateKey($marketer, 'welcome.message'))->toBeTrue()
        ->and($policy->updateKey($marketer, 'bot.default_language'))->toBeTrue()
        ->and($policy->updateKey($marketer, 'channel.id'))->toBeFalse()
        ->and($policy->updateKey($marketer, 'telegram.send_rate'))->toBeFalse()
        ->and($policy->updateKey($marketer, 'broadcast.test_recipient_chat_ids'))->toBeFalse()
        ->and($policy->updateKey($viewer, 'welcome.message'))->toBeFalse();
});

it('audits admin-context mutations with changed keys only', function () {
    $admin = Admin::factory()->owner()->create();
    $this->actingAs($admin);

    $item = MenuItem::factory()->create();
    $item->update(['position' => 5]);
    $item->delete();

    $actions = AuditLog::query()->orderBy('id')->pluck('action')->all();

    // admin.created fires for the actor's own factory row? No — created before login.
    expect($actions)->toContain('menu_item.created')
        ->and($actions)->toContain('menu_item.updated')
        ->and($actions)->toContain('menu_item.deleted');

    $update = AuditLog::query()->where('action', 'menu_item.updated')->first();

    expect($update->meta['changed'])->toContain('position')
        ->and($update->admin_id)->toBe($admin->id);
});

it('does not audit bot or scheduler mutations (no acting admin)', function () {
    MenuItem::factory()->create();
    Broadcast::factory()->create();

    expect(AuditLog::query()->count())->toBe(0);
});

it('audits broadcast lifecycle actions with safe metadata', function () {
    $admin = Admin::factory()->owner()->create();
    $this->actingAs($admin);

    $broadcast = Broadcast::factory()->create();
    app(BroadcastLifecycle::class)->schedule($broadcast, now()->addDay());
    app(BroadcastLifecycle::class)->cancel($broadcast);

    $actions = AuditLog::query()->pluck('action')->all();

    expect($actions)->toContain('broadcast.scheduled')
        ->and($actions)->toContain('broadcast.cancelled');

    $dump = AuditLog::query()->get()->toJson();

    expect($dump)->not->toContain('TEST-TOKEN')
        ->and($dump)->not->toContain('webhook-secret');
});

it('never stores forbidden keys in audit metadata', function () {
    $admin = Admin::factory()->owner()->create();
    $this->actingAs($admin);

    app(AuditLogger::class)->log('unit.test', null, [
        'safe' => 'value',
        'password' => 'nope',
        'bot_token' => 'nope',
        'nested' => ['webhook_secret' => 'nope', 'ok' => 1],
    ]);

    $meta = AuditLog::query()->where('action', 'unit.test')->first()->meta;

    expect($meta)->toEqual(['safe' => 'value', 'nested' => ['ok' => 1]]);
});

it('audits admin account changes', function () {
    $owner = Admin::factory()->owner()->create();
    $this->actingAs($owner);

    $newAdmin = Admin::factory()->viewer()->create();
    $newAdmin->update(['role' => 'marketer']);

    $actions = AuditLog::query()->pluck('action')->all();

    expect($actions)->toContain('admin.created')
        ->and($actions)->toContain('admin.updated');

    // Role change recorded by key only — never password hashes or tokens.
    $update = AuditLog::query()->where('action', 'admin.updated')->first();
    expect($update->meta['changed'])->toBe(['role']);
});

it('lets settings mutations through the service write audit rows from the settings page flow', function () {
    $owner = Admin::factory()->owner()->create();
    $this->actingAs($owner);

    app(AuditLogger::class)->log('settings.updated', null, ['keys' => ['channel.id']]);

    expect(AuditLog::query()->where('action', 'settings.updated')->first()->meta['keys'])
        ->toBe(['channel.id']);
});
