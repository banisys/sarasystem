<?php

namespace Modules\Site\Filament\Resources\SitePages\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Site\Filament\Resources\SitePages\PageResource;
use Modules\Site\Models\Page;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tenant'))
            // The home page first, then the newest.
            ->defaultSort(fn ($query) => $query->orderByRaw('path = ? desc', [Page::HOME_PATH])->latest())
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->description(fn (Page $record): ?string => $record->isHome() ? 'صفحه‌ی خانه' : null)
                    ->sortable(),
                TextColumn::make('path')
                    ->label('آدرس')
                    ->sortable()
                    ->extraAttributes(['dir' => 'ltr']),
                IconColumn::make('is_published')
                    ->label('منتشر شده')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TextFilter::make('path')->label('آدرس'),
                TernaryFilter::make('is_published')->label('منتشر شده'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('builder')
                    ->label('صفحه‌ساز')
                    ->icon(Heroicon::OutlinedSquare2Stack)
                    ->url(fn (Page $record): string => PageResource::getUrl('builder', ['record' => $record])),
                Action::make('view')
                    ->label('مشاهده در سایت')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Page $record): bool => $record->is_published),
                DeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->isHome()),
            ]);
    }
}
