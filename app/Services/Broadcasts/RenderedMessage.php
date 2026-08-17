<?php

declare(strict_types=1);

namespace App\Services\Broadcasts;

use App\Models\MediaFile;

final readonly class RenderedMessage
{
    public function __construct(
        public ?string $text,
        public ?MediaFile $media = null,
        public ?array $replyMarkup = null,
    ) {}
}
