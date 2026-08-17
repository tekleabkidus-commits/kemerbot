<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BotLanguage;
use Database\Factories\MenuItemTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemTranslation extends Model
{
    /** @use HasFactory<MenuItemTranslationFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'lang',
        'label',
        'reply_text',
    ];

    protected function casts(): array
    {
        return [
            'lang' => BotLanguage::class,
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
