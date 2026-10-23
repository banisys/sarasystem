<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\ManageTenants;
use App\Filament\Support\TextFilter;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Installation-level list of tenants; super admin only (TenantPolicy).
 * Not scoped to the panel's current tenant.
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return config('sarasystem.tenant_label');
    }

    public static function getPluralModelLabel(): string
    {
        return config('sarasystem.tenant_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('شناسه در آدرس')
                    ->helperText('فقط حروف کوچک انگلیسی، عدد و خط تیره؛ مثلاً marzdaran-1')
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique(ignoreRecord: true)
                    ->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText('غیرفعال کردن جای حذف: دسترسی همه به آن بسته می‌شود ولی داده‌ها می‌مانند.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('شناسه در آدرس'),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TextFilter::make('name')->label('نام'),
                TextFilter::make('slug')->label('شناسه در آدرس'),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTenants::route('/'),
        ];
    }
}
