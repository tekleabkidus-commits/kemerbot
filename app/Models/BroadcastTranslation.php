<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BotLanguage;
use Database\Factories\BroadcastTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastTranslation extends Model
{
    /** @use HasFactory<BroadcastTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'lang',
        'text',
        'media_file_id',
    ];

    protected function casts(): array
    {
        return [
            'lang' => BotLanguage::class,
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
