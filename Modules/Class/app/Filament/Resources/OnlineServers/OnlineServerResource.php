<?php

namespace Modules\Class\Filament\Resources\OnlineServers;

use App\Filament\Support\IndependentOfTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Class\Filament\Resources\OnlineServers\Pages\ListOnlineServers;
use Modules\Class\Filament\Resources\OnlineServers\Schemas\OnlineServerForm;
use Modules\Class\Filament\Resources\OnlineServers\Tables\OnlineServersTable;
use Modules\Class\Models\OnlineServer;
use UnitEnum;

/**
 * The installation's servers of online-class platforms, e.g. Adobe Connect
 * (ADR 0073); shared by every tenant, created and edited in a modal.
 */
class OnlineServerResource extends Resource implements IndependentOfTenant
{
    protected static ?string $model = OnlineServer::class;

    // Shared by every tenant in this installation.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'کلاس‌ها';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'online-servers';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'سرور کلاس آنلاین';

    protected static ?string $pluralModelLabel = 'سرورهای کلاس آنلاین';

    public static function form(Schema $schema): Schema
    {
        return OnlineServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnlineServersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnlineServers::route('/'),
        ];
    }
}
