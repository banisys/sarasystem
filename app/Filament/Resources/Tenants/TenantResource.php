<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\ManageTenants;
use App\Filament\Support\TextFilter;
use App\Models\Tenant;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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

    /**
     * @var array<string, Closure(): iterable<Action>>
     */
    protected static array $recordActionFactories = [];

    /**
     * Let a module add row actions to the tenants list, e.g. choosing the
     * tenant's site menu (ADR 0012). The core never depends on a module.
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  Closure(): iterable<Action>  $callback
     */
    public static function addRecordActionsUsing(string $name, Closure $callback): void
    {
        static::$recordActionFactories[$name] = $callback;
    }

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
                    // /{slug} is the tenant's home page and must not hide another route.
                    ->notIn(fn (): array => Tenant::reservedSlugs())
                    ->validationMessages(['not_in' => 'این شناسه برای بخش دیگری از سامانه رزرو است.'])
                    ->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText(fn (?Tenant $record): string => $record?->is_primary
                        ? 'اصلی را نمی‌توان غیرفعال کرد؛ اول یکی دیگر را اصلی کنید.'
                        : 'غیرفعال کردن جای حذف: دسترسی همه به آن بسته می‌شود ولی داده‌ها می‌مانند.')
                    ->disabled(fn (?Tenant $record): bool => (bool) $record?->is_primary)
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
                IconColumn::make('is_primary')
                    ->label('اصلی')
                    ->tooltip('صفحه‌ی خانه‌ی اصلی، آدرس اصلی سایت (/) است.')
                    ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::Star : null)
                    ->color('warning'),
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
                Action::make('home')
                    ->label('صفحه‌ی خانه')
                    ->icon(Heroicon::OutlinedHome)
                    ->url(fn (Tenant $record): string => $record->homeUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Tenant $record): bool => $record->is_active),
                Action::make('makePrimary')
                    ->label('تعیین به‌عنوان اصلی')
                    ->icon(Heroicon::OutlinedStar)
                    ->requiresConfirmation()
                    ->modalDescription('صفحه‌ی خانه‌ی این مورد در آدرس اصلی سایت (/) نمایش داده می‌شود.')
                    ->visible(fn (Tenant $record): bool => $record->is_active && ! $record->is_primary)
                    ->action(function (Tenant $record): void {
                        $record->makePrimary();

                        Notification::make()->title("«{$record->name}» اصلی شد.")->success()->send();
                    }),
                ...collect(static::$recordActionFactories)->flatMap(fn (Closure $callback): iterable => $callback())->all(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTenants::route('/'),
        ];
    }
}
