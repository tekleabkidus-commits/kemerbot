<?php

declare(strict_types=1);

namespace App\Telegram;

final readonly class StartPayload
{
    private function __construct(
        public StartPayloadType $type,
        public ?string $source = null,
        public ?int $referrerUserId = null,
    ) {}

    public static function none(): self
    {
        return new self(StartPayloadType::None);
    }

    public static function source(string $code): self
    {
        return new self(StartPayloadType::Source, source: $code);
    }

    public static function referral(int $referrerUserId): self
    {
        return new self(StartPayloadType::Referral, referrerUserId: $referrerUserId);
    }

    public static function invalid(): self
    {
        return new self(StartPayloadType::Invalid);
    }
}
