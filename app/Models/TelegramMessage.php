<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use Database\Factories\TelegramMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inbound messages + admin 1:1 replies only — never broadcast copies
 * (spec §6 table 18). Pruned after the retention window.
 */
class TelegramMessage extends Model
{
    /** @use HasFactory<TelegramMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tg_message_id',
        'direction',
        'type',
        'text',
        'media_meta',
        'admin_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'tg_message_id' => 'integer',
            'direction' => MessageDirection::class,
            'media_meta' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
