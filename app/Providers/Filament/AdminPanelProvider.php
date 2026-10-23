<?php

namespace App\Providers\Filament;

use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\PanelTable;
use App\Http\Middleware\MakeFilamentTenantCurrent;
use App\Models\Tenant;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\Column;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Builder;
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
        $this->configureComponentDefaults();

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
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->font('Shabnam', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->spa()
            // No global search box in the top bar; the sidebar has its own menu search.
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            // Search box (with history and collapse-all) at the top of the sidebar menu.
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn (): View => view('filament.admin.sidebar-nav-search'))
            // Collapsible table filters stay open across reloads when left open.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.admin.persist-filters-open'))
            // Second horizontal scrollbar above every wide table.
            ->renderHook(TablesRenderHook::TOOLBAR_AFTER, fn (): View => view('filament.admin.table-top-scroll'))
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
     * Panel-wide defaults for tables and fields (ported from LMS); a
     * component's own calls run after these and can override them.
     */
    private function configureComponentDefaults(): void
    {
        // Row actions in a dropdown in the first column, no row click (PanelTable).
        $this->app->bind(Table::class, PanelTable::class);

        // No search box (text lookups are filters, see TextFilter; a table
        // can opt back in with ->searchable()). Filters in a dropdown beside
        // the column manager (any AboveContent* layout puts the trigger on its
        // own row), 25 rows per page, and newest records first when the model
        // keeps timestamps.
        Table::configureUsing(fn (Table $table) => $table
            ->searchable(false)
            ->filtersLayout(FiltersLayout::Dropdown)
            ->filtersFormColumns(['sm' => 2, 'lg' => 3, 'xl' => 4])
            ->filtersFormWidth(Width::SixExtraLarge)
            ->filtersFormMaxHeight('32rem')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort(function (Builder $query, string $direction): ?Builder {
                $model = $query->getModel();

                return $model->usesTimestamps() && $model->getCreatedAtColumn()
                    ? $query->orderBy($model->qualifyColumn($model->getCreatedAtColumn()), $direction)
                    : null;
            }, 'desc'));

        // Every column can be hidden from the column manager; a column that
        // must stay visible opts out with ->toggleable(false).
        Column::configureUsing(fn (Column $column) => $column->toggleable());

        // Numeric inputs: left-to-right inside RTL forms, with thousands
        // separators. Closures, because ->numeric() is called after this runs.
        TextInput::configureUsing(function (TextInput $component): void {
            $component
                ->extraInputAttributes(
                    fn (TextInput $component): array => $component->isNumeric() ? ['dir' => 'ltr'] : [],
                    merge: true,
                )
                ->mask(fn (TextInput $component): ?RawJs => $component->isNumeric() ? RawJs::make('$money($input)') : null)
                ->stripCharacters(fn (TextInput $component): array => $component->isNumeric() ? [','] : []);
        });

        // Searchable selects open with (and search within) 10 options, and
        // relationship selects preload them; non-searchable ones keep 50 so
        // no option becomes unreachable.
        Select::configureUsing(function (Select $component): void {
            $component
                ->preload(fn (Select $component): bool => $component->isSearchable())
                ->optionsLimit(fn (Select $component): int => $component->isSearchable() ? 10 : 50);
        });
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
