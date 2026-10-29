<?php

namespace Modules\Class\Filament\Resources\Holidays\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Filament\Resources\Holidays\HolidayResource;
use Modules\Class\Models\Holiday;

class HolidaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان'),
                TextColumn::make('starts_on')
                    ->label('از')
                    ->jalaliDate()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('تا')
                    ->jalaliDate(),
                TextColumn::make('scope')
                    ->label('برای')
                    ->badge()
                    ->state(fn (Holiday $record): string => $record->isOrganizationWide() ? 'کل سازمان' : 'این '.config('sarasystem.tenant_label'))
                    ->color(fn (Holiday $record): string => $record->isOrganizationWide() ? 'info' : 'gray'),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                Filter::make('upcoming')
                    ->label('پیش رو')
                    ->query(fn (Builder $query) => $query->whereDate('ends_on', '>=', today())),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Holiday $record): bool => HolidayResource::isOwn($record)),
                DeleteAction::make()
                    ->visible(fn (Holiday $record): bool => HolidayResource::isOwn($record)),
            ]);
    }
}
