<?php

namespace Modules\Student\Filament\Resources\Students;

use App\Filament\Support\ModelRelationManagers;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Student\Filament\Resources\Students\Pages\ListStudents;
use Modules\Student\Filament\Resources\Students\Pages\ViewStudent;
use Modules\Student\Filament\Resources\Students\Schemas\StudentForm;
use Modules\Student\Filament\Resources\Students\Schemas\StudentInfolist;
use Modules\Student\Filament\Resources\Students\Tables\StudentsTable;
use Modules\Student\Models\Student;

/**
 * Learners of the current tenant (ADR 0018), called as the installation
 * says (`sarasystem.learner_label`: دانش‌آموز، دانشجو، هنرآموز، …). They
 * are created by admission workflows; their membership is edited in a modal.
 * Their page has tabs other modules add, e.g. contracts and documents
 * (ModelRelationManagers).
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    // See ProgramResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'students';

    public static function getNavigationGroup(): ?string
    {
        return config('sarasystem.learner_plural_label');
    }

    public static function getModelLabel(): string
    {
        return config('sarasystem.learner_label');
    }

    public static function getPluralModelLabel(): string
    {
        return config('sarasystem.learner_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return ModelRelationManagers::for(Student::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'view' => ViewStudent::route('/{record}'),
        ];
    }
}
