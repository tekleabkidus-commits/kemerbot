<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use App\Models\Admin;
use App\Models\Broadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => BroadcastType::Standard,
            'status' => BroadcastStatus::Draft,
            'audience_filter' => ['everyone' => true],
            'audience_snapshot_count' => null,
            'scheduled_at' => null,
            'recurrence' => null,
            'template_fields' => null,
            'created_by' => Admin::factory(),
            'queued' => 0,
            'sent' => 0,
            'blocked' => 0,
            'failed' => 0,
        ];
    }

    public function scheduled(): static
    {
        return $this->state([
            'status' => BroadcastStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function recurring(array $recurrence = ['frequency' => 'weekly', 'day' => 'saturday', 'time' => '09:00']): static
    {
        return $this->state([
            'status' => BroadcastStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
            'recurrence' => $recurrence,
        ]);
    }

    public function matchCard(): static
    {
        return $this->state([
            'type' => BroadcastType::MatchCard,
            'template_fields' => [
                'home_team' => 'St. George',
                'away_team' => 'Ethiopia Bunna',
                'kickoff_at' => now()->addDays(2)->toIso8601String(),
                'odds' => ['home' => '2.10', 'draw' => '3.20', 'away' => '3.50'],
                'cta' => 'Bet now',
            ],
        ]);
    }

    public function poll(): static
    {
        return $this->state(['type' => BroadcastType::Poll]);
    }
}
