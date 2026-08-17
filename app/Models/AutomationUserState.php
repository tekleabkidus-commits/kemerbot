<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutomationUserStatus;
use Database\Factories\AutomationUserStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable per-user automation execution state (documented exception to
 * Principle 3). unique(automation_id, user_id, trigger_key) prevents
 * duplicate enrollment; cooldown blocks re-entry (spec §8).
 */
class AutomationUserState extends Model
{
    /** @use HasFactory<AutomationUserStateFactory> */
    use HasFactory;

    protected $fillable = [
        'automation_id',
        'user_id',
        'current_step_no',
        'status',
        'triggered_at',
        'last_step_sent_at',
        'next_step_at',
        'completed_at',
        'cooldown_until',
        'trigger_key',
    ];

    protected function casts(): array
    {
        return [
            'current_step_no' => 'integer',
            'status' => AutomationUserStatus::class,
            'triggered_at' => 'datetime',
            'last_step_sent_at' => 'datetime',
            'next_step_at' => 'datetime',
            'completed_at' => 'datetime',
            'cooldown_until' => 'datetime',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
