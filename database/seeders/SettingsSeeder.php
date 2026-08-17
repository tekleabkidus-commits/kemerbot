<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Default runtime settings (spec §5.9). Only creates missing keys so re-seeding
 * never clobbers admin-edited values. Secrets never live here (spec §4.7).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'channel.id' => null,
            'channel.url' => null,
            'bot.default_language' => 'en',
            'telegram.send_rate' => (int) config('telegram.send_rate'),
            'broadcast.test_recipient_chat_ids' => [],
            'webapp.url' => 'https://sunbet.et',
            'welcome.message' => [
                'en' => "Welcome to SunBet, {first_name}! ⚽\nUse the menu below to explore matches, promos and more.",
                'am' => "እንኳን ወደ SunBet በደህና መጡ {first_name}! ⚽\nግጥሚያዎችን፣ ማስተዋወቂያዎችን እና ሌሎችንም ለማየት ከታች ያለውን ምናሌ ይጠቀሙ።",
            ],
            'welcome.media_file_id' => null,
            'features' => [
                'referrals' => true,
                'polls' => true,
                'mini_app' => true,
            ],
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
