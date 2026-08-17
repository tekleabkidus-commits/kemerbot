<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KeywordMatchType;
use Database\Factories\KeywordReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KeywordReply extends Model
{
    /** @use HasFactory<KeywordReplyFactory> */
    use HasFactory;

    protected $fillable = [
        'keywords',
        'match_type',
        'position',
        'media_file_id',
        'buttons',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'match_type' => KeywordMatchType::class,
            'position' => 'integer',
            'buttons' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(KeywordReplyTranslation::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
