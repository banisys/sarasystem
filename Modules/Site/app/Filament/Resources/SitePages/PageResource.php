<?php

namespace Modules\Site\Filament\Resources\SitePages;

use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Site\Filament\Resources\SitePages\Pages\CreatePage;
use Modules\Site\Filament\Resources\SitePages\Pages\EditPage;
use Modules\Site\Filament\Resources\SitePages\Pages\ListPages;
use Modules\Site\Filament\Resources\SitePages\Schemas\PageForm;
use Modules\Site\Filament\Resources\SitePages\Tables\PagesTable;
use Modules\Site\Models\Page;
use UnitEnum;

/**
 * Public pages of the tenants' sites (ADR 0010). The list shows the current
 * tenant's pages (BelongsToTenant scope); a new page can be made for any
 * tenant where the user may create pages.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    // Not Filament's tenant scoping: it would force new pages into the
    // current tenant. BelongsToTenant's scope limits the list instead.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'وب‌سایت';

    protected static ?string $slug = 'pages';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'صفحه';

    protected static ?string $pluralModelLabel = 'صفحه‌ها';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    /**
     * Tenants the user may create pages in: their panel tenants where they
     * hold Create:Page (permissions are per tenant).
     *
     * @return array<int, string> id => name
     */
    public static function creatableTenantOptions(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $options = $user->getTenants(Filament::getPanel('admin'))
            ->filter(fn (Tenant $tenant): bool => $tenant->execute(
                fn (): bool => $user->unsetRelation('roles')->unsetRelation('permissions')->can('Create:Page'),
            ))
            ->pluck('name', 'id')
            ->all();

        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $options;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
