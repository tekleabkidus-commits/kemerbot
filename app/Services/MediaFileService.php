<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaKind;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Creates media_files rows from validated admin uploads (spec §4.13). The
 * Filament FileUpload components do MIME/size validation; this maps the
 * stored file to its kind and registers it for Telegram file_id reuse.
 */
final class MediaFileService
{
    private const KIND_BY_MIME = [
        'image/gif' => MediaKind::Animation,
        'image/jpeg' => MediaKind::Photo,
        'image/png' => MediaKind::Photo,
        'image/webp' => MediaKind::Photo,
        'video/mp4' => MediaKind::Video,
        'video/quicktime' => MediaKind::Video,
    ];

    public function register(string $path): MediaFile
    {
        $disk = Storage::disk();

        if (! $disk->exists($path)) {
            throw new InvalidArgumentException("Uploaded media not found: {$path}");
        }

        $mime = (string) $disk->mimeType($path);
        $kind = self::KIND_BY_MIME[$mime] ?? null;

        if ($kind === null) {
            throw new InvalidArgumentException("Unsupported media type: {$mime}");
        }

        return MediaFile::query()->create([
            'path' => $path,
            'kind' => $kind,
            'mime' => $mime,
            'size' => $disk->size($path),
        ]);
    }

    /** Accepted upload MIME types, for the Filament FileUpload components. */
    public static function acceptedMimeTypes(): array
    {
        return array_keys(self::KIND_BY_MIME);
    }

    public static function kindForMime(string $mime): ?MediaKind
    {
        return self::KIND_BY_MIME[$mime] ?? null;
    }
}
