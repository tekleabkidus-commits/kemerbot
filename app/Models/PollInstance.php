<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PollInstanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correlates one sent Telegram poll message to its parent poll (documented
 * exception to Principle 3). Minimal columns only.
 */
class PollInstance extends Model
{
    /** @use HasFactory<PollInstanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'previous_counts', 'last_update_id',
        'poll_id',
        'user_id',
        'tg_poll_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime', 'previous_counts' => 'array', 'last_update_id' => 'integer',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
