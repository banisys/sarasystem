<?php

namespace Modules\Site\Filament\Actions;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Site\Models\Menu;
use Modules\Site\Models\TenantMenu;

/**
 * Row action of the tenants list (added by SiteServiceProvider): the menu
 * shown in the tenant's site header, out of every tenant's menus (ADR 0012).
 */
class ChooseTenantMenuAction
{
    public static function make(): Action
    {
        return Action::make('siteMenu')
            ->label('منوی سایت')
            ->icon(Heroicon::OutlinedBars3)
            ->modalHeading(fn (Tenant $record): string => "منوی سایت «{$record->name}»")
            ->modalWidth('md')
            ->fillForm(fn (Tenant $record): array => ['menu_id' => TenantMenu::menuOf($record)?->getKey()])
            ->schema([
                Select::make('menu_id')
                    ->label('منو')
                    ->placeholder('بدون منو')
                    ->options(fn (): array => self::menuOptions())
                    ->searchable()
                    ->helperText('در بالای همه‌ی صفحه‌های سایت نمایش داده می‌شود. منوها در «وب‌سایت → منوها» ساخته می‌شوند.'),
            ])
            ->action(function (array $data, Tenant $record): void {
                TenantMenu::assign($record, filled($data['menu_id'] ?? null) ? (int) $data['menu_id'] : null);

                Notification::make()->title('منوی سایت ذخیره شد.')->success()->send();
            });
    }

    /**
     * Every tenant's menus, with the tenant's name.
     *
     * @return array<int, string>
     */
    private static function menuOptions(): array
    {
        return Menu::withoutTenantScope()->with('tenant')->orderBy('title')->get()
            ->mapWithKeys(fn (Menu $menu): array => [$menu->id => "{$menu->title} ({$menu->tenant->name})"])
            ->all();
    }
}
