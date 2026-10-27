<?php

namespace Modules\Document\Filament\Resources\DocumentTypes;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Document\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use Modules\Document\Filament\Resources\DocumentTypes\Schemas\DocumentTypeForm;
use Modules\Document\Filament\Resources\DocumentTypes\Tables\DocumentTypesTable;
use Modules\Document\Models\DocumentType;
use UnitEnum;

/**
 * The kinds of documents, defined once in the primary tenant for the whole
 * organization (ADR 0019); created and edited in a modal.
 */
class DocumentTypeResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = DocumentType::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'مدارک';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'document-types';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'نوع مدرک';

    protected static ?string $pluralModelLabel = 'انواع مدرک';

    public static function form(Schema $schema): Schema
    {
        return DocumentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTypes::route('/'),
        ];
    }
}
