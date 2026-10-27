<?php

namespace Modules\Student\Filament\Resources\Preregistrations;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Student\Filament\Resources\Preregistrations\Pages\ListPreregistrations;
use Modules\Student\Filament\Resources\Preregistrations\Pages\ViewPreregistration;
use Modules\Student\Filament\Resources\Preregistrations\Schemas\PreregistrationInfolist;
use Modules\Student\Filament\Resources\Preregistrations\Tables\PreregistrationsTable;
use Modules\Student\Models\Preregistration;
use UnitEnum;

/**
 * Preregistrations from the public «پیش‌ثبت‌نام» form (ADR 0018); they are
 * reviewed in the admission workflow (cartable).
 */
class PreregistrationResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Preregistration::class;

    // See GradeResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'پیش‌ثبت‌نام';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'preregistrations';

    protected static ?string $modelLabel = 'متقاضی ثبت‌نام';

    protected static ?string $pluralModelLabel = 'متقاضیان ثبت‌نام';

    public static function infolist(Schema $schema): Schema
    {
        return PreregistrationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreregistrationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreregistrations::route('/'),
            'view' => ViewPreregistration::route('/{record}'),
        ];
    }
}
