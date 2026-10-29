<?php

namespace Modules\Class\Filament\Resources\Subjects\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Class\Models\Subject;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['schedules', 'sessions']))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان'),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('schedules_count')
                    ->label('تعداد برنامه‌ها'),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Subject $record): bool => $record->schedules_count === 0 && $record->sessions_count === 0)
                    ->modalDescription('درسی که در برنامه یا جلسه‌ای آمده حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
