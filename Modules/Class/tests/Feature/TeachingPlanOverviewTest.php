<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\TeachingPlans\Pages\ListTeachingPlans;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionContentBook;
use Modules\Class\Services\SessionPlanner;
use Modules\Class\Services\TeachingPlanOverview;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * «نمای طرح» on «طرح‌های درس» (ADR 0071): the plan's topics over its
 * sessions, and the classes of every tenant that follow it.
 */
class TeachingPlanOverviewTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private TeachingPlan $plan;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $west = Tenant::factory()->create(['name' => 'شعبه غرب']);
        $east = Tenant::factory()->create(['name' => 'شعبه شرق']);

        // Topic 1 takes two sessions, topics 2 and 3 one each: 4 sessions.
        $this->plan = TeachingPlan::factory()->for($this->primary)->withTopics([2, 1, 1])->create(['title' => 'ریاضی هفتم']);
        $this->teacher = User::factory()->create(['name' => 'رضا کریمی']);
        $role = ClassRole::factory()->for($this->primary)->teacher()->create();
        $this->roleWithPermissions('teacher', []);

        // West: five Saturdays; east: a single session, 3 short of the plan.
        foreach ([[$west, 'هفتم الف', Frequency::Weekly, '2026-11-28', '08:00'], [$east, 'هفتم ب', Frequency::Once, '2026-10-31', '10:00']] as [$tenant, $title, $frequency, $endsOn, $time]) {
            $classroom = Classroom::factory()->for($tenant)->create(['title' => $title]);
            $this->grantRole($this->teacher, $tenant, 'teacher');
            app(ClassMembers::class)->add($classroom, $role, [$this->teacher->id]);
            app(SessionPlanner::class)->saveSchedule(null, $classroom, [
                'frequency' => $frequency, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value],
                'starts_on' => '2026-10-31', 'ends_on' => $endsOn, 'start_time' => $time, 'end_time' => CarbonImmutable::parse($time)->addMinutes(90)->format('H:i'),
                'teacher_ids' => [$this->teacher->id], 'teaching_plan_id' => $this->plan->id,
            ]);
        }

        // West finished topic 1 in its first session.
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00'));
        $first = ClassSession::withoutTenantScope()->whereHas('classroom', fn ($query) => $query->withoutGlobalScopes()->where('title', 'هفتم الف'))->orderBy('starts_at')->first();
        app(SessionContentBook::class)->record($first, ['covered_topic_ids' => [$this->plan->topics[0]->id]], $this->teacher);
    }

    public function test_the_topics_are_laid_over_the_sessions_and_each_class_shows_its_progress(): void
    {
        $overview = app(TeachingPlanOverview::class)->of($this->plan);

        $this->assertSame(['topics' => 3, 'sessions' => 4, 'classes' => 2], $overview['totals']);
        $this->assertSame([[1, 2], [3, 3], [4, 4]], array_map(fn (array $topic): array => [$topic['from'], $topic['to']], $overview['topics']));

        $classes = collect($overview['classes'])->keyBy('title');
        $this->assertSame(['شعبه شرق', 0, 1, 0], [$classes['هفتم ب']['tenant'], $classes['هفتم ب']['covered'], $classes['هفتم ب']['sessions'], $classes['هفتم ب']['held']]);
        $this->assertSame(['شعبه غرب', 1, 5, 1], [$classes['هفتم الف']['tenant'], $classes['هفتم الف']['covered'], $classes['هفتم الف']['sessions'], $classes['هفتم الف']['held']]);
        $this->assertSame('رضا کریمی', $classes['هفتم الف']['teachers']);
    }

    public function test_the_row_action_shows_the_plan_in_a_modal(): void
    {
        $user = User::factory()->create();
        $this->makeStaff($user, $this->primary);
        $this->roleWithPermissions('plan_viewer', ['ViewAny:TeachingPlan']);
        $this->grantRole($user, $this->primary, 'plan_viewer');
        $this->actingInPanel($user, $this->primary);

        Livewire::test(ListTeachingPlans::class)
            ->mountTableAction('overview', $this->plan)
            ->assertMountedActionModalSee([
                'نمای طرح درس «ریاضی هفتم»', 'نقشه‌ی جلسه‌ها', 'سرفصل 1', 'جلسه‌های 1 تا 2', 'جلسه‌ی 3',
                'هفتم الف', 'شعبه غرب', 'پیشرفت:', 'هفتم ب', '3 جلسه کم است',
            ]);
    }
}
