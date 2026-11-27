<?php

namespace Modules\Class\Http\Controllers;

use App\Guardians\Guardians;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ExamSeating;
use Modules\Class\Services\HomeworkBook;
use Modules\Class\Services\MyClasses;
use Modules\Class\Services\Online\OnlineEntry;
use Modules\Class\Services\Online\OnlineRooms;
use Modules\Class\Services\PlanForecast;
use Modules\Class\Services\SessionContentBook;
use Modules\Class\Services\SessionExamSource;
use Modules\Class\Services\SessionLessons;
use Modules\Class\Services\SessionReminders;
use Modules\Exam\Services\ExamPerformance;
use Nwidart\Modules\Facades\Module;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * «تقویم آموزشی» in the user panel (ADR 0026): the person's calendar of
 * sessions, a session's details, content, homework and materials, their own
 * attendance, and — for who may — taking attendance and recording content.
 * Learners hand in homework, and teachers grade it (ADR 0030). A parent or
 * guardian sees a ward's the same way, read only (ADR 0027).
 */
class MyClassController extends Controller
{
    public function index(Request $request, MyClasses $myClasses): Response
    {
        $query = $request->query();
        $view = in_array($query['view'] ?? null, MyClasses::VIEWS, true) ? $query['view'] : 'month';
        $date = rescue(fn () => CarbonImmutable::parse((string) $request->query('date', today()->toDateString()))->startOfDay(), today()->toImmutable(), report: false);
        $classroomId = filter_var($query['classroom'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $subject = filter_var($query['subject'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $mode = is_string($query['mode'] ?? null) ? ClassMode::tryFrom($query['mode']) : null;

        $user = $this->person($request);
        $classes = ClassMember::query()->where('user_id', $user->getKey())->current()
            ->with(['classroom.onlineRooms.server', 'classRole'])
            ->get()
            ->groupBy('classroom_id')
            ->map(fn ($members): array => [
                'id' => $members->first()->classroom_id,
                'title' => $members->first()->classroom->title,
                'roles' => $members->pluck('classRole.title')->join('، '),
                'online_platform' => app(OnlineRooms::class)->on($members->first()->classroom, today())?->platformName(),
            ])
            ->values();

        $classroomId = $classes->containsStrict('id', $classroomId) ? $classroomId : null;

        return Inertia::render('Class::Index', [
            'calendar' => $myClasses->calendar($user, Tenant::current(), $view, $date, $subject, $mode, classroomId: $classroomId),
            'classes' => $classes,
            'showClassList' => ! $user->is($request->user()) || ! $myClasses->canBrowseMemberships($user),
            'ward' => $user->is($request->user()) ? null : ['id' => $user->getKey(), 'name' => $user->name],
        ]);
    }

    public function memberships(Request $request, MyClasses $myClasses): Response|RedirectResponse
    {
        if ($request->hasAny(['view', 'date', 'classroom', 'subject', 'mode', 'for'])) {
            return to_route('my-classes.index', $request->query());
        }

        $user = $request->user();
        abort_unless($myClasses->canBrowseMemberships($user), 403);

        return Inertia::render('Class::Memberships', [
            'classes' => $myClasses->memberClassrooms($user)->orderBy('title')->orderBy('id')->get(['id', 'title']),
        ]);
    }

    public function membership(Request $request, int $classroom, MyClasses $myClasses): Response
    {
        $user = $request->user();
        abort_unless($myClasses->canBrowseMemberships($user), 403);

        return Inertia::render('Class::Membership', [
            'classroom' => $myClasses->membershipDetails($user, $classroom),
        ]);
    }

    public function learnerPerformance(Request $request, int $classroom, int $learner, MyClasses $myClasses): Response
    {
        abort_unless(Module::isEnabled('Exam'), 404);
        $viewer = $request->user();
        abort_unless($myClasses->canBrowseMemberships($viewer), 403);

        $class = $myClasses->memberClassrooms($viewer)->findOrFail($classroom);
        abort_unless($class->members()->activeToday()->whereBelongsTo($viewer)
            ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', false))->exists(), 403);
        $student = $class->members()->activeToday()->attendees()->where('user_id', $learner)->firstOrFail()->user;
        $term = $class->term()->current()->first();
        $subjects = [];
        if ($term !== null) {
            $sessions = $class->sessions()->takingPlace()
                ->whereBetween('starts_at', [$term->starts_on->startOfDay(), $term->ends_on->endOfDay()])
                ->whereIn('subject_id', $myClasses->assignedSubjects($viewer, $class)->reorder()->select('id'))
                ->select('class_sessions.id');
            $subjects = app(ExamPerformance::class)->staffResults(
                $student,
                fn (Builder $exams): Builder => $exams->where('context_type', SessionExamSource::TYPE)->whereIn('context_id', $sessions),
            );
        }

        return Inertia::render('Exam::Performance', [
            'subjects' => $subjects,
            'learnerName' => $student->name,
            'academicTerm' => $term?->title,
            'backUrl' => route('my-classes.membership', $classroom),
        ]);
    }

    public function show(Request $request, ClassSession $session, MyClasses $myClasses, AttendanceRegister $register, SessionContentBook $book, PlanForecast $forecast, HomeworkBook $homework, OnlineEntry $entry, SessionReminders $reminders, ExamSeating $seating, SessionLessons $lessons): Response
    {
        $user = $this->person($request);
        abort_unless($myClasses->canSee($user, $session), 404);

        // A guardian only looks.
        $isOwn = $user->is($request->user());
        $role = $myClasses->roleIn($user, $session);
        $canTakeAttendance = $isOwn && $register->canTake($user, $session);
        $canRecordContent = $isOwn && $book->canRecord($user, $session);
        $recorded = $session->attendances()->get()->keyBy('user_id');
        $mine = $recorded->get($user->getKey());

        return Inertia::render('Class::Session', [
            'session' => [
                ...$myClasses->sessionCard($session),
                // Only for the person themselves, not a guardian (ADR 0073).
                'join_url' => $isOwn && $entry->offers($user, $session) ? route('class.online.enter', $session) : null,
                ...($isOwn && Module::isEnabled('Exam')
                    ? app(SessionExamSource::class)->panelLinks($session, $user)
                    : ['exams_url' => null, 'exam_paper_url' => null]),
                'when' => SessionActions::when($session),
                'moved_from' => $session->wasMoved() ? SessionActions::when($session, original: true) : null,
                'cancel_reason' => $session->cancel_reason,
                'content' => $session->hasContent() ? [
                    'topics' => PlanTopic::query()->whereKey($session->covered_topic_ids ?? [])->orderBy('sort')->pluck('title'),
                    'summary' => $session->summary,
                ] : null,
                'homework' => $session->homework,
                'homework_due' => $session->homework_due_on ? MyClasses::jalali($session->homework_due_on->toImmutable(), 'l Y/m/d') : null,
                'materials' => array_map(fn (array $link): array => ['name' => $link['name'], 'url' => $link['url']], $session->materialLinks()),
                'planned_topics' => array_values(array_unique(array_map(fn (PlanTopic $topic): string => $topic->title, $forecast->topicsFor($session)))),
            ],
            'role' => $role,
            'sharedLessons' => fn (): ?array => $lessons->forViewer($session, $user, $isOwn),
            'ward' => $isOwn ? null : ['id' => $user->getKey(), 'name' => $user->name],
            'gallery' => fn (): array => [
                'can_upload' => $isOwn && $this->canUploadGallery($user, $session),
                'accept' => implode(',', ClassSession::GALLERY_MIME_TYPES),
                'max_mb' => config('media-library.max_file_size') / (1024 * 1024),
                'files' => $session->getMedia(ClassSession::GALLERY_COLLECTION)->map(fn (Media $media): array => [
                    'id' => $media->getKey(),
                    'name' => $media->file_name,
                    'is_video' => str_starts_with($media->mime_type, 'video/'),
                    'url' => route('my-classes.gallery.show', [
                        'session' => $session,
                        'media' => $media,
                        'for' => $isOwn ? null : $user->getKey(),
                    ]),
                ])->values(),
            ],
            'seating' => $isOwn && $seating->canViewInUserPanel($user, $session) ? $seating->editor($session) : null,
            'reminderTimeline' => fn () => $isOwn ? $reminders->timeline(
                $user,
                $session,
                $request->integer('previous_reminders'),
                $request->integer('upcoming_reminders', SessionReminders::PAGE_SIZE),
            ) : null,
            'myAttendance' => $role['is_attendee'] && $mine ? [
                'status' => $mine->status->getLabel(),
                'late_minutes' => $mine->late_minutes,
                'note' => $mine->note,
            ] : null,
            'statuses' => collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $status): array => ['value' => $status->value, 'label' => $status->getLabel()]),
            'attendance' => $canTakeAttendance ? $register->attendees($session)->map(fn (User $attendee): array => [
                'user_id' => $attendee->getKey(),
                'name' => $attendee->name,
                'status' => ($recorded->get($attendee->getKey())?->status ?? AttendanceStatus::Present)->value,
                'late_minutes' => $recorded->get($attendee->getKey())?->late_minutes,
                'note' => $recorded->get($attendee->getKey())?->note,
            ])->values() : null,
            'contentForm' => $canRecordContent ? [
                'topics' => $forecast->topicChoices($session)->map(fn (PlanTopic $topic): array => [
                    'id' => $topic->getKey(),
                    'title' => $topic->title,
                    'plan' => $topic->teachingPlan?->title,
                    'done_elsewhere' => in_array($topic->getKey(), $forecast->coveredTopicIds($session->classroom_id), true)
                        && ! in_array($topic->getKey(), $session->covered_topic_ids ?? [], true),
                ])->values(),
                'covered_topic_ids' => $session->hasContent()
                    ? ($session->covered_topic_ids ?? [])
                    : array_values(array_unique(array_map(fn (PlanTopic $topic): int => $topic->getKey(), $forecast->topicsFor($session)))),
                'summary' => $session->summary ?? '',
                'homework' => $session->homework ?? '',
                'homework_due_on' => $session->homework_due_on ? MyClasses::jalali($session->homework_due_on->toImmutable(), 'Y/m/d') : '',
                'homework_max_score' => $session->homework_max_score ?? config('class.homework.default_max_score'),
                'materials' => array_map(fn (array $link): array => ['id' => $link['id'], 'name' => $link['name']], $session->materialLinks()),
            ] : null,
            // Homework (ADR 0030): the person's own hand-in, the form while they
            // may change it, and the hand-ins to grade for who may.
            'homeworkMaxScore' => $homework->maxScore($session),
            'mySubmission' => $role['is_attendee'] && ($submission = $homework->submissionOf($user, $session)) ? $this->submission($submission) : null,
            'canSubmit' => $isOwn && $homework->canSubmit($user, $session),
            'submissions' => $isOwn && $homework->canGrade($user, $session)
                ? $register->attendees($session)->map(function (User $attendee) use ($session): array {
                    $submission = $session->homeworkSubmissions()->with(['session', 'media'])->where('user_id', $attendee->getKey())->first();

                    return ['name' => $attendee->name, 'submission' => $submission ? $this->submission($submission) : null];
                })->values()
                : null,
        ]);
    }

    public function uploadGalleryFile(Request $request, ClassSession $session, MyClasses $myClasses): RedirectResponse
    {
        $user = $request->user();
        abort_if($request->filled('for') || ! $myClasses->canSee($user, $session), 404);
        abort_unless($this->canUploadGallery($user, $session), 403);

        $data = $request->validate([
            'file' => ['required', File::types(ClassSession::GALLERY_MIME_TYPES)->max((int) (config('media-library.max_file_size') / 1024))],
        ], attributes: ['file' => 'فایل گالری']);

        DB::transaction(function () use ($session, $data): void {
            $session = ClassSession::query()->lockForUpdate()->findOrFail($session->getKey());
            // Keep the documented session when its schedule is rebuilt.
            $session->update(['is_modified' => true]);
            $session->addMedia($data['file'])->toMediaCollection(ClassSession::GALLERY_COLLECTION);
        });

        return back()->with('status', 'فایل به گالری اضافه شد.');
    }

    public function showGalleryFile(Request $request, ClassSession $session, int $media, MyClasses $myClasses): BinaryFileResponse
    {
        abort_unless($myClasses->canSee($this->person($request), $session), 404);

        $file = $session->media()->where('collection_name', ClassSession::GALLERY_COLLECTION)->findOrFail($media);
        $path = $file->getPath();
        abort_unless(is_file($path) && in_array($file->mime_type, ClassSession::GALLERY_MIME_TYPES, true), 404);

        return response()->file($path, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function canUploadGallery(User $user, ClassSession $session): bool
    {
        return in_array($user->getKey(), $session->actualTeacherIds(), true)
            || ClassMember::query()->where('classroom_id', $session->classroom_id)->whereBelongsTo($user)->activeToday()
                ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', false))->exists();
    }

    public function updateSeating(Request $request, ClassSession $session, MyClasses $myClasses, ExamSeating $seating): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($request->filled('for') || ! $myClasses->canSee($user, $session), 404);

        $this->attempt(fn () => $seating->save($session, $user, $request->input('layout'), inUserPanel: true));

        return to_route('my-classes.session', $session)->with('status', 'چیدمان صندلی‌ها ذخیره شد.');
    }

    public function addReminder(Request $request, ClassSession $session, ClassSession $target, SessionReminders $reminders): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($request->filled('for') || ! $reminders->canRead($user, $session), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.SessionReminders::MAX_LENGTH],
        ], attributes: ['body' => 'متن یادآوری']);

        $reminders->add($user, $session, $target, $data['body']);

        return to_route('my-classes.session', [
            'session' => $session,
            ...$request->only(['previous_reminders', 'upcoming_reminders']),
        ])->with('status', 'یادآوری ثبت شد.');
    }

    public function submitHomework(Request $request, ClassSession $session, MyClasses $myClasses, HomeworkBook $homework): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($myClasses->canSee($user, $session), 404);

        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:10000'],
            'files' => ['nullable', 'array', 'max:'.HomeworkBook::MAX_FILES],
            'files.*' => ['file', 'max:20480'],
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer'],
        ]);

        $this->attempt(fn () => $homework->submit($session, $user, $data['answer'] ?? null, $request->file('files', []), $data['remove_media_ids'] ?? []));

        return back()->with('status', 'تکلیف تحویل شد.');
    }

    public function grade(Request $request, ClassSession $session, MyClasses $myClasses, HomeworkBook $homework): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($myClasses->canSee($user, $session), 404);

        $data = $request->validate([
            'grades' => ['required', 'array'],
            'grades.*.id' => ['required', 'integer'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0'],
            'grades.*.feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->attempt(fn () => $homework->grade($session, collect($data['grades'])->keyBy(fn (array $row): int => (int) $row['id'])->all(), $user));

        return back()->with('status', 'نمره‌ها ذخیره شد.');
    }

    /**
     * A hand-in as the session page shows it.
     *
     * @return array<string, mixed>
     */
    private function submission(HomeworkSubmission $submission): array
    {
        return [
            'id' => $submission->getKey(),
            'answer' => $submission->answer,
            'submitted_at' => MyClasses::jalali($submission->submitted_at->toImmutable(), 'Y/m/d H:i'),
            'is_late' => $submission->isLate(),
            'files' => $submission->fileLinks(),
            'score' => $submission->score !== null ? MyClasses::score($submission->score) : null,
            'feedback' => $submission->feedback,
            'is_graded' => $submission->isGraded(),
        ];
    }

    public function attendance(Request $request, ClassSession $session, MyClasses $myClasses, AttendanceRegister $register): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($myClasses->canSee($user, $session), 404);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.user_id' => ['required', 'integer'],
            'rows.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'rows.*.late_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->attempt(fn () => $register->record($session, collect($data['rows'])->keyBy(fn (array $row): int => (int) $row['user_id'])->all(), $user));

        return back()->with('status', 'حضور و غیاب ثبت شد.');
    }

    public function shareLessons(Request $request, ClassSession $session, MyClasses $myClasses, SessionLessons $lessons): RedirectResponse
    {
        $user = $request->user();
        abort_if($request->filled('for') || ! $myClasses->canSee($user, $session), 404);
        $lessons->authorizeEditing($session, $user);

        $data = $request->validate([
            'lesson_codes' => ['present', 'array', 'list', 'max:50'],
            'lesson_codes.*' => ['required', 'string', 'max:64', 'distinct:strict'],
        ], attributes: ['lesson_codes' => 'کدهای درس', 'lesson_codes.*' => 'کد درس']);

        $lessons->save($session, $user, $data['lesson_codes']);

        return to_route('my-classes.session', ['session' => $session, 'tab' => 'content'])
            ->with('status', 'درس‌های این جلسه ذخیره شدند.');
    }

    public function sharedLessonFile(Request $request, ClassSession $session, int $lesson, int $file, MyClasses $myClasses, SessionLessons $lessons): BinaryFileResponse
    {
        $viewer = $this->person($request);
        abort_unless($myClasses->canSee($viewer, $session), 404);

        return $lessons->file($session, $viewer, $lesson, $file);
    }

    public function content(Request $request, ClassSession $session, MyClasses $myClasses, SessionContentBook $book): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($myClasses->canSee($user, $session), 404);

        $data = $request->validate([
            'covered_topic_ids' => ['nullable', 'array'],
            'covered_topic_ids.*' => ['integer'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'homework' => ['nullable', 'string', 'max:5000'],
            'homework_due_on' => ['nullable', new JalaliDate],
            'homework_max_score' => ['nullable', 'integer', 'min:1', 'max:100'],
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $this->attempt(fn () => $book->record($session, [
            ...$data,
            'homework_due_on' => JalaliDate::toDate($data['homework_due_on'] ?? null)?->toDateString(),
            'files' => $request->file('files', []),
        ], $user));

        return back()->with('status', 'مطالب جلسه ثبت شد.');
    }

    /**
     * Whose classes are shown: the user's, or (`?for=`) a ward's whom the
     * user is a parent or guardian of (ADR 0027) and who is a member of the
     * current tenant.
     */
    private function person(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        if (! $request->filled('for')) {
            return $user;
        }

        $ward = app(Guardians::class)->wardsIn($user, Tenant::current())->firstWhere('id', (int) $request->query('for'));
        abort_if($ward === null, 404);

        return $ward;
    }

    /**
     * @throws ValidationException
     */
    private function attempt(callable $operation): void
    {
        try {
            $operation();
        } catch (ClassException $exception) {
            throw ValidationException::withMessages(['form' => $exception->getMessage()]);
        }
    }
}
