<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AutomationStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * delay_hours = delay since the previous step; step 0 is relative to the
 * trigger time (spec §4.4). Welcome-drip step 0 must have delay_hours > 0
 * (spec §4.2) — enforced in the admin layer, tested in the engine.
 */
class AutomationStep extends Model
{
    /** @use HasFactory<AutomationStepFactory> */
    use HasFactory;

    protected $fillable = [
        'automation_id',
        'step_no',
        'delay_hours',
        'media_file_id',
        'buttons',
    ];

    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'delay_hours' => 'integer',
            'buttons' => 'array',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AutomationStepTranslation::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
