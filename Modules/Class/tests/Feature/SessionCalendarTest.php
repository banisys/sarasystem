<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\Subject;
use Modules\Class\Services\SessionCalendar;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The «جلسه‌ها» tab's calendar and session details (ADR 0047).
 * Now is Thursday 2026-10-29 (1405/08/07) 12:00.
 */
class SessionCalendarTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->classroom = Classroom::factory()->for($this->west)->create();
    }

    public function test_periods_follow_the_jalali_calendar_and_the_iranian_week(): void
    {
        $date = CarbonImmutable::parse('2026-10-29');

        $week = SessionCalendar::period('week', $date);
        $this->assertSame(['2026-10-24', '2026-10-30', '2026-10-17', '2026-10-31'], [$week['first']->toDateString(), $week['last']->toDateString(), $week['previous']->toDateString(), $week['next']->toDateString()]);

        $month = SessionCalendar::period('month', $date);
        $this->assertSame(['2026-10-23', '2026-11-21', '2026-09-23', '2026-11-22'], [$month['first']->toDateString(), $month['last']->toDateString(), $month['previous']->toDateString(), $month['next']->toDateString()]);

        $year = SessionCalendar::period('year', $date);
        $this->assertSame(['2026-03-21', '2027-03-20', '2025-03-21', '2027-03-21'], [$year['first']->toDateString(), $year['last']->toDateString(), $year['previous']->toDateString(), $year['next']->toDateString()]);
        $this->assertSame('سال 1405', $year['label']);
    }

    public function test_sessions_are_toned_by_status_and_time(): void
    {
        $this->assertSame('scheduled', SessionCalendar::tone($this->classSession('2026-10-30 10:00')));
        $this->assertSame('live', SessionCalendar::tone($this->classSession('2026-10-29 11:30')));
        $this->assertSame('pending', SessionCalendar::tone($this->classSession('2026-10-28 10:00')));
        $this->assertSame('held', SessionCalendar::tone($this->classSession('2026-10-28 14:00', SessionStatus::Held)));
        $this->assertSame('cancelled', SessionCalendar::tone($this->classSession('2026-10-27 10:00', SessionStatus::Cancelled)));
    }

    public function test_the_calendar_lays_out_the_class_sessions_only(): void
    {
        Holiday::factory()->for($this->west)->create(['title' => 'تعطیلی شعبه', 'starts_on' => '2026-10-31', 'ends_on' => '2026-10-31']);
        $this->classSession('2026-10-28 10:00', SessionStatus::Held);
        $this->classSession('2026-10-31 07:00');
        $this->classSession('2026-11-25 10:00');
        ClassSession::factory()->create(['classroom_id' => Classroom::factory()->for($this->west)->create()->id, 'starts_at' => '2026-10-29 10:00', 'ends_at' => '2026-10-29 11:00']);

        $calendar = app(SessionCalendar::class);
        $week = $calendar->forClassroom($this->classroom, 'week', CarbonImmutable::parse('2026-10-29'));
        $this->assertSame(range(8, 17), $week['hours']);
        $this->assertSame(1, $week['totals']['held']);
        $this->assertSame(0, $week['totals']['pending']);
        $this->assertCount(1, $week['days'][4]['sessions']);
        $this->assertSame([], $week['days'][5]['sessions']);
        $this->assertEquals(20, $week['days'][4]['sessions'][0]['top']);
        $this->assertEquals(15, $week['days'][4]['sessions'][0]['height']);

        $month = $calendar->forClassroom($this->classroom, 'month', CarbonImmutable::parse('2026-10-29'));
        $this->assertSame(6, $month['leading_blanks']);
        $this->assertSame('تعطیلی شعبه', collect($month['days'])->firstWhere('date', '2026-10-31')['holiday']);
        $this->assertSame(2, array_sum($month['totals']));

        // An empty day: its month to pick from, and the nearest sessions around it.
        $day = $calendar->forClassroom($this->classroom, 'day', CarbonImmutable::parse('2026-11-10'));
        $this->assertSame(['2026-10-31', '2026-11-25'], [$day['previous_session'], $day['next_session']]);
        $this->assertSame('2026-10-23', $day['mini_month']['date']);
        $this->assertSame(['2026-09-23', '2026-11-22'], [$day['mini_month']['previous'], $day['mini_month']['next']]);
        $this->assertSame(1, collect($day['mini_month']['days'])->firstWhere('date', '2026-10-28')['tones']['held']);
        $this->assertNull($calendar->forClassroom($this->classroom, 'week', CarbonImmutable::parse('2026-11-25'))['next_session']);

        $year = $calendar->forClassroom($this->classroom, 'year', CarbonImmutable::parse('2026-10-29'));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 2, 1, 0, 0, 0], array_column($year['months'], 'count'));
    }

    public function test_the_tab_shows_the_calendar_and_switches_views(): void
    {
        $subject = Subject::factory()->create(['title' => 'ریاضی']);
        $this->classSession('2026-10-30 10:00', subject: $subject);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(SessionsRelationManager::class, $this->owner())
            ->assertSee('آبان 1405')
            ->assertSee('ریاضی')
            ->assertSee($this->classroom->mode->getLabel())
            ->call('showSessions', 'year', '2027-01-15')
            ->assertSet('sessionsView', 'year')
            ->assertSee('سال 1405')
            ->assertSee('اسفند')
            ->call('showSessions', 'day', '2026-10-30')
            ->assertSee('ریاضی')
            ->assertSee('جلسه‌ی بعدی')
            ->set('sessionsDate', '2026-12-01 00:00:00')
            ->assertSee('آذر 1405')
            ->assertSee('جلسه‌ای در این روز نیست')
            ->call('showSessions', 'week', 'not-a-date')
            ->assertSet('sessionsDate', null)
            ->call('showSessions', 'list')
            ->assertSet('sessionsView', 'month')
            ->assertDontSee('فهرست')
            ->set('sessionsView', 'list')
            ->assertSee('آبان 1405');
    }

    public function test_a_session_links_to_its_page_in_a_new_tab_and_marks_itself_opened(): void
    {
        $session = $this->classSession('2026-10-30 10:00');
        $elsewhere = ClassSession::factory()->create(['classroom_id' => Classroom::factory()->for($this->west)->create()->id, 'starts_at' => '2026-10-30 12:00', 'ends_at' => '2026-10-30 13:00']);

        $this->actingInPanel($this->admin(), $this->west);
        $calendar = Livewire::test(SessionsRelationManager::class, $this->owner());

        foreach (['month', 'week', 'day'] as $view) {
            $calendar->call('showSessions', $view, '2026-10-30')
                ->assertSeeHtml('href="'.ClassSessionResource::getUrl('view', ['record' => $session]).'" target="_blank"')
                ->assertSeeHtml('x-on:click="openedSession = '.$session->id.'"')
                ->assertSeeHtml('border-dashed!')
                ->assertDontSeeHtml(ClassSessionResource::getUrl('view', ['record' => $elsewhere]));
        }
    }

    private function classSession(string $startsAt, SessionStatus $status = SessionStatus::Scheduled, ?Subject $subject = null): ClassSession
    {
        $start = CarbonImmutable::parse($startsAt);

        return ClassSession::factory()->create([
            'classroom_id' => $this->classroom->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(90),
            'status' => $status,
            'subject_id' => $subject?->id,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());

        return $admin;
    }

    /**
     * @return array{ownerRecord: Classroom, pageClass: class-string}
     */
    private function owner(): array
    {
        return ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class];
    }
}
