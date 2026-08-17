<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => AdminRole::Marketer,
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => AdminRole::Owner]);
    }

    public function marketer(): static
    {
        return $this->state(['role' => AdminRole::Marketer]);
    }

    public function viewer(): static
    {
        return $this->state(['role' => AdminRole::Viewer]);
    }
}
