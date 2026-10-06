<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;

final class MediaPayload
{
    public function withFile(MediaFile $media, callable $send): mixed
    {
        if ($media->tg_file_id) {
            return $send($media->tg_file_id);
        }
        $stream = Storage::readStream($media->path);
        if (! is_resource($stream)) {
            throw new MediaUnavailable('Media is unavailable');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'kemer-media-');
        if ($tmp === false) {
            fclose($stream);
            throw new MediaUnavailable('Cannot allocate media temporary file');
        }
        try {
            $out = fopen($tmp, 'wb');
            try {
                stream_copy_to_stream($stream, $out);
            } finally {
                fclose($out);
                fclose($stream);
            }

            return $send($tmp);
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
