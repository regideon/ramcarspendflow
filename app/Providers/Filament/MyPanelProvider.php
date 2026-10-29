<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Requestors and matrix approvers: a mobile-first panel with its own shell
 * (resources/views/filament/my/layout.blade.php) instead of Filament's sidebar and topbar.
 */
class MyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('my')
            ->path('my')
            ->login()
            ->brandName('Ramcar AP')
            ->viteTheme('resources/css/filament/my/theme.css')
            ->font('Inter')
            // The shell is designed light-only for now.
            ->darkMode(false)
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'info' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->discoverResources(in: app_path('Filament/My/Resources'), for: 'App\Filament\My\Resources')
            // App\Filament\My\Pages\Dashboard is picked up by discovery
            ->discoverPages(in: app_path('Filament/My/Pages'), for: 'App\Filament\My\Pages')
            ->discoverWidgets(in: app_path('Filament/My/Widgets'), for: 'App\Filament\My\Widgets')
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
