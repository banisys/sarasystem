<?php

namespace Modules\Document\Filament\Resources\Documents;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Document\Filament\Resources\Documents\Pages\ListDocuments;
use Modules\Document\Filament\Resources\Documents\Tables\DocumentsTable;
use Modules\Document\Models\Document;
use UnitEnum;

/**
 * The documents of the current tenant's people (ADR 0019): applicants'
 * in the primary tenant, members' in their tenant. Uploading and checking
 * follow each type's roles.
 */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    // See DocumentTypeResource.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'مدارک';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'documents';

    protected static ?string $modelLabel = 'مدرک';

    protected static ?string $pluralModelLabel = 'مدارک';

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
        ];
    }
}
