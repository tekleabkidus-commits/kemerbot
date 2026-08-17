<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BotLanguage;
use Database\Factories\BroadcastButtonTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastButtonTranslation extends Model
{
    /** @use HasFactory<BroadcastButtonTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'broadcast_button_id',
        'lang',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'lang' => BotLanguage::class,
        ];
    }

    public function button(): BelongsTo
    {
        return $this->belongsTo(BroadcastButton::class, 'broadcast_button_id');
    }
}
