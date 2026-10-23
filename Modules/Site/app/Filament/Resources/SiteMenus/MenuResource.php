<?php

namespace Modules\Site\Filament\Resources\SiteMenus;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Site\Filament\Resources\SiteMenus\Pages\ListMenus;
use Modules\Site\Filament\Resources\SiteMenus\Pages\MenuItems;
use Modules\Site\Filament\Resources\SiteMenus\Schemas\MenuForm;
use Modules\Site\Filament\Resources\SiteMenus\Tables\MenusTable;
use Modules\Site\Models\Menu;
use UnitEnum;

/**
 * Menus of the current tenant's site (ADR 0012): the list, and a tree page
 * per menu where its items are added, edited and dragged into place.
 */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    // As for pages: BelongsToTenant's scope limits the list and new menus
    // get the current tenant; Filament's tenant scoping would need a
    // relationship on Tenant.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'وب‌سایت';

    protected static ?string $slug = 'menus';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'منو';

    protected static ?string $pluralModelLabel = 'منوها';

    public static function form(Schema $schema): Schema
    {
        return MenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenusTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'items' => MenuItems::route('/{record}/items'),
        ];
    }
}
