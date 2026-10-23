<?php

namespace Modules\Exam\Filament\Resources\Exams\Tables;

use App\Filament\Support\TextFilter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Models\Exam;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withSum('sections', 'question_count')
                ->withCount(['attempts', 'participantUsers', 'participantRoles']))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('منتشر شده')
                    ->boolean(),
                TextColumn::make('starts_at')
                    ->label('شروع')
                    ->jalaliDateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('پایان')
                    ->jalaliDateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('sections_sum_question_count')
                    ->label('تعداد سؤال')
                    ->placeholder('0'),
                TextColumn::make('participants')
                    ->label('شرکت‌کنندگان')
                    ->state(fn (Exam $record): string => "{$record->participant_users_count} کاربر، {$record->participant_roles_count} نقش"),
                TextColumn::make('attempts_count')
                    ->label('تعداد شرکت')
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                TernaryFilter::make('is_published')->label('منتشر شده'),
            ])
            ->recordActions([
                Action::make('results')
                    ->label('نتایج')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->url(fn (Exam $record): string => ExamAttemptResource::urlForExam($record))
                    ->visible(fn (): bool => ExamAttemptResource::canViewAny()),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('آزمون با همه‌ی نتایج و پاسخ‌های شرکت‌کنندگان حذف می‌شود.'),
            ]);
    }
}
