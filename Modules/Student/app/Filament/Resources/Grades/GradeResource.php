<?php

namespace Modules\Student\Filament\Resources\Grades;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Student\Filament\Resources\Grades\Pages\ListGrades;
use Modules\Student\Filament\Resources\Grades\Schemas\GradeForm;
use Modules\Student\Filament\Resources\Grades\Tables\GradesTable;
use Modules\Student\Models\Grade;
use UnitEnum;

/**
 * The stages and grades offered in the «پیش‌ثبت‌نام» form (ADR 0018);
 * created and edited in a modal.
 */
class GradeResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Grade::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'پیش‌ثبت‌نام';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'grades';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'مقطع / پایه';

    protected static ?string $pluralModelLabel = 'مقاطع و پایه‌ها';

    public static function form(Schema $schema): Schema
    {
        return GradeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GradesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrades::route('/'),
        ];
    }
}
