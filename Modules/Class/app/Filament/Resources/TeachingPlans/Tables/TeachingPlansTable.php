<?php

namespace Modules\Class\Filament\Resources\TeachingPlans\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Class\Models\TeachingPlan;

class TeachingPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('subject')->withCount(['topics', 'schedules'])->withSum('topics', 'sessions'))
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('subject.title')
                    ->label('درس')
                    ->placeholder('همه'),
                TextColumn::make('topics_count')
                    ->label('سرفصل‌ها'),
                TextColumn::make('topics_sum_sessions')
                    ->label('جلسه‌ها')
                    ->placeholder('0'),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('schedules_count')
                    ->label('برنامه‌ها'),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('4xl'),
                DeleteAction::make()
                    ->visible(fn (TeachingPlan $record): bool => $record->schedules_count === 0)
                    ->modalDescription('طرحی که برنامه‌ای از آن پیروی می‌کند حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
