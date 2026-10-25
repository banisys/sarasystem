<?php

namespace Modules\Employee\Filament\Resources\JobPositions;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Employee\Filament\Concerns\OnlyInPrimaryTenant;
use Modules\Employee\Filament\Resources\JobPositions\Pages\ListJobPositions;
use Modules\Employee\Filament\Resources\JobPositions\Schemas\JobPositionForm;
use Modules\Employee\Filament\Resources\JobPositions\Tables\JobPositionsTable;
use Modules\Employee\Models\JobPosition;
use UnitEnum;

/**
 * The jobs offered in the «همکاری با ما» form (ADR 0014); created and
 * edited in a modal.
 */
class JobPositionResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = JobPosition::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'همکاری با ما';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'job-positions';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'موقعیت شغلی';

    protected static ?string $pluralModelLabel = 'موقعیت‌های شغلی';

    public static function form(Schema $schema): Schema
    {
        return JobPositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobPositionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobPositions::route('/'),
        ];
    }
}
