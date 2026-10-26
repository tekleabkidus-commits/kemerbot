<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AudienceSegment;

it('renders saved audiences with real rows and supported Filament callbacks', function () {
    $owner = Admin::factory()->owner()->create();

    AudienceSegment::create([
        'name' => 'Recently active users',
        'description' => 'People active in the last week',
        'filter' => ['active_last_days' => 7],
    ]);

    $this->actingAs($owner)
        ->get('/admin/segments')
        ->assertOk()
        ->assertSee('Recently active users');
});
