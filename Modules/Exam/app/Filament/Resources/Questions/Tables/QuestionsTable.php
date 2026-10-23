<?php

namespace Modules\Exam\Filament\Resources\Questions\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Exam\Enums\QuestionType;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('banks'))
            ->columns([
                TextColumn::make('body')
                    ->label('متن سؤال')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('type')
                    ->label('نوع')
                    ->badge(),
                TextColumn::make('banks.title')
                    ->label('بانک‌ها')
                    ->badge(),
                TextColumn::make('score')
                    ->label('بارم')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('body')->label('متن سؤال'),
                SelectFilter::make('bank')
                    ->label('بانک')
                    ->relationship('banks', 'title'),
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(QuestionType::class),
                TernaryFilter::make('is_active')
                    ->label('فعال'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('سؤال از همه‌ی بانک‌ها حذف می‌شود. آزمون‌هایی که قبلاً داده شده‌اند، متن سؤال را نگه می‌دارند.'),
            ]);
    }
}
