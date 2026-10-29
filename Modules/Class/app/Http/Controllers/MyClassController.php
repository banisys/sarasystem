<?php

namespace Modules\Class\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Rules\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\MyClasses;
use Modules\Class\Services\PlanForecast;
use Modules\Class\Services\SessionContentBook;

/**
 * «کلاس‌های من» in the user panel (ADR 0026): the person's calendar of
 * sessions, a session's details, content, homework and materials, their own
 * attendance, and — for who may — taking attendance and recording content.
 */
class MyClassController extends Controller
{
    public function index(Request $request, MyClasses $myClasses): Response
    {
        $view = in_array($request->query('view'), MyClasses::VIEWS, true) ? $request->query('view') : 'week';
        $date = rescue(fn () => CarbonImmutable::parse((string) $request->query('date', today()->toDateString()))->startOfDay(), today()->toImmutable(), report: false);

        /** @var User $user */
        $user = $request->user();
        $classes = ClassMember::query()->where('user_id', $user->getKey())->current()
            ->with(['classroom', 'classRole'])
            ->get()
            ->groupBy('classroom_id')
            ->map(fn ($members): array => [
                'id' => $members->first()->classroom_id,
                'title' => $members->first()->classroom->title,
                'roles' => $members->pluck('classRole.title')->join('، '),
                'mode' => $members->first()->classroom->mode->getLabel(),
                'meeting_url' => $members->first()->classroom->mode->meetsOnline() ? $members->first()->classroom->meeting_url : null,
            ])
            ->values();

        return Inertia::render('Class::Index', [
            'calendar' => $myClasses->calendar($user, Tenant::current(), $view, $date),
            'classes' => $classes,
        ]);
    }

    public function show(Request $request, ClassSession $session, MyClasses $myClasses, AttendanceRegister $register, SessionContentBook $book, PlanForecast $forecast): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($myClasses->canSee($user, $session), 404);

        $role = $myClasses->roleIn($user, $session);
        $canTakeAttendance = $register->canTake($user, $session);
        $canRecordContent = $book->canRecord($user, $session);
        $recorded = $session->attendances()->get()->keyBy('user_id');
        $mine = $recorded->get($user->getKey());

        return Inertia::render('Class::Session', [
            'session' => [
                ...$myClasses->sessionCard($session),
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
                    'done_elsewhere' => in_array($topic->getKey(), $forecast->coveredTopicIds($session->classroom_id), true)
                        && ! in_array($topic->getKey(), $session->covered_topic_ids ?? [], true),
                ])->values(),
                'covered_topic_ids' => $session->hasContent()
                    ? ($session->covered_topic_ids ?? [])
                    : array_values(array_unique(array_map(fn (PlanTopic $topic): int => $topic->getKey(), $forecast->topicsFor($session)))),
                'summary' => $session->summary ?? '',
                'homework' => $session->homework ?? '',
                'homework_due_on' => $session->homework_due_on ? MyClasses::jalali($session->homework_due_on->toImmutable(), 'Y/m/d') : '',
                'materials' => array_map(fn (array $link): array => ['id' => $link['id'], 'name' => $link['name']], $session->materialLinks()),
            ] : null,
        ]);
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
