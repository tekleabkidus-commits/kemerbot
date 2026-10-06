<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * DB-backed runtime settings with a short cache (spec §5.9). Secrets never
 * live here — bot token, webhook secret and keys stay in env (spec §4.7).
 */
final class SettingsService
{
    private const CACHE_KEY = 'settings:all';

    private const CACHE_SECONDS = 60;

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_SECONDS,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
