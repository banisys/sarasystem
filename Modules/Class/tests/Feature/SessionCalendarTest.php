<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\Classrooms\Pages\ClassSessions;
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

    public function test_overlapping_sessions_keep_separate_space_on_the_day_and_week_timelines(): void
    {
        $cancelled = $this->classSession('2026-10-30 10:00', SessionStatus::Cancelled);
        $replacement = $this->classSession('2026-10-30 10:00');
        $overlapping = $this->classSession('2026-10-30 11:00');
        $following = $this->classSession('2026-10-30 12:30');
        $date = CarbonImmutable::parse('2026-10-30');
        $calendar = app(SessionCalendar::class);

        foreach (['day', 'week'] as $view) {
            $days = $calendar->forClassroom($this->classroom, $view, $date)['days'];
            $sessions = collect(collect($days)->firstWhere('date', '2026-10-30')['sessions'])->keyBy('id');

            $this->assertSame('cancelled', $sessions[$cancelled->id]['tone']);
            $this->assertSame($sessions[$cancelled->id]['top'], $sessions[$replacement->id]['top']);
            $this->assertSame([
                $cancelled->id => [0.0, 66.667],
                $replacement->id => [33.333, 33.333],
                $overlapping->id => [66.667, 0.0],
                $following->id => [0.0, 0.0],
            ], $sessions->map(fn (array $session): array => [$session['inline_start'], $session['inline_end']])->all());
        }
    }

    public function test_the_tab_shows_the_calendar_and_switches_views(): void
    {
        $subject = Subject::factory()->create(['title' => 'ریاضی']);
        $this->classSession('2026-10-30 10:00', subject: $subject);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(SessionsRelationManager::class, $this->owner())
            ->assertSee('آبان 1405')
            ->assertSee('ریاضی')
            ->assertSeeHtml('title="حضوری"')
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

    public function test_the_class_calendar_filters_sessions_and_navigation_by_subject(): void
    {
        $subject = Subject::factory()->for(Tenant::primary())->create(['title' => 'ریاضی', 'is_active' => false]);
        $other = Subject::factory()->for(Tenant::primary())->create(['title' => 'علوم']);
        $remoteSubject = Subject::factory()->for(Tenant::primary())->create(['title' => 'درس شعبهٔ دیگر']);
        $this->classSession('2026-10-30 08:00', subject: $subject)->update(['title' => 'تمرین کسرها']);
        $this->classSession('2026-10-30 10:00', subject: $subject)->update(['title' => 'آزمون کسرها', 'kind' => SessionKind::Exam]);
        $this->classSession('2026-10-30 12:00', subject: $other)->update(['title' => 'آزمایش نور']);
        $this->classSession('2026-10-30 14:00')->update(['title' => 'جلسهٔ معارفه']);
        $this->classSession('2026-10-31 08:00', subject: $other);
        $this->classSession('2026-11-02 08:00', subject: $subject);
        $remoteClass = Classroom::factory()->for(Tenant::factory()->create())->create();
        ClassSession::factory()->for($remoteClass, 'classroom')->for($remoteSubject)->create([
            'title' => 'جلسهٔ شعبهٔ دیگر',
            'starts_at' => '2026-10-30 08:00',
            'ends_at' => '2026-10-30 09:00',
        ]);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(SessionsRelationManager::class, $this->owner())
            ->assertSeeHtml('wire:model.live="sessionsSubject"')
            ->assertSeeHtml('<option value="'.$subject->id.'">ریاضی</option>')
            ->assertDontSee('درس شعبهٔ دیگر')
            ->assertSee('آزمایش نور')
            ->set('sessionsSubject', (string) $subject->id)
            ->assertSee('تمرین کسرها')
            ->assertSee('آزمون کسرها')
            ->assertDontSee('آزمایش نور')
            ->assertDontSee('جلسهٔ معارفه')
            ->call('showSessions', 'day', '2026-10-30')
            ->assertSee('تمرین کسرها')
            ->assertDontSee('آزمایش نور')
            ->assertSeeHtml('wire:click="showSessions(\'day\', \'2026-11-02\')"')
            ->set('sessionsSubject', (string) $remoteSubject->id)
            ->assertDontSee('جلسهٔ شعبهٔ دیگر')
            ->assertSee('جلسه‌ای در این روز نیست')
            ->set('sessionsSubject', '')
            ->assertSee('آزمایش نور')
            ->assertSee('جلسهٔ معارفه');
    }

    public function test_the_class_calendar_combines_delivery_mode_and_subject_filters(): void
    {
        $math = Subject::factory()->for(Tenant::primary())->create(['title' => 'ریاضی']);
        $science = Subject::factory()->for(Tenant::primary())->create(['title' => 'علوم']);
        $this->classSession('2026-10-30 08:00', subject: $math)->update(['title' => 'کسرهای حضوری']);
        $this->classSession('2026-10-30 10:00', subject: $math)->update(['title' => 'کسرهای آنلاین', 'mode' => ClassMode::Online]);
        $this->classSession('2026-10-30 12:00', subject: $science)->update(['title' => 'آزمایش نور', 'mode' => ClassMode::Online, 'kind' => SessionKind::Exam]);
        $this->classSession('2026-10-31 08:00', subject: $math);
        $this->classSession('2026-11-04 08:00', subject: $math)->update(['mode' => ClassMode::Online]);
        ClassSession::factory()->for(Classroom::factory()->for(Tenant::factory()), 'classroom')->for($math)->create([
            'title' => 'جلسهٔ شعبهٔ دیگر', 'mode' => ClassMode::Online, 'starts_at' => '2026-10-30 08:00', 'ends_at' => '2026-10-30 09:00',
        ]);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(SessionsRelationManager::class, $this->owner())
            ->assertSeeHtml('wire:model.live="sessionsMode"')
            ->assertSeeHtml('<option value="in_person">حضوری</option>')
            ->assertSeeHtml('<option value="online">آنلاین</option>')
            ->assertDontSee('ترکیبی')
            ->set('sessionsMode', ClassMode::InPerson->value)
            ->assertSee('کسرهای حضوری')
            ->assertDontSee('کسرهای آنلاین')
            ->set('sessionsMode', ClassMode::Online->value)
            ->assertSee('کسرهای آنلاین')
            ->assertSee('آزمایش نور')
            ->assertDontSee('کسرهای حضوری')
            ->assertDontSee('جلسهٔ شعبهٔ دیگر')
            ->set('sessionsSubject', (string) $math->id)
            ->assertSee('کسرهای آنلاین')
            ->assertDontSee('آزمایش نور')
            ->call('showSessions', 'day', '2026-10-30')
            ->assertSeeHtml('wire:click="showSessions(\'day\', \'2026-11-04\')"')
            ->set('sessionsMode', '')
            ->assertSee('کسرهای حضوری')
            ->assertDontSee('آزمایش نور');
    }

    public function test_a_session_links_to_its_page_in_a_new_tab_and_marks_itself_opened(): void
    {
        $session = $this->classSession('2026-10-30 10:00');
        $exam = $this->classSession('2026-10-30 13:00');
        $exam->update(['kind' => SessionKind::Exam]);
        $elsewhere = ClassSession::factory()->create(['classroom_id' => Classroom::factory()->for($this->west)->create()->id, 'starts_at' => '2026-10-30 12:00', 'ends_at' => '2026-10-30 13:00']);

        $this->actingInPanel($this->admin(), $this->west);
        $calendar = Livewire::test(SessionsRelationManager::class, $this->owner());

        foreach (['month', 'week', 'day'] as $view) {
            $calendar->call('showSessions', $view, '2026-10-30')
                ->assertSeeHtml('href="'.ClassSessionResource::getUrl('view', ['record' => $session]).'" target="_blank"')
                ->assertSeeHtml('href="'.ClassSessionResource::getUrl('view-exam', ['record' => $exam]).'" target="_blank"')
                ->assertDontSeeHtml('href="'.ClassSessionResource::getUrl('view', ['record' => $exam]).'"')
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
        return ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class];
    }
}
