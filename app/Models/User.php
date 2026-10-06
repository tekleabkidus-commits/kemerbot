<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Telegram bot audience member — not a panel account (that's Admin).
 */
class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'marketing_subscribed', 'preferred_language', 'topics', 'daily_message_limit', 'support_status', 'assigned_admin_id', 'support_note', 'converted_at',
        'tg_chat_id',
        'first_name',
        'username',
        'language',
        'source',
        'referred_by_user_id',
        'joined_at',
        'last_active_at',
        'blocked_bot',
        'blocked_at',
        'in_channel',
        'channel_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'marketing_subscribed' => 'boolean', 'topics' => 'array', 'daily_message_limit' => 'integer', 'converted_at' => 'datetime',
            'tg_chat_id' => 'integer',
            'joined_at' => 'datetime',
            'last_active_at' => 'datetime',
            'blocked_bot' => 'boolean',
            'blocked_at' => 'datetime',
            'in_channel' => 'boolean',
            'channel_checked_at' => 'datetime',
        ];
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_user_id');
    }

    public function automationStates(): HasMany
    {
        return $this->hasMany(AutomationUserState::class);
    }

    public function telegramMessages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function buttonClicks(): HasMany
    {
        return $this->hasMany(ButtonClick::class);
    }

    public function pollInstances(): HasMany
    {
        return $this->hasMany(PollInstance::class);
    }

    public function broadcastFailures(): HasMany
    {
        return $this->hasMany(BroadcastFailure::class);
    }
}
