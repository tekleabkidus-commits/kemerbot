<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ButtonKind;
use Database\Factories\BroadcastButtonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastButton extends Model
{
    /** @use HasFactory<BroadcastButtonFactory> */
    use HasFactory;

    protected $fillable = [
        'broadcast_id',
        'row',
        'position',
        'kind',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'row' => 'integer',
            'position' => 'integer',
            'kind' => ButtonKind::class,
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BroadcastButtonTranslation::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ButtonClick::class, 'button_id');
    }
}
