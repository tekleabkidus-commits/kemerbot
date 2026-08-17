<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BotLanguage;
use Database\Factories\KeywordReplyTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordReplyTranslation extends Model
{
    /** @use HasFactory<KeywordReplyTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'keyword_reply_id',
        'lang',
        'reply_text',
    ];

    protected function casts(): array
    {
        return [
            'lang' => BotLanguage::class,
        ];
    }

    public function keywordReply(): BelongsTo
    {
        return $this->belongsTo(KeywordReply::class);
    }
}
