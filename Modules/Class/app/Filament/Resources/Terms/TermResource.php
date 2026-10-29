<?php

namespace Modules\Class\Filament\Resources\Terms;

use App\Filament\Concerns\OnlyInPrimaryTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\Terms\Pages\ListTerms;
use Modules\Class\Filament\Resources\Terms\Schemas\TermForm;
use Modules\Class\Filament\Resources\Terms\Tables\TermsTable;
use Modules\Class\Models\Term;
use UnitEnum;

/**
 * The organization's terms, defined in the primary tenant (ADR 0022);
 * created and edited in a modal.
 */
class TermResource extends Resource
{
    use OnlyInPrimaryTenant;

    protected static ?string $model = Term::class;

    // BelongsToTenant's scope limits the list and new rows get the current
    // tenant; Filament's tenant scoping would need a relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'class-terms';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'دوره‌ی آموزشی';

    protected static ?string $pluralModelLabel = 'دوره‌های آموزشی';

    public static function form(Schema $schema): Schema
    {
        return TermForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TermsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerms::route('/'),
        ];
    }
}
