<?php

namespace Modules\Campus\Filament\Pages;

use App\Filament\Support\IndependentOfTenant;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Renderless;
use Modules\Campus\Services\OrganizationSites;
use UnitEnum;

/**
 * All the tenants a user sees as a town of 3D buildings (ADR 0052). It spans
 * tenants, so it doesn't depend on the current one.
 */
class OrganizationMap extends Page implements IndependentOfTenant
{
    protected string $view = 'campus::filament.pages.organization-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'organization-map';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function getNavigationLabel(): string
    {
        return 'نقشه‌ی '.config('sarasystem.tenant_plural_label');
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        return ($user = auth()->user()) instanceof User && app(OrganizationSites::class)->canSeeAny($user);
    }

    /**
     * The windows' state now, for the page to update itself (ADR 0053).
     *
     * @return array<string, mixed>
     */
    #[Renderless]
    public function liveData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $sites = app(OrganizationSites::class)->for($user);

        return [
            'sites' => $sites,
            'states' => Building::states(),
            'time' => now()->format('H:i'),
            'totals' => [
                'rooms' => collect($sites)->sum(fn (array $site): int => count($site['rooms'])),
                'live' => collect($sites)->sum('live'),
                'people' => collect($sites)->sum('people'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['scene' => $this->liveData(), 'liveSeconds' => config('campus.live_seconds')];
    }
}
