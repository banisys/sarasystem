<?php

namespace Modules\Class\Filament\Resources\Holidays;

use App\Models\Tenant;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Filament\Resources\Holidays\Pages\ListHolidays;
use Modules\Class\Filament\Resources\Holidays\Schemas\HolidayForm;
use Modules\Class\Filament\Resources\Holidays\Tables\HolidaysTable;
use Modules\Class\Models\Holiday;
use UnitEnum;

/**
 * Days without classes (ADR 0022). Each tenant lists its own and the
 * organization's (the primary tenant's) and edits its own only.
 */
class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    // See TermResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'class-holidays';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'تعطیلی';

    protected static ?string $pluralModelLabel = 'تعطیلات';

    /**
     * @return Builder<Holiday>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();

        return Holiday::applyingIn($tenant);
    }

    public static function isOwn(Holiday $holiday): bool
    {
        return $holiday->tenant_id === Filament::getTenant()?->getKey();
    }

    public static function form(Schema $schema): Schema
    {
        return HolidayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HolidaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHolidays::route('/'),
        ];
    }
}
