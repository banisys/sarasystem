<?php

namespace App\Filament\Resources\Workflows;

use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Filament\Resources\Workflows\Pages\EditWorkflow;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Filament\Resources\Workflows\Pages\ViewWorkflow;
use App\Filament\Resources\Workflows\Schemas\WorkflowForm;
use App\Filament\Resources\Workflows\Tables\WorkflowsTable;
use App\Filament\Support\IndependentOfTenant;
use App\Models\Workflow;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Installation-wide workflow definitions (ADR 0016); cases and step
 * assignments still belong to their respective tenants.
 */
class WorkflowResource extends Resource implements IndependentOfTenant
{
    protected static ?string $model = Workflow::class;

    // Definitions are shared by every tenant in this installation.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'کارتابل';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'workflows';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'فرایند';

    protected static ?string $pluralModelLabel = 'فرایندها';

    /**
     * @var array<string, Closure(): iterable<Action>>
     */
    protected static array $viewPageActionFactories = [];

    /**
     * Let a module add buttons to the header of a workflow's page, e.g. its 3D
     * view (ADR 0064). Registering a name again replaces it.
     *
     * @param  Closure(): iterable<Action>  $callback
     */
    public static function addViewPageActionsUsing(string $name, Closure $callback): void
    {
        static::$viewPageActionFactories[$name] = $callback;
    }

    /**
     * @return list<Action>
     */
    public static function moduleViewPageActions(): array
    {
        return collect(static::$viewPageActionFactories)->flatMap(fn (Closure $callback): iterable => $callback())->values()->all();
    }

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
            'view' => ViewWorkflow::route('/{record}'),
            'edit' => EditWorkflow::route('/{record}/edit'),
        ];
    }
}
