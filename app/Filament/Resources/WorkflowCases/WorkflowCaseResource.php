<?php

namespace App\Filament\Resources\WorkflowCases;

use App\Filament\Resources\WorkflowCases\Pages\ListWorkflowCases;
use App\Filament\Resources\WorkflowCases\Pages\ViewWorkflowCase;
use App\Filament\Resources\WorkflowCases\Schemas\WorkflowCaseInfolist;
use App\Filament\Resources\WorkflowCases\Tables\WorkflowCasesTable;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowCase;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The cartable «کارتابل» (ADR 0016): cases whose current step is the
 * user's in the current tenant, whichever tenant owns them. Access comes
 * from the step assignment, not from permissions (WorkflowCasePolicy;
 * excluded from Shield).
 */
class WorkflowCaseResource extends Resource
{
    protected static ?string $model = WorkflowCase::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'کارتابل';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'cartable';

    protected static ?string $navigationLabel = 'کارتابل من';

    protected static ?string $modelLabel = 'پرونده';

    protected static ?string $pluralModelLabel = 'کارتابل من';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return WorkflowCase::query()->whereRaw('1 = 0');
        }

        return WorkflowCase::assignedTo($user, $tenant)->with('subject');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkflowCaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowCasesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowCases::route('/'),
            'view' => ViewWorkflowCase::route('/{record}'),
        ];
    }
}
