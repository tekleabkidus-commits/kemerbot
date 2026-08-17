<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Uniform envelope for every Telegram Bot API call (spec §10). Error
 * classification lives here — nowhere else interprets Telegram error codes.
 */
final readonly class TelegramResponse
{
    private function __construct(
        public bool $ok,
        public mixed $result = null,
        public ?int $errorCode = null,
        public ?string $description = null,
        public ?int $retryAfter = null,
        public bool $network = false,
    ) {}

    public static function success(mixed $result = true): self
    {
        return new self(ok: true, result: $result);
    }

    public static function failure(int $errorCode, string $description, ?int $retryAfter = null): self
    {
        return new self(ok: false, errorCode: $errorCode, description: $description, retryAfter: $retryAfter);
    }

    public static function networkFailure(string $description): self
    {
        return new self(ok: false, description: $description, network: true);
    }

    public function successful(): bool
    {
        return $this->ok;
    }

    /** 403: the user blocked the bot (spec §5.1). */
    public function blockedByUser(): bool
    {
        return $this->errorCode === 403;
    }

    /** 429: flood control — honor retryAfter (spec §10). */
    public function rateLimited(): bool
    {
        return $this->errorCode === 429;
    }

    /** Worth retrying: transient network trouble, 5xx, or flood control. */
    public function retryable(): bool
    {
        return $this->network
            || $this->rateLimited()
            || ($this->errorCode !== null && $this->errorCode >= 500);
    }

    public function messageId(): ?int
    {
        return is_array($this->result) ? ($this->result['message_id'] ?? null) : null;
    }

    /**
     * Telegram file_id of sent media, used for reuse on later sends (spec §4.13).
     */
    public function fileId(): ?string
    {
        if (! is_array($this->result)) {
            return null;
        }

        if (isset($this->result['photo']) && is_array($this->result['photo'])) {
            // Copy first: end() takes a reference, which readonly forbids.
            $sizes = $this->result['photo'];
            $largest = end($sizes);

            return is_array($largest) ? ($largest['file_id'] ?? null) : null;
        }

        return $this->result['video']['file_id']
            ?? $this->result['animation']['file_id']
            ?? $this->result['document']['file_id']
            ?? null;
    }
}
