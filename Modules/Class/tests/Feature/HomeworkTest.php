<?php

namespace Modules\Class\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Services\ClassMembers;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Handing in homework and grading it (ADR 0030). The session is Saturday
 * 2026-10-31; its homework is due Monday 2026-11-02 (1405/08/11), out of 20.
 */
class HomeworkTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Classroom $classroom;

    private ClassSession $session;

    private User $teacher;

    private User $amir;

    private User $sara;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00'));

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->roleWithPermissions('member', []);

        $this->classroom = Classroom::factory()->for($this->west)->create();
        [$this->amir, $this->sara, $this->teacher] = [$this->person('امیر محمدی'), $this->person('سارا جعفری'), $this->person('معلم')];
        $members = app(ClassMembers::class);
        $members->add($this->classroom, ClassRole::factory()->for($primary)->attendee()->create(), [$this->amir->id, $this->sara->id], CarbonImmutable::parse('2026-10-01'));
        $members->add($this->classroom, ClassRole::factory()->for($primary)->teacher()->create(), [$this->teacher->id]);

        $this->session = ClassSession::factory()->create([
            'classroom_id' => $this->classroom->id, 'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-10-31 08:00', 'ends_at' => '2026-10-31 09:30',
            'homework' => 'تمرین ۱ تا ۵ صفحه‌ی ۲۰', 'homework_due_on' => '2026-11-02', 'homework_max_score' => 20,
        ]);
    }

    public function test_a_learner_hands_in_an_answer_and_files_and_changes_them_until_graded(): void
    {
        $this->as($this->amir, route('my-classes.session', $this->session))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canSubmit', true)
            ->where('mySubmission', null)
            ->where('submissions', null));

        $this->as($this->amir, route('my-classes.homework', $this->session), 'post', ['answer' => '', 'files' => []])
            ->assertSessionHasErrors(['form' => 'پاسخ را بنویسید یا فایلی بفرستید.']);

        $this->as($this->amir, route('my-classes.homework', $this->session), 'post', [
            'answer' => 'جواب تمرین‌ها', 'files' => [UploadedFile::fake()->create('page1.jpg', 200, 'image/jpeg'), UploadedFile::fake()->create('page2.jpg', 200, 'image/jpeg')],
        ])->assertSessionHasNoErrors();

        $submission = HomeworkSubmission::query()->sole();
        $this->assertSame(['page1.jpg', 'page2.jpg'], array_column($submission->fileLinks(), 'name'));
        $this->assertFalse($submission->isLate());

        // A day after the due day: changed, now late, one file less.
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00'));
        $this->as($this->amir, route('my-classes.homework', $this->session), 'post', [
            'answer' => 'جواب کامل', 'remove_media_ids' => [$submission->getFirstMedia(HomeworkSubmission::FILES_COLLECTION)->id],
        ])->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame('جواب کامل', $submission->answer);
        $this->assertSame(['page2.jpg'], array_column($submission->fileLinks(), 'name'));
        $this->assertTrue($submission->isLate());

        $submission->update(['score' => 18, 'graded_at' => now()]);
        $this->as($this->amir, route('my-classes.homework', $this->session), 'post', ['answer' => 'باز هم'])
            ->assertSessionHasErrors(['form' => 'تکلیف شما نمره گرفته و دیگر تغییر نمی‌کند.']);
    }

    public function test_only_the_attendees_hand_in_and_late_work_may_be_refused(): void
    {
        $this->as($this->teacher, route('my-classes.homework', $this->session), 'post', ['answer' => 'x'])
            ->assertSessionHasErrors(['form' => 'این تکلیف برای شما نیست.']);

        config(['class.homework.accepts_late' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-11-03 00:01'));
        $this->as($this->amir, route('my-classes.homework', $this->session), 'post', ['answer' => 'x'])
            ->assertSessionHasErrors(['form' => 'مهلت تحویل این تکلیف گذشته است.']);
    }

    public function test_the_teacher_grades_and_the_learner_and_guardian_see_it(): void
    {
        $handedIn = HomeworkSubmission::factory()->create(['session_id' => $this->session->id, 'user_id' => $this->amir->id]);

        $this->as($this->teacher, route('my-classes.session', $this->session))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('submissions', 2)
            ->where('submissions.0.name', 'امیر محمدی')
            ->where('submissions.0.submission.id', $handedIn->id)
            ->where('submissions.1.submission', null));

        $this->as($this->teacher, route('my-classes.grades', $this->session), 'post', ['grades' => [['id' => $handedIn->id, 'score' => 21]]])
            ->assertSessionHasErrors('form');
        $this->as($this->teacher, route('my-classes.grades', $this->session), 'post', ['grades' => [['id' => $handedIn->id, 'score' => '18.5', 'feedback' => 'خوب بود']]])
            ->assertSessionHasNoErrors();

        $handedIn->refresh();
        $this->assertSame('18.50', $handedIn->score);
        $this->assertSame($this->teacher->id, $handedIn->graded_by_id);

        $this->as($this->amir, route('my-classes.index', ['date' => '2026-10-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.days.0.sessions.0.my_homework', 'نمره‌ی تکلیف: 18.5 از 20'));

        $father = $this->person('محمد محمدی');
        Guardianship::query()->create(['guardian_id' => $father->id, 'ward_id' => $this->amir->id, 'relationship' => 'father']);
        $this->as($father, route('my-classes.session', ['session' => $this->session, 'for' => $this->amir->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mySubmission.score', '18.5')
            ->where('mySubmission.feedback', 'خوب بود')
            ->where('canSubmit', false)
            ->where('submissions', null));

        // A learner can't grade, and grades are for this session's hand-ins only.
        $this->as($this->sara, route('my-classes.grades', $this->session), 'post', ['grades' => [['id' => $handedIn->id, 'score' => 20]]])
            ->assertSessionHasErrors('form');
        $other = HomeworkSubmission::factory()->create(['session_id' => ClassSession::factory()->create(['classroom_id' => $this->classroom->id, 'homework' => 'x'])->id]);
        $this->as($this->teacher, route('my-classes.grades', $this->session), 'post', ['grades' => [['id' => $other->id, 'score' => 20]]])
            ->assertSessionHasErrors(['form' => 'این تکلیف از این جلسه نیست.']);
    }

    public function test_the_admin_panel_grades_hand_ins(): void
    {
        // The sessions tab lists sessions from today on.
        $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00'));
        $handedIn = HomeworkSubmission::factory()->create(['session_id' => $this->session->id, 'user_id' => $this->sara->id]);
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->west);

        $this->actingInPanel($admin, $this->west);
        $tab = Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class])
            ->assertTableColumnStateSet('homework_submissions_count', 'تحویل 1', $this->session)
            ->mountAction(TestAction::make('grading')->table($this->session));

        $rows = $tab->instance()->mountedActions[0]['data']['grades'];
        $tab->setActionData(['grades' => array_map(fn (array $row): array => [...$row, 'score' => 17, 'feedback' => 'دقت بیشتر'], $rows)])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('17.00', $handedIn->fresh()->score);
        $this->assertSame('دقت بیشتر', $handedIn->fresh()->feedback);
    }

    private function person(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->grantRole($user, $this->west, 'member');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function as(User $user, string $url, string $method = 'get', array $data = []): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $this->west->id])
            ->{$method}($url, $data);
    }
}
