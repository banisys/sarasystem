<?php

namespace Modules\Student\Filament\Resources\Programs;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Student\Filament\Resources\Programs\Pages\ListPrograms;
use Modules\Student\Filament\Resources\Programs\Schemas\ProgramForm;
use Modules\Student\Filament\Resources\Programs\Tables\ProgramsTable;
use Modules\Student\Models\Program;
use UnitEnum;

/**
 * The programs offered in the «پیش‌ثبت‌نام» form (ADR 0018); created and
 * edited in a modal.
 */
class ProgramResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Program::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'پیش‌ثبت‌نام';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'programs';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'برنامه‌ی آموزشی';

    protected static ?string $pluralModelLabel = 'برنامه‌های آموزشی';

    public static function form(Schema $schema): Schema
    {
        return ProgramForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrograms::route('/'),
        ];
    }
}
