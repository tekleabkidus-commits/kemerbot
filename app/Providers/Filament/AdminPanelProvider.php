<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Home;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()->profile()
            ->multiFactorAuthentication([AppAuthentication::make()], isRequired: fn () => ! app()->environment(['local', 'testing']))
            ->brandName('KemerBet Studio')->brandLogo(fn () => view('filament.brand'))->brandLogoHeight('40px')
            ->homeUrl(fn () => Home::getUrl())->sidebarWidth('240px')->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::head.end', fn () => new HtmlString('<link rel="stylesheet" href="'.asset('css/kemer-workspace.css').'">'))
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Spec §12 navigation: Overview · Engagement · Bot Content · Audience · Administration.
            ->navigationGroups([
                'Overview',
                'Campaigns',
                'Audience',
                'Bot Content',
                'Settings',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
