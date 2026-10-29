<?php

namespace Modules\Class\Filament\Resources\ClassRoles;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\ClassRoles\Pages\ListClassRoles;
use Modules\Class\Filament\Resources\ClassRoles\Schemas\ClassRoleForm;
use Modules\Class\Filament\Resources\ClassRoles\Tables\ClassRolesTable;
use Modules\Class\Models\ClassRole;
use UnitEnum;

/**
 * What people can be in a class, defined in the primary tenant
 * (ADR 0022); created and edited in a modal.
 */
class ClassRoleResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = ClassRole::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'class-roles';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'نقش کلاس';

    protected static ?string $pluralModelLabel = 'نقش‌های کلاس';

    public static function form(Schema $schema): Schema
    {
        return ClassRoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassRolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassRoles::route('/'),
        ];
    }
}
