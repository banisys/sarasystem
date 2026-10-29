<?php

namespace Modules\Class\Filament\Resources\Terms\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Class\Models\Term;

class TermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('classrooms'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان'),
                TextColumn::make('starts_on')
                    ->label('شروع')
                    ->jalaliDate()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('پایان')
                    ->jalaliDate()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('classrooms_count')
                    ->label('تعداد کلاس‌ها'),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_active')->label('فعال'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Term $record): bool => $record->classrooms_count === 0)
                    ->modalDescription('دوره‌ای که کلاسی در آن هست حذف نمی‌شود؛ آن را غیرفعال کنید.'),
            ]);
    }
}
