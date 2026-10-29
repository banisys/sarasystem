<?php

namespace Modules\School\Filament\Resources\Enrollments\Schemas;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Term;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;
use Modules\School\Services\Enrollments;
use Modules\School\Services\SchoolException;
use Modules\Student\Models\Student;

/**
 * A learner's grade, major and main class in an academic year (ADR 0031).
 * Saved through Enrollments, which keeps the rules.
 */
class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label(config('sarasystem.learner_label'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::searchLearners($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->find($value)?->name)
                    ->disabledOn('edit')
                    ->required(),
                Select::make('term_id')
                    ->label('سال تحصیلی')
                    ->options(fn (?Enrollment $record): array => Term::options() + ($record?->term ? [$record->term->getKey() => $record->term->title] : []))
                    ->default(fn (): ?int => array_key_first(Term::options()))
                    ->required()
                    ->live(),
                Select::make('grade_id')
                    ->label('پایه')
                    ->options(fn (): array => Grade::options())
                    ->required()
                    ->live(),
                Select::make('major_id')
                    ->label('رشته')
                    ->options(fn (): array => Major::options())
                    ->visible(fn (Get $get): bool => (bool) Grade::definedGrades()->find($get('grade_id'))?->has_majors)
                    ->required(),
                Select::make('classroom_id')
                    ->label('کلاس')
                    ->options(fn (Get $get): array => Classroom::query()->topLevel()->where('term_id', $get('term_id'))->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable(),
            ])
            ->columns(2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(?Enrollment $enrollment, array $data): Enrollment
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $learner = $enrollment?->user ?? User::query()->findOrFail($data['user_id']);

        try {
            return app(Enrollments::class)->save(
                $enrollment, $learner, (int) $data['term_id'], (int) $data['grade_id'],
                filled($data['major_id'] ?? null) ? (int) $data['major_id'] : null,
                filled($data['classroom_id'] ?? null) ? (int) $data['classroom_id'] : null,
                $tenant->getKey(),
            );
        } catch (SchoolException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    /**
     * Learners of the tenant matching a name, national code or code.
     *
     * @return array<int, string>
     */
    private static function searchLearners(string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        return Student::query()
            ->where(fn (Builder $query) => $query
                ->where('student_code', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('national_code', "%{$search}%")))
            ->with('user')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->user_id => $student->user->name.' - '.$student->user->national_code])
            ->all();
    }
}
