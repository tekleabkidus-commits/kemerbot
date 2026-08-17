<?php

declare(strict_types=1);

use App\Models\Admin;

it('shows the panel login page to guests', function () {
    $this->get('/admin/login')->assertOk();
});

it('redirects guests away from the panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an authenticated admin reach the panel', function () {
    $admin = Admin::factory()->owner()->create();

    $this->actingAs($admin)->get('/admin')->assertOk();
});
