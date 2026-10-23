<?php

namespace Modules\Exam\Filament\Resources\QuestionBanks\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\Questions\QuestionResource;
use Modules\Exam\Models\QuestionBank;

class QuestionBanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('questions'))
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                TextColumn::make('questions_count')
                    ->label('تعداد سؤال‌ها')
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
                Action::make('questions')
                    ->label('سؤال‌ها')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->url(fn (QuestionBank $record): string => QuestionResource::getUrl('index', ['filters' => ['bank' => ['value' => $record->id]]])),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('سؤال‌ها حذف نمی‌شوند، فقط از این بانک برداشته می‌شوند. آزمون‌هایی که از این بانک سؤال می‌گیرند این بخش را از دست می‌دهند.'),
            ]);
    }
}
