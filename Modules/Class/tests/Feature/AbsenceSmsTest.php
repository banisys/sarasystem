<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\LogSmsSender;
use App\Sms\SmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Class\Jobs\SendAbsenceNotice;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Subject;
use Modules\Class\Services\AbsenceNotices;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassMembers;
use RuntimeException;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * SMS to guardians when an attendee is marked absent (ADR 0029). The
 * session is Saturday 2026-10-31 (1405/08/09) at 08:00.
 */
class AbsenceSmsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private FakeSmsSender $sms;

    private Tenant $west;

    private ClassSession $session;

    private User $teacher;

    private User $amir;

    private User $sara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:10'));

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'شعبه غرب']);
        $this->west->makeCurrent();
        $this->roleWithPermissions('member', []);

        $classroom = Classroom::factory()->for($this->west)->create(['title' => 'هفتم الف']);
        [$this->amir, $this->sara, $this->teacher] = [$this->person('امیر محمدی'), $this->person('سارا جعفری'), $this->person('معلم')];
        app(ClassMembers::class)->add($classroom, ClassRole::factory()->for($primary)->attendee()->create(), [$this->amir->id, $this->sara->id], CarbonImmutable::parse('2026-10-01'));
        $this->session = ClassSession::factory()->create([
            'classroom_id' => $classroom->id, 'teacher_id' => $this->teacher->id,
            'subject_id' => Subject::factory()->for($primary)->create(['title' => 'ریاضی'])->id,
            'starts_at' => '2026-10-31 08:00', 'ends_at' => '2026-10-31 09:30',
        ]);

        // Amir: a father with SMS, a mother without, an uncle with no mobile. Sara: no guardian.
        $this->guardian($this->amir, '09121110000', true);
        $this->guardian($this->amir, '09122220000', false);
        $this->guardian($this->amir, null, true);
    }

    public function test_the_guardians_with_sms_on_hear_about_an_absence_once(): void
    {
        $this->record(['absent', 'absent']);

        $this->assertSame([[
            'mobile' => '09121110000',
            'message' => 'امیر محمدی در جلسه‌ی «هفتم الف» (ریاضی) شنبه 1405/08/09 ساعت 08:00 غایب بود. شعبه غرب - '.config('app.name'),
        ]], $this->sms->sent);
        $this->assertNotNull(Attendance::query()->where('user_id', $this->amir->id)->sole()->absence_notified_at);

        // Saved again, or marked present and absent again: no second SMS.
        $this->record(['absent', 'present']);
        $this->record(['present', 'present']);
        $this->record(['absent', 'present']);
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_the_notice_goes_on_the_queue_and_a_correction_meanwhile_sends_nothing(): void
    {
        Queue::fake();

        $this->record(['absent', 'present']);

        $this->assertSame([], $this->sms->sent);
        Queue::assertPushed(SendAbsenceNotice::class, 1);
        $job = Queue::pushedJobs()[SendAbsenceNotice::class][0]['job'];
        $this->assertSame(Attendance::query()->where('user_id', $this->amir->id)->sole()->id, $job->attendanceId);

        // Marked present before the queue ran the job.
        $this->record(['present', 'present']);
        $job->handle(app(AbsenceNotices::class));
        $this->assertSame([], $this->sms->sent);
    }

    public function test_a_failed_send_throws_so_the_job_is_tried_again(): void
    {
        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public function send(string $mobile, string $message): void
            {
                throw new RuntimeException('provider down');
            }
        });
        Queue::fake();
        $this->record(['absent', 'present']);
        $job = Queue::pushedJobs()[SendAbsenceNotice::class][0]['job'];

        try {
            $job->handle(app(AbsenceNotices::class));
            $this->fail('The job should fail.');
        } catch (RuntimeException) {
            $this->assertNull(Attendance::query()->where('user_id', $this->amir->id)->sole()->absence_notified_at);
        }

        $this->assertSame(3, $job->tries);
    }

    public function test_no_sms_for_other_statuses_or_when_turned_off(): void
    {
        $this->record(['late', 'present']);
        $this->record(['excused', 'present']);
        $this->assertSame([], $this->sms->sent);

        config(['class.attendance.absence_sms' => false]);
        $this->record(['absent', 'present']);
        $this->assertSame([], $this->sms->sent);
        $this->assertNull(Attendance::query()->where('user_id', $this->amir->id)->sole()->absence_notified_at);
    }

    public function test_the_log_driver_writes_to_the_sms_log(): void
    {
        Log::shouldReceive('channel')->once()->with('sms')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('[sms] to 09121110000: سلام');

        (new LogSmsSender)->send('09121110000', 'سلام');
    }

    /**
     * @param  array{string, string}  $statuses  Amir's and Sara's
     */
    private function record(array $statuses): void
    {
        app(AttendanceRegister::class)->record($this->session->fresh(), [
            $this->amir->id => ['status' => $statuses[0]],
            $this->sara->id => ['status' => $statuses[1]],
        ], $this->teacher);
    }

    private function guardian(User $ward, ?string $mobile, bool $receivesSms): void
    {
        Guardianship::query()->create([
            'guardian_id' => User::factory()->create(['mobile' => $mobile])->id,
            'ward_id' => $ward->id, 'relationship' => 'father', 'receives_sms' => $receivesSms,
        ]);
    }

    private function person(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->grantRole($user, $this->west, 'member');

        return $user;
    }
}
