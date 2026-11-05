<?php

namespace Modules\Campus\Providers;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\View\View;
use Modules\Campus\Filament\Pages\Building;
use Modules\Campus\Filament\Pages\OrganizationMap;
use Modules\Campus\Services\RoleHierarchy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CampusServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Campus';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'campus';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        RoleResource::addHeaderActionsUsing('role-pyramid', fn (): array => [
            Action::make('rolePyramid')
                ->label('هرم قدرت نقش‌ها')
                ->icon(Heroicon::OutlinedCube)
                ->color('gray')
                ->modalHeading('هرم قدرت نقش‌ها')
                ->modalWidth(Width::Screen)
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalContent(fn (): View => view('campus::filament.modals.role-pyramid', ['scene' => app(RoleHierarchy::class)->scene()])),
        ]);

        TenantResource::addRecordActionsUsing('building', fn (): array => [
            Action::make('building')
                ->label(Building::getNavigationLabel())
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->url(fn (Tenant $record): string => Building::getUrl(tenant: $record))
                ->visible(fn (): bool => Building::canAccess()),
        ]);

        TenantResource::addHeaderActionsUsing('organization-map', fn (): array => [
            Action::make('organizationMap')
                ->label(OrganizationMap::getNavigationLabel())
                ->icon(Heroicon::OutlinedMap)
                ->color('gray')
                ->url(fn (): string => OrganizationMap::getUrl())
                ->visible(fn (): bool => OrganizationMap::canAccess()),
        ]);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
