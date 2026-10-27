<?php

namespace Modules\Employee\Filament\Resources\JobApplications;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Employee\Filament\Resources\JobApplications\Pages\ListJobApplications;
use Modules\Employee\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use Modules\Employee\Filament\Resources\JobApplications\Schemas\JobApplicationInfolist;
use Modules\Employee\Filament\Resources\JobApplications\Tables\JobApplicationsTable;
use Modules\Employee\Models\JobApplication;
use UnitEnum;

/**
 * Applications from the «همکاری با ما» form (ADR 0014). Read-only for now;
 * the review steps come later.
 */
class JobApplicationResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = JobApplication::class;

    // See JobPositionResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'همکاری با ما';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'job-applications';

    protected static ?string $modelLabel = 'متقاضی همکاری';

    protected static ?string $pluralModelLabel = 'متقاضیان همکاری';

    public static function infolist(Schema $schema): Schema
    {
        return JobApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobApplicationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobApplications::route('/'),
            'view' => ViewJobApplication::route('/{record}'),
        ];
    }
}
