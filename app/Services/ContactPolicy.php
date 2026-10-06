<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ContactPolicy
{
    public function reserve(User $user, string $key, string $topic = 'general'): string
    {
        return DB::transaction(function () use ($user, $key, $topic) {
            $fresh = User::query()->lockForUpdate()->find($user->id);
            if (! $fresh || $fresh->blocked_bot || ! $fresh->marketing_subscribed) {
                return 'skip';
            }
            if ($fresh->topics && ! in_array($topic, $fresh->topics, true)) {
                return 'skip';
            }
            if (DB::table('contact_send_slots')->where('delivery_key', $key)->exists()) {
                return 'send';
            }
            $settings = app(SettingsService::class);
            $hour = now()->timezone(config('app.display_timezone'))->hour;
            $start = $settings->get('messaging.quiet_start');
            $end = $settings->get('messaging.quiet_end');
            if ($start !== null && $end !== null && $start != $end) {
                $quiet = $start < $end ? ($hour >= $start && $hour < $end) : ($hour >= $start || $hour < $end);
                if ($quiet) {
                    return 'wait';
                }
            }
            $day = now()->timezone(config('app.display_timezone'))->startOfDay()->utc();
            $limit = min($fresh->daily_message_limit, (int) $settings->get('messaging.daily_limit', 3));
            if (DB::table('contact_send_slots')->where('user_id', $user->id)->where('reserved_at', '>=', $day)->count() >= $limit) {
                return 'wait';
            }
            DB::table('contact_send_slots')->insert(['user_id' => $user->id, 'delivery_key' => $key, 'reserved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return 'send';
        });
    }
}
