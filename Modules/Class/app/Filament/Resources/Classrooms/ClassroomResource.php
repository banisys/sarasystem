<?php

namespace Modules\Class\Filament\Resources\Classrooms;

use App\Filament\Support\ModelRelationManagers;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\Classrooms\Pages\ListClassrooms;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\MembersRelationManager;
use Modules\Class\Filament\Resources\Classrooms\Schemas\ClassroomForm;
use Modules\Class\Filament\Resources\Classrooms\Schemas\ClassroomInfolist;
use Modules\Class\Filament\Resources\Classrooms\Tables\ClassroomsTable;
use Modules\Class\Models\Classroom;
use UnitEnum;

/**
 * Classes of the current tenant (ADR 0022); created and edited in a modal.
 * A class's page has its members and the tabs other modules add
 * (ModelRelationManagers).
 */
class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    // See TermResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'classes';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'کلاس';

    protected static ?string $pluralModelLabel = 'کلاس‌ها';

    public static function form(Schema $schema): Schema
    {
        return ClassroomForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassroomInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassroomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            ...ModelRelationManagers::for(Classroom::class),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassrooms::route('/'),
            'view' => ViewClassroom::route('/{record}'),
        ];
    }
}
