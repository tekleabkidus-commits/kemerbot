<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'owner@kemerbet.co')],
            [
                'name' => 'KemerBet Owner',
                // Override via SEED_ADMIN_PASSWORD before seeding any shared environment.
                'password' => env('SEED_ADMIN_PASSWORD', 'password'),
                'role' => AdminRole::Owner,
            ],
        );
    }
}
