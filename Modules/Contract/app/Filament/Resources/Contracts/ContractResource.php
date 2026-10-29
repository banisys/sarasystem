<?php

namespace Modules\Contract\Filament\Resources\Contracts;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Contract\Filament\Resources\Contracts\Pages\ListContracts;
use Modules\Contract\Filament\Resources\Contracts\Tables\ContractsTable;
use Modules\Contract\Models\Contract;
use UnitEnum;

/**
 * The contracts of the current tenant's people (ADR 0020): drafts
 * prepared in workflows for applicants (primary tenant) and contracts of
 * members, prepared, issued and signed here.
 */
class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    // See ContractTypeResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'قراردادها';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'contracts';

    protected static ?string $modelLabel = 'قرارداد';

    protected static ?string $pluralModelLabel = 'قراردادها';

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
        ];
    }
}
