<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Hammadzafar05\MobileBottomNav\MobileBottomNav;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class HubPanelProvider extends PanelProvider
{
    /**
     * Hand-tuned so the mid shades keep their saturation (Color::hex() mutes them).
     *
     * @var array<int, string>
     */
    public const array BRAND_BLUE = [
        50 => 'oklch(0.97 0.014 255)',
        100 => 'oklch(0.94 0.034 256)',
        200 => 'oklch(0.885 0.066 257)',
        300 => 'oklch(0.81 0.112 258)',
        400 => 'oklch(0.71 0.165 259)',
        500 => 'oklch(0.63 0.21 260)',
        600 => 'oklch(0.569 0.237 260.4)',
        700 => 'oklch(0.49 0.215 261)',
        800 => 'oklch(0.42 0.18 262)',
        900 => 'oklch(0.355 0.14 263)',
        950 => 'oklch(0.25 0.095 264)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('hub')
            ->path('hub')
            ->login()
            ->viteTheme('resources/css/filament/hub/theme.css')

            ->passwordReset()
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->profile(isSimple: false)
            ->topNavigation()
            ->maxContentWidth('screen-2xl')
            // A render hook, not ->assets(): panel assets load in every panel, and this
            // stylesheet's overrides (e.g. .text-base) must not reach the My panel.
            ->renderHook(
                PanelsRenderHook::STYLES_BEFORE,
                fn (): HtmlString => new HtmlString('<link rel="stylesheet" href="'.asset('css/app/custom-stylesheet.css').'" />'),
            )
            ->brandLogo(asset('images/logo-01.png'))

            // Enterprise blue (DigitalOcean-like): primary-600 is #0069FF; slate greys,
            // sky for "info" so in-progress badges stay distinct from the brand blue.
            ->colors([
                'primary' => self::BRAND_BLUE,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->navigationGroups([
                'RFP',
                'P2P',
                'Settings',
                'Help',
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            // App\Filament\Pages\Dashboard (per-role home) is picked up by discovery
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                MobileBottomNav::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
