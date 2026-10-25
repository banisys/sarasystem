<?php

namespace Modules\Employee\Filament\Resources\JobPositions\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class JobPositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('applications'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('در فرم')
                    ->boolean(),
                TextColumn::make('applications_count')
                    ->label('تعداد درخواست‌ها')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('در فرم'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('درخواست‌هایی که برای این موقعیت ثبت شده‌اند حذف نمی‌شوند و عنوان شغل در آن‌ها می‌ماند.'),
            ]);
    }
}
