<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ButtonClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only callback-button analytics (documented exception to Principle 3).
 */
class ButtonClick extends Model
{
    /** @use HasFactory<ButtonClickFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'broadcast_id',
        'button_id',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function button(): BelongsTo
    {
        return $this->belongsTo(BroadcastButton::class, 'button_id');
    }
}
