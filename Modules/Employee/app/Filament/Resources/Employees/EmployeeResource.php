<?php

namespace Modules\Employee\Filament\Resources\Employees;

use App\Filament\Support\ModelRelationManagers;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Filament\Resources\Employees\Pages\ViewEmployee;
use Modules\Employee\Filament\Resources\Employees\Schemas\EmployeeForm;
use Modules\Employee\Filament\Resources\Employees\Schemas\EmployeeInfolist;
use Modules\Employee\Filament\Resources\Employees\Tables\EmployeesTable;
use Modules\Employee\Models\Employee;
use UnitEnum;

/**
 * Employees of the current tenant (ADR 0016). They are created by hiring
 * workflows, or quickly with «ثبت سریع کارمند» / «ورود از فایل» (ADR 0038)
 * on the list; employment details are edited in a modal. Their page has tabs
 * other modules add, e.g. contracts and documents (ModelRelationManagers).
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    // See JobPositionResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'کارکنان';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'employees';

    protected static ?string $modelLabel = 'کارمند';

    protected static ?string $pluralModelLabel = 'کارمندان';

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return ModelRelationManagers::for(Employee::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'view' => ViewEmployee::route('/{record}'),
        ];
    }
}
