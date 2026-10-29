<?php

namespace Modules\School\Providers;

use App\Filament\Support\ModelRelationManagers;
use App\Guardians\Guardians;
use App\Models\Tenant;
use App\Models\User;
use App\UserPanel\UserPanel;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Support\ClassroomExtensions;
use Modules\School\Filament\Actions\ScoreBookAction;
use Modules\School\Filament\RelationManagers\EnrollmentsRelationManager;
use Modules\School\Models\ClassroomGrade;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Major;
use Modules\School\Services\Enrollments;
use Modules\School\Services\ReportCards;
use Modules\School\Services\SchoolException;
use Modules\School\Services\ScoreBook;
use Modules\Student\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SchoolServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'School';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'school';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->extendClassrooms();

        // A learner joining a class with a grade gets it for the year (ADR 0031).
        ClassMember::created(fn (ClassMember $member) => app(Enrollments::class)->memberJoined($member));

        // «دفتر نمره» for teachers of classes with a grade, «کارنامه» for
        // learners with a published report card and their guardians (ADR 0032).
        UserPanel::navigationUsing('school', function (User $user, Tenant $tenant): array {
            $cards = app(ReportCards::class);

            return [
                ...(app(ScoreBook::class)->assignmentsOf($user)->isNotEmpty() ? [['title' => 'دفتر نمره', 'url' => route('school.scores.index')]] : []),
                ...($cards->publishedFor($user->getKey(), $tenant->getKey())->isNotEmpty() ? [['title' => 'کارنامه', 'url' => route('school.report-cards.index')]] : []),
                ...app(Guardians::class)->wardsIn($user, $tenant)
                    ->filter(fn (User $ward): bool => $cards->publishedFor($ward->getKey(), $tenant->getKey())->isNotEmpty())
                    ->map(fn (User $ward): array => ['title' => 'کارنامه‌ی '.$ward->name, 'url' => route('school.report-cards.index', ['for' => $ward->getKey()])])
                    ->values()->all(),
            ];
        });

        // A «پرونده‌ی تحصیلی» tab on each learner's page.
        Student::resolveRelationUsing(EnrollmentsRelationManager::RELATIONSHIP, fn (Student $student): HasMany => $student->hasMany(Enrollment::class, 'user_id', 'user_id'));
        ModelRelationManagers::register(Student::class, EnrollmentsRelationManager::class);
    }

    /**
     * «پایه / رشته» on classes (ADR 0031): in the form (saved through
     * Enrollments), on the class page, and as a column and filter.
     */
    private function extendClassrooms(): void
    {
        $gradeOf = fn (?Model $classroom): ?ClassroomGrade => $classroom === null ? null
            : ClassroomGrade::withoutTenantScope()->with(['grade', 'major'])->where('classroom_id', $classroom->getKey())->first();

        ClassroomExtensions::register('school', [
            'form' => fn (): array => [
                Select::make('school_grade_id')
                    ->label('پایه')
                    ->helperText('برای کلاس‌های مدرسه؛ '.config('sarasystem.learner_plural_label').'ی آن این پایه را برای سال تحصیلی کلاس می‌گیرند.')
                    ->options(fn (): array => Grade::options())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->loadStateFromRelationshipsUsing(fn (Select $component, ?Classroom $record) => $component->state($gradeOf($record)?->grade_id))
                    ->saveRelationshipsUsing(function (Classroom $record, mixed $state, Get $get): void {
                        try {
                            app(Enrollments::class)->setClassroomGrade($record, filled($state) ? (int) $state : null, filled($get('school_major_id')) ? (int) $get('school_major_id') : null);
                        } catch (SchoolException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            throw new Halt;
                        }
                    }),
                Select::make('school_major_id')
                    ->label('رشته')
                    ->options(fn (): array => Major::options())
                    ->visible(fn (Get $get): bool => filled($get('school_grade_id')) && (bool) Grade::definedGrades()->find($get('school_grade_id'))?->has_majors)
                    ->required()
                    ->dehydrated(false)
                    ->loadStateFromRelationshipsUsing(fn (Select $component, ?Classroom $record) => $component->state($gradeOf($record)?->major_id)),
            ],
            'infolist' => fn (): array => [
                TextEntry::make('school_grade')
                    ->label('پایه')
                    ->state(fn (Classroom $record): ?string => ($grade = $gradeOf($record)) ? $grade->grade->title.($grade->major ? ' - '.$grade->major->title : '') : null)
                    ->placeholder('—'),
            ],
            'columns' => fn (): array => [
                TextColumn::make('school_grade')
                    ->label('پایه')
                    ->state(fn (Classroom $record): ?string => ($grade = $gradeOf($record)) ? $grade->grade->title.($grade->major ? ' - '.$grade->major->title : '') : null)
                    ->placeholder('—')
                    ->toggleable(),
            ],
            'actions' => fn (): array => [ScoreBookAction::make()],
            'filters' => fn (): array => [
                SelectFilter::make('school_grade')
                    ->label('پایه')
                    ->options(fn (): array => Grade::options())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $gradeId) => $query
                        ->whereIn('id', ClassroomGrade::withoutTenantScope()->where('grade_id', $gradeId)->select('classroom_id')))),
            ],
        ]);
    }
}
