<?php

namespace Modules\Exam\Filament\Resources\Exams\Tables;

use App\Filament\Support\TextFilter;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Models\Exam;
use Modules\Exam\Services\ExamPaperPreview;
use Modules\Exam\Support\ExamContexts;

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
                    ->description(fn (Exam $record): ?string => $record->is_practice ? 'آزمون تمرینی' : null)
                    ->sortable(),
                TextColumn::make('examGroup.title')
                    ->label('گروه آزمون')
                    ->placeholder('بدون گروه'),
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
                    ->state(fn (Exam $record): int => $record->question_snapshot === null
                        ? (int) $record->sections_sum_question_count
                        : count($record->question_snapshot))
                    ->placeholder('0'),
                TextColumn::make('participants')
                    ->label('شرکت‌کنندگان')
                    ->state(fn (Exam $record): string => $record->context_type !== null ? 'اعضای جلسه' : "{$record->participant_users_count} کاربر، {$record->participant_roles_count} نقش"),
                TextColumn::make('attempts_count')
                    ->label('تعداد شرکت')
                    ->sortable(),
            ])
            ->filters([
                TextFilter::make('title')->label('عنوان'),
                SelectFilter::make('examGroup')
                    ->label('گروه آزمون')
                    ->relationship('examGroup', 'title')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_published')->label('منتشر شده'),
            ])
            ->recordActions([
                Action::make('previewPaper')
                    ->label('پیش‌نمایش آزمون')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->authorize('view')
                    ->modalHeading('پیش‌نمایش آزمون')
                    ->modalWidth('6xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن')
                    ->modalContent(function (Exam $record, ExamPaperPreview $preview, ExamContexts $contexts): View {
                        $actor = auth()->user();
                        $context = $actor instanceof User && $record->context_type !== null && $record->context_id !== null
                            ? $contexts->resolve($record->context_type, $record->context_id, $actor)
                            : null;

                        return $preview->savedModal($record, $context?->classTitle, $context?->startsAt);
                    }),
                Action::make('results')
                    ->label('نتایج')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->url(fn (Exam $record): string => ExamAttemptResource::urlForExam($record))
                    ->visible(fn (Exam $record): bool => ! $record->is_practice && ExamAttemptResource::canViewAny()),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('آزمون با همه‌ی نتایج و پاسخ‌های شرکت‌کنندگان حذف می‌شود.'),
            ]);
    }
}
