<?php

namespace Modules\Campus\Providers;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Campus\Filament\Pages\OrganizationMap;
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
