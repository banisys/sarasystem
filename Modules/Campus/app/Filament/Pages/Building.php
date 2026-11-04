<?php

namespace Modules\Campus\Filament\Pages;

use App\Campus\BuildingRooms;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $rooms = BuildingRooms::for($user);
        $counts = collect($rooms)->countBy('state');
        $states = [
            BuildingRooms::LIVE => ['label' => 'در حال برگزاری', 'color' => '#facc15'],
            BuildingRooms::TODAY => ['label' => 'جلسه‌ی بعدی امروز', 'color' => '#7dd3fc'],
            BuildingRooms::IDLE => ['label' => 'بدون جلسه‌ی دیگر امروز', 'color' => '#475569'],
        ];

        return [
            'rooms' => $rooms,
            'time' => now()->format('H:i'),
            'scene' => ['rooms' => $rooms, 'states' => $states],
            'legend' => collect($states)->map(fn (array $state, string $key): array => [...$state, 'count' => $counts[$key] ?? 0])->all(),
        ];
    }
}
