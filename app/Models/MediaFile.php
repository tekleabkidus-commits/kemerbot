<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaKind;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per upload; `tg_file_id` is persisted after the first successful
 * Telegram send and reused for all subsequent sends (spec §4.13).
 */
class MediaFile extends Model
{
    /** @use HasFactory<MediaFileFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'tg_file_id',
        'kind',
        'mime',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'size' => 'integer',
        ];
    }
}
