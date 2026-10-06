<?php

namespace App\Filament\Pages;

use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\User;
use App\Services\DashboardMetrics;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\DB;

class Home extends Page
{
    protected string $view = 'filament.pages.home';

    protected static ?string $slug = 'home';

    protected static ?string $navigationLabel = 'Home';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = -10;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getTitle(): string
    {
        return 'Your workspace';
    }

    protected function getViewData(): array
    {
        return ['kpis' => app(DashboardMetrics::class)->kpis(), 'campaigns' => Broadcast::query()->latest()->limit(5)->get(),
            'scheduled' => Broadcast::query()->where('status', 'scheduled')->orderBy('scheduled_at')->limit(4)->get(),
            'openInbox' => User::query()->where('support_status', 'open')->count(), 'activeJourneys' => Automation::query()->where('is_active', true)->count(),
            'joins' => app(DashboardMetrics::class)->dailyJoins(14), 'subscribers' => User::query()->where('marketing_subscribed', true)->where('blocked_bot', false)->count(),
            'pendingUpdates' => DB::table('telegram_updates')->where('status', '!=', 'processed')->count()];
    }
}
