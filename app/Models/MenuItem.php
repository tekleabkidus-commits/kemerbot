<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuActionType;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'position',
        'action_type',
        'url',
        'media_file_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'action_type' => MenuActionType::class,
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    /**
     * True when re-parenting this item under $candidateParentId would create a
     * cycle (itself, or any of its descendants). Used by admin-side validation;
     * the bot never renders cycles because they can't be persisted through it.
     */
    public function wouldCreateCycle(?int $candidateParentId): bool
    {
        if ($candidateParentId === null) {
            return false;
        }

        $currentId = $candidateParentId;

        while ($currentId !== null) {
            if ($currentId === $this->id) {
                return true;
            }

            $currentId = MenuItem::query()->whereKey($currentId)->value('parent_id');
        }

        return false;
    }
}
