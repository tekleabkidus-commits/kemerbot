<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\Poll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    public function definition(): array
    {
        return [
            'broadcast_id' => Broadcast::factory()->poll(),
            'question' => [
                'en' => 'Who wins this weekend?',
                'am' => 'በዚህ ሳምንት ማን ያሸንፋል?',
            ],
            'options' => [
                'en' => ['St. George', 'Ethiopia Bunna', 'Draw'],
                'am' => ['ቅዱስ ጊዮርጊስ', 'ኢትዮጵያ ቡና', 'አቻ'],
            ],
            'answer_counts' => null,
            'is_anonymous' => true,
        ];
    }
}
