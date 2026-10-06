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
        if (! app()->environment(['local', 'testing']) && (! env('SEED_ADMIN_EMAIL') || strlen((string) env('SEED_ADMIN_PASSWORD')) < 16)) {
            throw new \RuntimeException('Shared environments require an explicit email and password of at least 16 characters');
        }
        Admin::query()->firstOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL') ?: 'owner@kemerbet.co'],
            [
                'name' => 'KemerBet Owner',
                // Override via SEED_ADMIN_PASSWORD before seeding any shared environment.
                'password' => env('SEED_ADMIN_PASSWORD') ?: 'password',
                'role' => AdminRole::Owner,
            ],
        );
    }
}
