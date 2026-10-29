<?php

namespace Modules\Contract\Filament\Resources\ContractTypes;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Contract\Filament\Resources\ContractTypes\Pages\ListContractTypes;
use Modules\Contract\Filament\Resources\ContractTypes\Schemas\ContractTypeForm;
use Modules\Contract\Filament\Resources\ContractTypes\Tables\ContractTypesTable;
use Modules\Contract\Models\ContractType;
use UnitEnum;

/**
 * The kinds of contracts, defined once in the primary tenant for the whole
 * organization (ADR 0020); created and edited in a modal.
 */
class ContractTypeResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = ContractType::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'قراردادها';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'contract-types';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'نوع قرارداد';

    protected static ?string $pluralModelLabel = 'انواع قرارداد';

    public static function form(Schema $schema): Schema
    {
        return ContractTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractTypes::route('/'),
        ];
    }
}
