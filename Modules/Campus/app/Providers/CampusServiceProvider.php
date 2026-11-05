<?php

namespace Modules\Campus\Providers;

use App\Campus\StaffPeople;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Support\PersonListActions;
use App\Filament\Support\PersonRecordActions;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\View\View;
use Modules\Campus\Filament\Pages\Building;
use Modules\Campus\Filament\Pages\OrganizationMap;
use Modules\Campus\Services\RoleHierarchy;
use Modules\Campus\Services\StaffHierarchy;
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
                ->modalContent(fn (): View => view('campus::filament.modals.power-pyramid', [
                    'scene' => app(RoleHierarchy::class)->scene(),
                    'forPeople' => false,
                    'namesOn' => true,
                    'note' => 'فقط نقش‌هایی که در پنل مدیریت مجوز دارند نشان داده می‌شوند. جایگاه از خود مجوزها به دست می‌آید: نقشی بالاتر است که همه‌ی مجوزهای نقش پایین‌تر را داشته باشد و بیشتر هم داشته باشد. دو نقش در یک طبقه لزوماً هم‌تراز نیستند. کلیک روی ستون: جزئیات و بالادست و زیردست',
                ])),
        ]);

        PersonListActions::register(PersonRecordActions::STAFF, 'power-pyramid', fn (): array => [
            Action::make('staffPyramid')
                ->label('هرم قدرت')
                ->icon(Heroicon::OutlinedCube)
                ->color('gray')
                ->visible(fn (): bool => ($user = auth()->user()) instanceof User && StaffPeople::canView($user))
                ->modalHeading(fn (): string => 'هرم قدرت کارکنان '.Tenant::current()->name)
                ->modalWidth(Width::Screen)
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalContent(fn (): View => view('campus::filament.modals.power-pyramid', [
                    'scene' => $scene = app(StaffHierarchy::class)->scene(auth()->user()),
                    'forPeople' => true,
                    // Names over very many figures only hide each other; hovering still shows them.
                    'namesOn' => collect($scene['roles'])->sum('holders') <= 40,
                    'emptyMessage' => 'هنوز کارمند فعالی در این '.config('sarasystem.tenant_label').' نیست.',
                    'note' => 'هر آدمک یک کارمند است. جایگاهش از مجوزهایش در همین '.config('sarasystem.tenant_label').' به دست می‌آید، نه از چارت سازمانی: کسی بالاتر است که همه‌ی مجوزهای پایین‌تری را داشته باشد و بیشتر هم داشته باشد. افراد با مجوز یکسان کنار هم‌اند، و دو نفر در یک طبقه لزوماً هم‌قدرت نیستند. کسی که ورود به پنل ندارد خاکستری است. کلیک روی آدمک: جزئیات همان نفر',
                ])),
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
