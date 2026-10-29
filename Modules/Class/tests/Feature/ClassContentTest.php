<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Filament\Resources\TeachingPlans\Pages\ListTeachingPlans;
use Modules\Class\Filament\Resources\TeachingPlans\TeachingPlanResource;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\PlanTopic;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\PlanForecast;
use Modules\Class\Services\SessionContentBook;
use Modules\Class\Services\SessionPlanner;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Teaching plans, their forecast over sessions, and recording what a
 * session covered (ADR 0025). Today is Thursday 2026-10-29; the schedule
 * meets on Saturdays from 2026-10-31 (31/10, 7/11, 14/11, 21/11, 28/11).
 */
class ClassContentTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private Classroom $classroom;

    private User $teacher;

    private TeachingPlan $plan;

    /** @var list<PlanTopic> */
    private array $topics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();

        $this->roleWithPermissions('teacher', []);
        $this->classroom = Classroom::factory()->for($this->west)->create();
        $this->teacher = User::factory()->create();
        $this->grantRole($this->teacher, $this->west, 'teacher');
        app(ClassMembers::class)->add($this->classroom, ClassRole::factory()->for($this->primary)->teacher()->create(), [$this->teacher->id]);

        // Topic 1 takes two sessions, topics 2 and 3 one each.
        $this->plan = TeachingPlan::factory()->for($this->primary)->withTopics([2, 1, 1])->create();
        $this->topics = $this->plan->topics->all();

        app(SessionPlanner::class)->saveSchedule(null, $this->classroom, [
            'frequency' => Frequency::Weekly, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value],
            'starts_on' => '2026-10-31', 'ends_on' => '2026-11-28', 'start_time' => '08:00', 'end_time' => '09:30',
            'teacher_id' => $this->teacher->id, 'teaching_plan_id' => $this->plan->id,
        ]);
    }

    public function test_the_plans_topics_are_laid_over_the_coming_sessions(): void
    {
        $this->assertSame([
            '2026-10-31' => ['سرفصل 1'], '2026-11-07' => ['سرفصل 1'], '2026-11-14' => ['سرفصل 2'], '2026-11-21' => ['سرفصل 3'],
        ], $this->forecastByDay());
    }

    public function test_an_unfinished_topic_moves_on_and_a_finished_one_leaves_the_forecast(): void
    {
        $book = app(SessionContentBook::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00'));

        // The first session doesn't finish topic 1.
        $book->record($this->sessionOn('2026-10-31'), ['summary' => 'نیمه‌ی اول سرفصل ۱'], $this->teacher);
        $this->assertSame(['2026-11-07' => ['سرفصل 1'], '2026-11-14' => ['سرفصل 1'], '2026-11-21' => ['سرفصل 2'], '2026-11-28' => ['سرفصل 3']], $this->forecastByDay());

        // A cancelled session gets nothing; the next one finishes topics 1 and 2.
        $this->travelTo(CarbonImmutable::parse('2026-11-14 09:00'));
        app(SessionPlanner::class)->cancel($this->sessionOn('2026-11-07'), 'تعطیلی ناگهانی');
        $book->record($this->sessionOn('2026-11-14'), ['covered_topic_ids' => [$this->topics[0]->id, $this->topics[1]->id]], $this->teacher);

        $this->assertSame(['2026-11-21' => ['سرفصل 3']], $this->forecastByDay());
        $this->assertSame(['covered' => 2, 'total' => 3], app(PlanForecast::class)->progress($this->classroom->schedules()->sole()));
        $this->assertSame(SessionStatus::Held, $this->sessionOn('2026-11-14')->status);
    }

    public function test_content_needs_a_topic_of_the_plan_or_a_summary_and_a_due_date_after_the_session(): void
    {
        $book = app(SessionContentBook::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00'));
        $session = $this->sessionOn('2026-10-31');
        $otherPlanTopic = TeachingPlan::factory()->for($this->primary)->withTopics([1])->create()->topics()->sole();

        $this->assertRefused(fn () => $book->record($session, [], $this->teacher), 'سرفصلی را انتخاب کنید');
        $this->assertRefused(fn () => $book->record($session, ['covered_topic_ids' => [$otherPlanTopic->id]], $this->teacher), 'در طرح درس این کلاس نیست');
        $this->assertRefused(fn () => $book->record($session, ['summary' => 'x', 'homework' => 'تمرین ۱', 'homework_due_on' => '2026-10-30'], $this->teacher), 'مهلت تکلیف');

        $book->record($session, ['summary' => 'مقدمه', 'homework' => 'تمرین ۱ تا ۵', 'homework_due_on' => '2026-11-07'], $this->teacher);
        $session->refresh();
        $this->assertSame('تمرین ۱ تا ۵', $session->homework);
        $this->assertSame('2026-11-07', $session->homework_due_on->toDateString());
        $this->assertSame($this->teacher->id, $session->content_recorded_by_id);
    }

    public function test_who_may_record_content(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00'));
        $session = $this->sessionOn('2026-10-31');
        $supervisor = User::factory()->create();
        $this->grantRole($supervisor, $this->west, 'teacher');
        app(ClassMembers::class)->add($this->classroom, ClassRole::factory()->for($this->primary)->create(['title' => 'ناظر', 'takes_attendance' => true]), [$supervisor->id]);
        $book = app(SessionContentBook::class);

        $this->assertTrue($book->canRecord($this->teacher, $session));
        $this->assertFalse($book->canRecord($supervisor, $session));

        $this->travelTo(CarbonImmutable::parse('2026-11-08 00:01'));
        $this->assertStringContainsString('مهلت', $book->refusal($this->teacher, $session));
    }

    public function test_the_content_action_saves_topics_and_materials_and_links_are_signed(): void
    {
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-10-31 09:00'));
        $this->makeStaff($this->teacher, $this->west);
        $this->roleWithPermissions('class_viewer', ['ViewAny:Classroom', 'View:Classroom']);
        $this->grantRole($this->teacher, $this->west, 'class_viewer');
        $session = $this->sessionOn('2026-10-31');
        Storage::disk('local')->put('class-materials/handout.pdf', '%PDF-1.4 handout');

        $this->actingInPanel($this->teacher, $this->west);
        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class])
            ->mountAction(TestAction::make('content')->table($session))
            ->assertSchemaStateSet(['covered_topic_ids' => [$this->topics[0]->id]])
            ->setActionData(['covered_topic_ids' => [], 'summary' => 'بخش اول', 'files' => [UploadedFile::fake()->create('handout.pdf', 100, 'application/pdf')]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $session->refresh();
        $this->assertSame('بخش اول', $session->summary);
        $links = $session->materialLinks();
        $this->assertCount(1, $links);

        $this->get($links[0]['url'])->assertOk();
        $this->get(route('class.materials', ['media' => $links[0]['id']]))->assertForbidden();
    }

    public function test_plans_are_made_in_the_primary_tenant_with_their_topics(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);

        $this->actingInPanel($admin, $this->primary);
        Livewire::test(ListTeachingPlans::class)
            ->callAction('create', data: [
                'title' => 'علوم هفتم', 'is_active' => true,
                'topics' => [['title' => 'تجربه و تفکر', 'sessions' => 2], ['title' => 'اندازه‌گیری', 'sessions' => 1]],
            ])
            ->assertHasNoActionErrors();

        $plan = TeachingPlan::withoutTenantScope()->where('title', 'علوم هفتم')->sole();
        $this->assertSame($this->primary->id, $plan->tenant_id);
        $this->assertSame(['تجربه و تفکر', 'اندازه‌گیری'], $plan->topics->pluck('title')->all());
        $this->assertSame([2, 1], $plan->topics->pluck('sessions')->all());

        $this->get(TeachingPlanResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(TeachingPlanResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    private function sessionOn(string $day): ClassSession
    {
        return ClassSession::query()->whereDate('starts_at', $day)->sole();
    }

    /**
     * @return array<string, list<string>>
     */
    private function forecastByDay(): array
    {
        $forecast = app(PlanForecast::class);
        $forecast->flush();

        return ClassSession::query()->orderBy('starts_at')->get()
            ->mapWithKeys(fn (ClassSession $session): array => [$session->starts_at->toDateString() => array_map(fn (PlanTopic $topic): string => $topic->title, $forecast->topicsFor($session))])
            ->filter()
            ->all();
    }

    private function assertRefused(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (ClassException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("No error «{$message}».");
    }
}
