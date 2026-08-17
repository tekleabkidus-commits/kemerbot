<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Starter main menu (KemerBet branding). Non-clobbering: seeds only when no
 * menu items exist, so marketer-edited menus are never touched by re-seeding.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        if (MenuItem::query()->exists()) {
            return;
        }

        $about = MenuItem::query()->create(['action_type' => 'reply', 'position' => 0]);
        $about->translations()->create([
            'lang' => 'en',
            'label' => 'ℹ️ About KemerBet',
            'reply_text' => "KemerBet — Ethiopia's home of sport. ⚽\nOdds, promos and more at kemerbet.co",
        ]);
        $about->translations()->create([
            'lang' => 'am',
            'label' => 'ℹ️ ስለ KemerBet',
            'reply_text' => "KemerBet — የኢትዮጵያ የስፖርት ቤት። ⚽\nkemerbet.co ላይ ይጎብኙን",
        ]);

        $games = MenuItem::query()->create(['action_type' => 'submenu', 'position' => 1]);
        $games->translations()->create(['lang' => 'en', 'label' => '🎮 Games']);
        $games->translations()->create(['lang' => 'am', 'label' => '🎮 ጨዋታዎች']);

        $sportsbook = MenuItem::query()->create([
            'parent_id' => $games->id,
            'action_type' => 'url',
            'url' => 'https://kemerbet.co/en/sport',
            'position' => 0,
        ]);
        $sportsbook->translations()->create(['lang' => 'en', 'label' => '⚽ Sportsbook']);
        $sportsbook->translations()->create(['lang' => 'am', 'label' => '⚽ ስፖርት']);

        $casino = MenuItem::query()->create([
            'parent_id' => $games->id,
            'action_type' => 'url',
            'url' => 'https://kemerbet.co/en/fastgames-lobby/All/',
            'position' => 1,
        ]);
        $casino->translations()->create(['lang' => 'en', 'label' => '🎰 Casino']);
        $casino->translations()->create(['lang' => 'am', 'label' => '🎰 ካሲኖ']);

        $site = MenuItem::query()->create([
            'action_type' => 'url',
            'url' => 'https://kemerbet.co',
            'position' => 2,
        ]);
        $site->translations()->create(['lang' => 'en', 'label' => '🌍 Visit KemerBet']);
        $site->translations()->create(['lang' => 'am', 'label' => '🌍 KemerBet ይጎብኙ']);

        $invite = MenuItem::query()->create(['action_type' => 'invite', 'position' => 3]);
        $invite->translations()->create(['lang' => 'en', 'label' => '🎉 Invite friends']);
        $invite->translations()->create(['lang' => 'am', 'label' => '🎉 ጓደኞችን ይጋብዙ']);
    }
}
