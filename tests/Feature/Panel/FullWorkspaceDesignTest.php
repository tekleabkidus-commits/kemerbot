<?php

declare(strict_types=1);

use App\Filament\Pages\Calendar;
use App\Models\Admin;
use App\Models\Broadcast;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = Admin::factory()->owner()->create();
});

it('loads the redesigned dashboard with live audience metrics', function () {
    User::factory()->create(['first_name' => 'Support Example', 'support_status' => 'open']);

    $this->actingAs($this->owner)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Audience growth')
        ->assertSee('Active conversations')
        ->assertSee('Support Example');
});

it('shows a genuine scheduled campaign on the monthly calendar', function () {
    $date = now(config('app.display_timezone'))->addDays(3);
    Broadcast::factory()->create([
        'name' => 'Calendar verification',
        'status' => 'scheduled',
        'scheduled_at' => $date,
    ]);

    $this->actingAs($this->owner)
        ->get('/admin/calendar')
        ->assertOk()
        ->assertSee('Your content calendar')
        ->assertSee('Calendar verification');

    Livewire::actingAs($this->owner)
        ->test(Calendar::class)
        ->call('nextMonth')
        ->assertSet('monthOffset', 1)
        ->call('currentMonth')
        ->assertSet('monthOffset', 0);
});

it('renders redesigned reporting and delivery pages without inventing totals', function () {
    $this->actingAs($this->owner)
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('Understand your impact');

    $this->actingAs($this->owner)
        ->get('/admin/delivery-center')
        ->assertOk()
        ->assertSee('Every message accounted for');
});

it('renders new settings layout and honors viewer read-only permissions', function () {
    $viewer = Admin::factory()->viewer()->create();

    $this->actingAs($viewer)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('Read-only access')
        ->assertDontSee('Save settings');
});

it('ships the global responsive design tokens used throughout the panel', function () {
    $css = file_get_contents(public_path('css/kemer-2026.css'));

    expect($css)->toContain('--ks-primary:')
        ->toContain('@media(max-width:760px)')
        ->toContain('.fi-ta')
        ->toContain('.fi-input-wrp')
        ->toContain('.fi-sc-wizard-header');
});
