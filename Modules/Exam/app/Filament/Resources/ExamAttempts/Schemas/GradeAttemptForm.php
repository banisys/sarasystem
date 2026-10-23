<?php

namespace Modules\Exam\Filament\Resources\ExamAttempts\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\ExamAttempt;

/**
 * The attempt and its answers. Only the score of written answers can be
 * changed; choice answers were graded on submit.
 */
class GradeAttemptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('شرکت‌کننده'),
                        TextEntry::make('exam.title')
                            ->label('آزمون'),
                        TextEntry::make('status')
                            ->label('وضعیت')
                            ->badge(),
                        TextEntry::make('score')
                            ->label('نمره')
                            ->state(fn (ExamAttempt $record): ?string => $record->score === null ? null : "{$record->score} از {$record->exam->total_score}")
                            ->placeholder('هنوز تصحیح نشده'),
                    ]),
                Repeater::make('answers')
                    ->label('پاسخ‌ها')
                    ->relationship()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->disabled(fn (ExamAttempt $record): bool => $record->isInProgress())
                    ->itemLabel('سؤال')
                    ->itemNumbers()
                    ->schema([
                        TextEntry::make('question_body')
                            ->label('سؤال')
                            ->columnSpanFull(),
                        TextEntry::make('selected_option')
                            ->label('پاسخ')
                            ->visible(fn (AttemptAnswer $record): bool => $record->isChoice())
                            ->state(fn (AttemptAnswer $record): ?string => $record->optionText($record->selected_option))
                            ->color(fn (AttemptAnswer $record): string => $record->selected_option === $record->correct_option ? 'success' : 'danger')
                            ->placeholder('بی‌پاسخ'),
                        TextEntry::make('correct_option')
                            ->label('پاسخ درست')
                            ->visible(fn (AttemptAnswer $record): bool => $record->isChoice())
                            ->state(fn (AttemptAnswer $record): ?string => $record->optionText($record->correct_option)),
                        TextEntry::make('text_answer')
                            ->label('پاسخ')
                            ->visible(fn (AttemptAnswer $record): bool => ! $record->isChoice())
                            ->placeholder('بی‌پاسخ')
                            ->columnSpanFull(),
                        TextInput::make('score')
                            ->label(fn (AttemptAnswer $record): string => "نمره (از {$record->max_score})")
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (AttemptAnswer $record): float => $record->max_score)
                            ->disabled(fn (AttemptAnswer $record): bool => $record->isChoice())
                            ->placeholder('تصحیح نشده'),
                    ])
                    ->columns(2)
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, AttemptAnswer $record): array => self::gradeData($data, $record))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Keep only the score of a written answer, and note who graded it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function gradeData(array $data, AttemptAnswer $record): array
    {
        if ($record->isChoice() || ! array_key_exists('score', $data)) {
            return [];
        }

        $score = filled($data['score']) ? (float) $data['score'] : null;

        if ($score === $record->score) {
            return [];
        }

        return [
            'score' => $score,
            'graded_by' => $score === null ? null : auth()->id(),
            'graded_at' => $score === null ? null : now(),
        ];
    }
}
