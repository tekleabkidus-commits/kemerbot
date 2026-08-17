<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutomationTrigger;
use Database\Factories\AutomationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    /** @use HasFactory<AutomationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'trigger',
        'trigger_config',
        'cooldown_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => AutomationTrigger::class,
            'trigger_config' => 'array',
            'cooldown_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class)->orderBy('step_no');
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(AutomationUserState::class);
    }
}
