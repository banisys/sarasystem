<?php

namespace App\Filament\Pages;

use App\Features\SystemMap as MapBuilder;
use App\Filament\Support\IndependentOfTenant;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SystemMap extends Page implements IndependentOfTenant
{
    protected string $view = 'filament.pages.system-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 94;

    protected static ?string $title = 'نقشهٔ سامانه';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(MapBuilder::class)->build();
    }
}
