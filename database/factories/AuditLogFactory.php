<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'subject_type' => null,
            'subject_id' => null,
            'meta' => ['fields' => ['name']],
            'created_at' => now(),
        ];
    }
}
