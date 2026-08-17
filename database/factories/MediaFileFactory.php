<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'path' => 'media/'.fake()->uuid().'.jpg',
            'tg_file_id' => null,
            'kind' => MediaKind::Photo,
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
        ];
    }

    public function withTelegramFileId(): static
    {
        return $this->state(['tg_file_id' => 'AgACAgQAAxkBAAI'.fake()->regexify('[A-Za-z0-9_-]{20}')]);
    }
}
