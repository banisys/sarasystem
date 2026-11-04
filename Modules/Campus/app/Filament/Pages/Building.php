<?php

namespace Modules\Campus\Filament\Pages;

use App\Campus\BuildingRooms;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Renderless;
use UnitEnum;

/**
 * The current tenant as a 3D building (ADR 0051): a window per room, lit when
 * something is happening there now. Whoever may see the rooms may open it.
 */
class Building extends Page
{
    protected string $view = 'campus::filament.pages.building';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?string $navigationLabel = 'ساختمان سه‌بعدی';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'building';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return ($user = auth()->user()) instanceof User && BuildingRooms::canView($user);
    }

    public function getTitle(): string
    {
        return 'ساختمان '.Tenant::current()->name;
    }

    /**
     * What a window's colour means.
     *
     * @return array<string, array{label: string, color: string}>
     */
    public static function states(): array
    {
        return [
            BuildingRooms::LIVE => ['label' => 'در حال برگزاری', 'color' => '#facc15'],
            BuildingRooms::TODAY => ['label' => 'جلسه‌ی بعدی امروز', 'color' => '#7dd3fc'],
            BuildingRooms::IDLE => ['label' => 'بدون جلسه‌ی دیگر امروز', 'color' => '#475569'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('organizationMap')
                ->label(OrganizationMap::getNavigationLabel())
                ->icon(Heroicon::OutlinedMap)
                ->color('gray')
                ->url(OrganizationMap::getUrl()),
        ];
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
        $rooms = BuildingRooms::for($user);
        $counts = collect($rooms)->countBy('state');

        return [
            'rooms' => $rooms,
            'states' => self::states(),
            'time' => now()->format('H:i'),
            'legend' => collect(self::states())->map(fn (array $state, string $key): array => [...$state, 'count' => $counts[$key] ?? 0])->all(),
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
