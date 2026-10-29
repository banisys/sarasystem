<?php

namespace Modules\School\Filament\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Modules\Class\Models\Classroom;
use Modules\School\Enums\DescriptiveLevel;
use Modules\School\Enums\ScoreScale;
use Modules\School\Models\Curriculum;
use Modules\School\Models\ScorePeriod;
use Modules\School\Services\SchoolException;
use Modules\School\Services\ScoreBook;

/**
 * «دفتر نمره» on a class with a grade (ADR 0032): pick a score period and a
 * subject, then a score (or level) and note per learner. Added to classes
 * through ClassroomExtensions; ScoreBook decides who may.
 */
class ScoreBookAction
{
    public static function make(): Action
    {
        return Action::make('scoreBook')
            ->label('دفتر نمره')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Classroom $record): bool => self::mayOpen($record))
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('ذخیره‌ی نمره‌ها')
            ->schema(fn (Classroom $record, ScoreBook $book): array => [
                Select::make('period_id')
                    ->label('دوره‌ی نمره‌دهی')
                    ->options($book->periodsOf($record)->pluck('title', 'id')->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillRows($record, $get, $set)),
                Select::make('subject_id')
                    ->label('درس')
                    ->options($book->subjectsOf($record)->mapWithKeys(fn (Curriculum $item): array => [$item->subject_id => $item->subject->title])->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillRows($record, $get, $set)),
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->visible(fn (Get $get): bool => filled($get('period_id')) && filled($get('subject_id')))
                    ->schema([
                        Hidden::make('user_id'),
                        TextInput::make('name')->hiddenLabel()->disabled()->dehydrated(false)->columnSpan(4),
                        $book->scaleOf($record) === ScoreScale::Numeric
                            ? TextInput::make('score')->hiddenLabel()->placeholder('نمره از ۲۰')->numeric()->minValue(0)->maxValue(ScoreBook::MAX_SCORE)->step(0.25)->columnSpan(2)
                            : Select::make('level')->hiddenLabel()->placeholder('ارزشیابی')->options(DescriptiveLevel::class)->columnSpan(3),
                        TextInput::make('note')->hiddenLabel()->placeholder('توضیح')->maxLength(255)->columnSpan(5),
                    ]),
            ])
            ->action(function (array $data, Classroom $record, ScoreBook $book): void {
                /** @var User $user */
                $user = auth()->user();
                $rows = collect($data['rows'] ?? [])->keyBy(fn (array $row): int => (int) $row['user_id'])->all();

                try {
                    $book->record($record, ScorePeriod::definedPeriods()->findOrFail($data['period_id']), (int) $data['subject_id'], $rows, $user);
                } catch (SchoolException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('نمره‌ها ذخیره شد.')->success()->send();
            });
    }

    /**
     * The class has a grade, and the user may edit it or teaches one of its subjects.
     */
    private static function mayOpen(Classroom $classroom): bool
    {
        $user = auth()->user();
        $book = app(ScoreBook::class);

        if (! $user instanceof User || $book->subjectsOf($classroom)->isEmpty()) {
            return false;
        }

        return $user->can('update', $classroom)
            || $book->subjectsOf($classroom)->contains(fn (Curriculum $item): bool => $book->teaches($user, $classroom, $item->subject_id));
    }

    private static function fillRows(Classroom $classroom, Get $get, Set $set): void
    {
        $book = app(ScoreBook::class);
        $period = filled($get('period_id')) ? ScorePeriod::definedPeriods()->find($get('period_id')) : null;

        if ($period === null || blank($get('subject_id'))) {
            $set('rows', []);

            return;
        }

        $scores = $book->scores($classroom, $period, (int) $get('subject_id'));

        $set('rows', $book->learners($classroom)->map(fn (User $learner): array => [
            'user_id' => $learner->getKey(),
            'name' => $learner->name,
            'score' => $scores->get($learner->getKey())?->score,
            'level' => $scores->get($learner->getKey())?->level?->value,
            'note' => $scores->get($learner->getKey())?->note,
        ])->values()->all());
    }
}
