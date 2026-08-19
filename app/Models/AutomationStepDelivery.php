<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutomationDeliveryStatus;
use Database\Factories\AutomationStepDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transactional outbox row for one automation step send (HARDENING §4).
 * Created in the same transaction that claims/parks the user state, owned by
 * the queue job through sending → sent/failed. unique(state, step) makes a
 * step deliverable at most once per enrollment.
 */
class AutomationStepDelivery extends Model
{
    /** @use HasFactory<AutomationStepDeliveryFactory> */
    use HasFactory;

    use Prunable;

    /** Terminal outbox rows are audit detail, not live state: prune after 90d. */
    public function prunable(): Builder
    {
        return static::query()
            ->whereIn('status', [AutomationDeliveryStatus::Sent->value, AutomationDeliveryStatus::Failed->value])
            ->where('updated_at', '<', now()->subDays(90));
    }

    protected $fillable = [
        'automation_user_state_id',
        'automation_step_id',
        'user_id',
        'status',
        'attempt_count',
        'queued_at',
        'sent_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => AutomationDeliveryStatus::class,
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(AutomationUserState::class, 'automation_user_state_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'automation_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
