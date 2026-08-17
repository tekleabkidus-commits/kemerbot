<?php

namespace App\Filament\Support;

use App\Models\MediaFile;
use App\Services\MediaFileService;

/**
 * Bridges Filament FileUpload state (a stored path) to media_files rows
 * (spec §4.13). Re-selecting the same file never creates a duplicate row —
 * that would discard the cached Telegram file_id.
 */
class FormMedia
{
    public static function resolveMediaFileId(?string $uploadedPath, ?MediaFile $current): ?int
    {
        if ($uploadedPath === null || $uploadedPath === '') {
            return null;
        }

        if ($current !== null && $current->path === $uploadedPath) {
            return $current->id;
        }

        return app(MediaFileService::class)->register($uploadedPath)->id;
    }
}
