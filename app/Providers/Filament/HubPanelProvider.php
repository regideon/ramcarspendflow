<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
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
            // Custom CSS turned off for now, to see Filament's native styling
            ->assets([
                Css::make('custom-stylesheet', asset('css/app/custom-stylesheet.css')),
                //     Css::make('custom-stylesheet-fontawesome-all.min', asset('css/app/custom-stylesheet-fontawesome-all.min.css')),
                //     Css::make('spreadsheet', asset('css/app/spreadsheet.css')),
                //     Css::make('knowledge-base', asset('css/app/knowledge-base.css')),
            ])
            ->brandLogo(asset('images/logo-01.png'))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
<script>
    (() => {
        const attrOf = (box, attribute) => {
            const holder = box.closest('tr')?.querySelector(`[${attribute}]`)
            return holder ? holder.getAttribute(attribute) : null
        }

        const companyOf = (box) => attrOf(box, 'data-company-code')
        const vendorOf = (box) => attrOf(box, 'data-vendor-code')

        // Filament keeps row selection in its Alpine table component until an
        // action mounts, so the Livewire `selectedTableRecords` property is
        // empty while paging. Read the Alpine state instead: it survives
        // pagination, while the visible checkboxes do not.
        const selectedCount = (root) => {
            const table = window.Alpine?.$data(root)

            if (!table || !table.selectedRecords) return null

            if (table.isTrackingDeselectedRecords) {
                const total = parseInt(table.$refs?.allSelectableRecordsCount?.value ?? '0', 10)

                return Math.max(total - table.deselectedRecords.size, 0)
            }

            return table.selectedRecords.size
        }

        const apply = (root) => {
            if (!root || !root.isConnected) return

            const boxes = Array.from(root.querySelectorAll('.fi-ta-record-checkbox'))
            const checked = boxes.find((box) => box.checked)

            // Learn the locked company/vendor from a visible checked row, but
            // never clear it just because paging moved the selected row off
            // screen.
            if (checked) {
                const company = companyOf(checked)
                const vendor = vendorOf(checked)

                if (company !== null) root.__lockedCompany = company
                if (vendor !== null) root.__lockedVendor = vendor
            }

            const count = selectedCount(root)

            // Only a real empty selection clears the lock. `null` means Alpine
            // is not ready yet, so keep the previous lock.
            if (count === 0 && ! checked) {
                root.__lockedCompany = null
                root.__lockedVendor = null
            }

            const lockedCompany = root.__lockedCompany ?? null
            const lockedVendor = root.__lockedVendor ?? null
            const lockKey = `${lockedCompany ?? ''}|${lockedVendor ?? ''}`

            for (const box of boxes) {
                const row = box.closest('tr')
                const company = companyOf(box)
                const vendor = vendorOf(box)
                const hide =
                    (lockedCompany !== null && company !== null && company !== lockedCompany) ||
                    (lockedVendor !== null && vendor !== null && vendor !== lockedVendor)

                box.disabled = hide

                if (row) row.style.display = hide ? 'none' : ''
            }

            // Push the locked values into the table's SelectFilters so they
            // become real, server-side filters that survive pagination - not
            // just a visual hide. Only set when the lock changes, to avoid
            // request loops. When the last row is deselected, reset both.
            const wire = root.__filamentCompanyWire

            if (wire && root.__appliedLock !== lockKey) {
                const previous = root.__appliedLock
                root.__appliedLock = lockKey

                if (lockedCompany !== null) {
                    wire.set('tableFilters.company_code.value', lockedCompany, true)
                } else if (previous) {
                    wire.set('tableFilters.company_code.value', null, true)
                }

                if (lockedVendor !== null) {
                    wire.set('tableFilters.vendor_code.value', lockedVendor, true)
                } else if (previous) {
                    wire.set('tableFilters.vendor_code.value', null, true)
                }
            }
        }

        const applyAll = () => document.querySelectorAll('.fi-ta').forEach((root) => apply(root))
        const schedule = () => window.requestAnimationFrame(applyAll)

        window.filamentCompanyRowLock = (root, wire) => {
            if (wire) root.__filamentCompanyWire = wire

            apply(root)
        }

        document.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('.fi-ta')) schedule()
        }, true)

        document.addEventListener('change', (event) => {
            if (event.target instanceof Element && event.target.closest('.fi-ta')) schedule()
        }, true)

        document.addEventListener('livewire:navigated', schedule)

        if (window.Livewire && window.Livewire.hook) {
            window.Livewire.hook('morphed', schedule)
        }
    })()
</script>
HTML,
            )

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
