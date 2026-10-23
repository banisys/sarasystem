<?php

namespace Modules\Site\Filament\Resources\SiteMenus\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Site\Filament\Resources\SiteMenus\MenuResource;
use Modules\Site\Models\Menu;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('تعداد آیتم‌ها')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
            ])
            ->recordActions([
                Action::make('items')
                    ->label('آیتم‌ها')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->url(fn (Menu $record): string => MenuResource::getUrl('items', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('منو با همه‌ی آیتم‌هایش حذف می‌شود و از سایت‌هایی که آن را نشان می‌دهند برداشته می‌شود.'),
            ]);
    }
}
