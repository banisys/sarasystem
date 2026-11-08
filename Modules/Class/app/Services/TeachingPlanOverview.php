<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Models\TeachingPlan;

/**
 * A teaching plan as a picture (ADR 0071): its topics laid over its
 * sessions in order, and every class that follows it (in any tenant) with
 * the topics it has covered and the sessions its schedules give.
 */
class TeachingPlanOverview
{
    public function __construct(private PlanForecast $forecast) {}

    /**
     * @return array{
     *     plan: array{title: string, subject: ?string, description: ?string, is_active: bool},
     *     totals: array{topics: int, sessions: int, classes: int},
     *     topics: list<array{id: int, number: int, title: string, description: ?string, sessions: int, from: int, to: int}>,
     *     classes: list<array{title: string, tenant: ?string, teachers: string, covered_ids: list<int>, covered: int, sessions: int, held: int}>,
     * }
     */
    public function of(TeachingPlan $plan): array
    {
        $topics = $plan->topics()->orderBy('sort')->orderBy('id')->get();
        $next = 1;
        $laidOut = $topics->values()->map(function (PlanTopic $topic, int $index) use (&$next): array {
            $sessions = max(1, (int) $topic->sessions);
            $from = $next;
            $next += $sessions;

            return [
                'id' => $topic->getKey(),
                'number' => $index + 1,
                'title' => $topic->title,
                'description' => $topic->description,
                'sessions' => $sessions,
                'from' => $from,
                'to' => $next - 1,
            ];
        })->all();

        $classes = $this->classes($plan, $topics->modelKeys());

        return [
            'plan' => [
                'title' => $plan->title,
                'subject' => $plan->subject?->title,
                'description' => $plan->description,
                'is_active' => (bool) $plan->is_active,
            ],
            'totals' => ['topics' => count($laidOut), 'sessions' => $next - 1, 'classes' => count($classes)],
            'topics' => $laidOut,
            'classes' => $classes,
        ];
    }

    /**
     * The classes whose schedules follow the plan, in any tenant.
     *
     * @param  list<int>  $topicIds
     * @return list<array{title: string, tenant: ?string, teachers: string, covered_ids: list<int>, covered: int, sessions: int, held: int}>
     */
    private function classes(TeachingPlan $plan, array $topicIds): array
    {
        /** @var Collection<int, ClassSchedule> $schedules */
        $schedules = ClassSchedule::withoutTenantScope()
            ->where('teaching_plan_id', $plan->getKey())
            ->with(['classroom' => fn ($query) => $query->withoutGlobalScopes()->with('tenant'), 'teachers'])
            ->get()
            ->filter(fn (ClassSchedule $schedule): bool => $schedule->classroom !== null);

        return $schedules->groupBy('classroom_id')->map(function (Collection $classSchedules) use ($topicIds): array {
            $classroom = $classSchedules->first()->classroom;
            $covered = array_values(array_intersect($topicIds, $this->forecast->coveredTopicIds($classroom->getKey())));
            $sessions = ClassSession::withoutTenantScope()
                ->whereIn('schedule_id', $classSchedules->modelKeys())
                ->where('status', '!=', SessionStatus::Cancelled);

            return [
                'title' => $classroom->title,
                'tenant' => $classroom->tenant?->name,
                'teachers' => $classSchedules->flatMap(fn (ClassSchedule $schedule): Collection => $schedule->teachers)
                    ->unique('id')->map(fn (User $teacher): string => $teacher->name)->join('، '),
                'covered_ids' => $covered,
                'covered' => count($covered),
                'sessions' => (clone $sessions)->count(),
                'held' => (clone $sessions)->where('status', SessionStatus::Held)->count(),
            ];
        })->sortBy([['tenant', 'asc'], ['title', 'asc']])->values()->all();
    }
}
