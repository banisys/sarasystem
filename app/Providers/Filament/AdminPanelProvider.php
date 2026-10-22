<?php

namespace App\Providers\Filament;

use App\Http\Middleware\MakeFilamentTenantCurrent;
use App\Models\Tenant;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;
use Nwidart\Modules\Facades\Module;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $this->discoverModules($panel)
            ->default()
            ->id('admin')
            ->path('cpadmin')
            ->brandName(fn (): string => config('app.name'))
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                MakeFilamentTenantCurrent::class,
            ], isPersistent: true)
            // No login page of its own: guests are sent to the shared /login (ADR 0004).
            // The account page is the shared Inertia one (/profile).
            ->userMenuItems([
                Action::make('account')
                    ->label('حساب کاربری')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => route('profile.edit')),
            ])
            ->colors([
                'primary' => Color::Violet,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                // Roles are defined centrally (global, or for one tenant via the
                // team field) and assigned per tenant; the list is not tenant-scoped.
                FilamentShieldPlugin::make()
                    ->centralApp()
                    ->scopeToTenant(false),
                FilamentApexChartsPlugin::make(),
            ]);
    }

    /**
     * Each enabled module contributes its own resources, pages and widgets
     * from Modules/{Name}/app/Filament (docs/decisions/0005-modules.md).
     * A disabled module adds nothing; a missing directory is skipped.
     */
    private function discoverModules(Panel $panel): Panel
    {
        foreach (Module::allEnabled() as $module) {
            $name = $module->getStudlyName();

            $panel
                ->discoverResources(in: $module->getExtraPath('app/Filament/Resources'), for: "Modules\\{$name}\\Filament\\Resources")
                ->discoverPages(in: $module->getExtraPath('app/Filament/Pages'), for: "Modules\\{$name}\\Filament\\Pages")
                ->discoverWidgets(in: $module->getExtraPath('app/Filament/Widgets'), for: "Modules\\{$name}\\Filament\\Widgets");
        }

        return $panel;
    }
}
