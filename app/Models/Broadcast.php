<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastStatus;
use App\Enums\BroadcastType;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Campaign row. Status transitions go only through BroadcastLifecycle (spec §7);
 * each recipient outcome and its counter commit in the same transaction.
 */
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'topic', 'expires_at', 'approved_at', 'approved_by', 'snapshot_built_at', 'experiment', 'failure_reason',
        'type',
        'status',
        'audience_filter',
        'audience_snapshot_count',
        'scheduled_at',
        'recurrence',
        'template_fields',
        'created_by',
        'queued',
        'sent',
        'blocked',
        'failed', 'skipped',
        'started_at',
        'finished_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime', 'approved_at' => 'datetime', 'snapshot_built_at' => 'datetime', 'experiment' => 'array',
            'type' => BroadcastType::class,
            'status' => BroadcastStatus::class,
            'audience_filter' => 'array',
            'audience_snapshot_count' => 'integer',
            'scheduled_at' => 'datetime',
            'recurrence' => 'array',
            'template_fields' => 'array',
            'queued' => 'integer',
            'sent' => 'integer',
            'blocked' => 'integer',
            'failed' => 'integer', 'skipped' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'occurrence_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BroadcastTranslation::class);
    }

    public function buttons(): HasMany
    {
        return $this->hasMany(BroadcastButton::class)->orderBy('row')->orderBy('position');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(BroadcastFailure::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ButtonClick::class);
    }

    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    public function isRecurring(): bool
    {
        return $this->recurrence !== null;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class, 'parent_broadcast_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Broadcast::class, 'parent_broadcast_id');
    }
}
