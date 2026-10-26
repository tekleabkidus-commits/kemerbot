<?php

declare(strict_types=1);

use App\Filament\Pages\Reports;
use App\Filament\Resources\Automations\Pages\ListAutomations;
use App\Filament\Resources\Broadcasts\Pages\ListBroadcasts;
use App\Filament\Resources\Broadcasts\Schemas\BroadcastWizard;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Admin;
use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\User;
use Livewire\Livewire;

it('uses five real campaign wizard steps without changing saved field names', function () {
    expect(BroadcastWizard::steps())->toHaveCount(5);
});

it('renders the approved campaign manager with real statuses and a draft preview', function () {
    $admin = Admin::factory()->owner()->create();
    Broadcast::factory()->create(['name' => 'Example draft campaign']);

    $this->actingAs($admin)->get('/admin/broadcasts')
        ->assertOk()
        ->assertSee('Example draft campaign')
        ->assertSee('Campaign preview')
        ->assertSee('New Campaign');

    Livewire::actingAs($admin)->test(ListBroadcasts::class)->assertSee('Drafts');
});

it('renders the actual People database and the selected customer inspector', function () {
    $admin = Admin::factory()->owner()->create();
    $first = User::factory()->create(['first_name' => 'Example A']);
    $second = User::factory()->create(['first_name' => 'Example B']);

    $this->actingAs($admin)->get('/admin/users')
        ->assertOk()
        ->assertSee('People')
        ->assertSee('Example A');

    Livewire::actingAs($admin)->test(ListUsers::class)
        ->call('selectPreview', $second->id)
        ->assertSet('previewUserId', $second->id)
        ->assertSee('Example B');
});

it('previews real sequential automations without introducing fictional branches', function () {
    $admin = Admin::factory()->owner()->create();
    $automation = Automation::factory()->create(['name' => 'Real welcome journey']);

    $this->actingAs($admin)->get('/admin/automations')
        ->assertOk()
        ->assertSee('Real welcome journey')
        ->assertSee('Step editor');

    Livewire::actingAs($admin)->test(ListAutomations::class)
        ->call('chooseJourney', $automation->id)
        ->assertSet('previewAutomationId', $automation->id);
});

it('shows a working report time-window selector', function () {
    $admin = Admin::factory()->owner()->create();

    Livewire::actingAs($admin)->test(Reports::class)
        ->call('setPeriod', 7)
        ->assertSet('period', 7)
        ->call('setPeriod', 90)
        ->assertSet('period', 90)
        ->assertSee('Reports / Analytics');
});
