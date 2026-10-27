<?php

namespace App\Filament\Resources\Workflows;

use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Filament\Resources\Workflows\Pages\EditWorkflow;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Filament\Resources\Workflows\Schemas\WorkflowForm;
use App\Filament\Resources\Workflows\Tables\WorkflowsTable;
use App\Models\Workflow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The workflow designer «فرایندها» (ADR 0016): workflows of the current
 * tenant, each an ordered list of steps.
 */
class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'کارتابل';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'workflows';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'فرایند';

    protected static ?string $pluralModelLabel = 'فرایندها';

    public static function form(Schema $schema): Schema
    {
        return WorkflowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflows::route('/'),
            'create' => CreateWorkflow::route('/create'),
            'edit' => EditWorkflow::route('/{record}/edit'),
        ];
    }
}
