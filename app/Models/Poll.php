<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parent poll definition; question/options are {en, am} jsonb language maps
 * (spec §4.12). Aggregated answer_counts keyed by option index.
 */
class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'question',
        'options',
        'answer_counts',
        'is_anonymous',
    ];

    protected function casts(): array
    {
        return [
            'question' => 'array',
            'options' => 'array',
            'answer_counts' => 'array',
            'is_anonymous' => 'boolean',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(PollInstance::class);
    }
}
