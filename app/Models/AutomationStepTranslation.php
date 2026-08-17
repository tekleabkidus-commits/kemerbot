<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BotLanguage;
use Database\Factories\AutomationStepTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationStepTranslation extends Model
{
    /** @use HasFactory<AutomationStepTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'automation_step_id',
        'lang',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'lang' => BotLanguage::class,
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'automation_step_id');
    }
}
