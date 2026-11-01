<?php

namespace App\Filament\Resources\AllWorkflowCases;

use App\Filament\Resources\AllWorkflowCases\Pages\ListAllWorkflowCases;
use App\Filament\Resources\AllWorkflowCases\Pages\ViewAllWorkflowCase;
use App\Filament\Resources\AllWorkflowCases\Schemas\AllWorkflowCaseInfolist;
use App\Filament\Resources\AllWorkflowCases\Tables\AllWorkflowCasesTable;
use App\Models\WorkflowCase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * «همه‌ی پرونده‌ها» (ADR 0043): every case of the current tenant, in
 * progress or finished, read only. Unlike the cartable, access comes from
 * the `ViewAny:WorkflowCase` / `View:WorkflowCase` permissions; the
 * cartable's WorkflowCasePolicy is not used here.
 */
class AllWorkflowCasesResource extends Resource
{
    protected static ?string $model = WorkflowCase::class;

    // WorkflowCase's TenantScope keeps the current tenant's cases.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'کارتابل';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'workflow-cases';

    protected static ?string $navigationLabel = 'همه‌ی پرونده‌ها';

    protected static ?string $modelLabel = 'پرونده';

    protected static ?string $pluralModelLabel = 'همه‌ی پرونده‌ها';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['subject', 'assigneeUser', 'assigneeRole', 'assigneeTenant', 'claimedBy']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('ViewAny:WorkflowCase') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('View:WorkflowCase') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AllWorkflowCaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AllWorkflowCasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllWorkflowCases::route('/'),
            'view' => ViewAllWorkflowCase::route('/{record}'),
        ];
    }
}
