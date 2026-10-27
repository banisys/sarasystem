<?php

namespace Modules\Student\Filament\Resources\Students;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Student\Filament\Resources\Students\Pages\ListStudents;
use Modules\Student\Filament\Resources\Students\Schemas\StudentForm;
use Modules\Student\Filament\Resources\Students\Tables\StudentsTable;
use Modules\Student\Models\Student;
use UnitEnum;

/**
 * Students of the current tenant (ADR 0018). They are created by admission
 * workflows; enrollment details are edited in a modal.
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    // See GradeResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'دانش‌آموزان';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'students';

    protected static ?string $modelLabel = 'دانش‌آموز';

    protected static ?string $pluralModelLabel = 'دانش‌آموزان';

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
        ];
    }
}
