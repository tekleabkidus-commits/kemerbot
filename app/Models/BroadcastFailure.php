<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FailureCategory;
use Database\Factories\BroadcastFailureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user permanent failure record — the only per-recipient rows a broadcast
 * writes (Principle 3). `sanitized_error` never contains tokens or chat data.
 */
class BroadcastFailure extends Model
{
    /** @use HasFactory<BroadcastFailureFactory> */
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'user_id',
        'tg_error_code',
        'category',
        'sanitized_error',
        'attempts',
        'first_failed_at',
        'last_failed_at',
    ];

    protected function casts(): array
    {
        return [
            'tg_error_code' => 'integer',
            'category' => FailureCategory::class,
            'attempts' => 'integer',
            'first_failed_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
