<?php

declare(strict_types=1);

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

it('casts role to the AdminRole enum', function () {
    $admin = Admin::factory()->owner()->create();

    expect($admin->refresh()->role)->toBe(AdminRole::Owner)
        ->and($admin->isOwner())->toBeTrue()
        ->and($admin->isViewer())->toBeFalse();
});

it('hashes passwords', function () {
    $admin = Admin::factory()->create(['password' => 'secret-password']);

    expect($admin->password)->not->toBe('secret-password')
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
});

it('seeds an owner account idempotently', function () {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    expect(Admin::query()->where('role', AdminRole::Owner)->count())->toBe(1);
});

it('rejects duplicate emails', function () {
    Admin::factory()->create(['email' => 'dup@kemerbet.co']);

    expect(fn () => Admin::factory()->create(['email' => 'dup@kemerbet.co']))
        ->toThrow(QueryException::class);
});

it('rejects roles outside the enum via check constraint', function () {
    // Raw insert to bypass the enum cast and hit the DB constraint itself.
    expect(fn () => DB::table('admins')->insert([
        'name' => 'Bad Role',
        'email' => 'bad-role@kemerbet.co',
        'password' => 'irrelevant',
        'role' => 'superadmin',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
