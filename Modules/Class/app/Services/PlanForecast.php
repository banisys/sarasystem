<?php

namespace Modules\Class\Services;

use Illuminate\Support\Collection;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Models\TeachingPlan;

/**
 * Which topics of their teaching plan a class's coming sessions are
 * expected to cover (ADR 0025). Nothing is stored: the topics the class has
 * not covered yet, each repeated for the sessions it takes, are laid in
 * order over the sessions from today on (of the schedules following the
 * plan) that are not cancelled and have no content. A topic left unfinished
 * moves on by itself.
 */
class PlanForecast
{
    /**
     * Forecasts by class id, for the request.
     *
     * @var array<int, array<int, list<PlanTopic>>>
     */
    private array $byClassroom = [];

    /**
     * @return list<PlanTopic>
     */
    public function topicsFor(ClassSession $session): array
    {
        return $this->forClassroom($session->classroom_id)[$session->getKey()] ?? [];
    }

    /**
     * Session id => expected topics, for every session of the class with a forecast.
     *
     * @return array<int, list<PlanTopic>>
     */
    public function forClassroom(int $classroomId): array
    {
        if (array_key_exists($classroomId, $this->byClassroom)) {
            return $this->byClassroom[$classroomId];
        }

        $schedules = ClassSchedule::withoutTenantScope()->where('classroom_id', $classroomId)->whereNotNull('teaching_plan_id')->get(['id', 'teaching_plan_id']);
        $covered = $this->coveredTopicIds($classroomId);
        $forecast = [];

        foreach ($schedules->groupBy('teaching_plan_id') as $planId => $planSchedules) {
            $plan = TeachingPlan::withoutTenantScope()->with('topics')->find($planId);
            $slots = $plan === null ? collect() : $plan->topics
                ->reject(fn (PlanTopic $topic): bool => in_array($topic->getKey(), $covered, true))
                ->flatMap(fn (PlanTopic $topic): array => array_fill(0, max(1, $topic->sessions), $topic))
                ->values();

            $sessions = ClassSession::withoutTenantScope()
                ->whereIn('schedule_id', $planSchedules->modelKeys())
                ->where('status', SessionStatus::Scheduled)
                ->whereNull('content_recorded_at')
                ->where('starts_at', '>=', today())
                ->orderBy('starts_at')
                ->limit($slots->count())
                ->pluck('id');

            foreach ($sessions as $index => $sessionId) {
                $forecast[$sessionId][] = $slots[$index];
            }
        }

        return $this->byClassroom[$classroomId] = $forecast;
    }

    /**
     * How many of the plan's topics the class has covered.
     *
     * @return array{covered: int, total: int}
     */
    public function progress(ClassSchedule $schedule): array
    {
        $topicIds = PlanTopic::query()->where('teaching_plan_id', $schedule->teaching_plan_id)->pluck('id')->all();

        return [
            'covered' => count(array_intersect($topicIds, $this->coveredTopicIds($schedule->classroom_id))),
            'total' => count($topicIds),
        ];
    }

    /**
     * Topics the class's sessions recorded as covered.
     *
     * @return list<int>
     */
    public function coveredTopicIds(int $classroomId): array
    {
        return ClassSession::withoutTenantScope()
            ->where('classroom_id', $classroomId)
            ->whereNotNull('covered_topic_ids')
            ->pluck('covered_topic_ids')
            ->flatten()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Forget what was worked out (after content was recorded).
     */
    public function flush(): void
    {
        $this->byClassroom = [];
    }

    /**
     * The topics a session may record: its schedule's plan, or (a session
     * added by hand) every plan the class follows.
     *
     * @return Collection<int, PlanTopic>
     */
    public function topicChoices(ClassSession $session): Collection
    {
        $planIds = $session->schedule_id !== null
            ? ClassSchedule::withoutTenantScope()->whereKey($session->schedule_id)->whereNotNull('teaching_plan_id')->pluck('teaching_plan_id')
            : ClassSchedule::withoutTenantScope()->where('classroom_id', $session->classroom_id)->whereNotNull('teaching_plan_id')->distinct()->pluck('teaching_plan_id');

        return PlanTopic::query()->whereIn('teaching_plan_id', $planIds)->with('teachingPlan')->orderBy('teaching_plan_id')->orderBy('sort')->orderBy('id')->get();
    }
}
