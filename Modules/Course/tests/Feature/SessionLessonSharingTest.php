<?php

namespace Modules\Course\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Class\Database\Factories\ClassRoleFactory;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SessionLessonSharingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Module::isEnabled('Course') || ! Module::isEnabled('Class')) {
            $this->markTestSkipped('Session lesson sharing requires Course and Class.');
        }
    }

    public function test_teacher_shares_ordered_text_and_media_without_enrolling_the_learner(): void
    {
        Storage::fake('local');
        $branch = Tenant::factory()->create();
        $teacher = $this->branchUser($branch);
        $learner = $this->branchUser($branch);
        $session = ClassSession::factory()->for(Classroom::factory()->for($branch))->taughtBy($teacher)->create(['summary' => 'مطالب قبلی']);
        $this->addMember($session, $learner, ClassRole::factory()->attendee());
        $section = CourseSection::factory()->for(Course::factory()->state(['is_published' => true]))->create();
        $lesson = CourseLesson::factory()->for($section, 'section')->create([
            'code' => 'LSN-VIDEO',
            'body_html' => '<p>متن اشتراکی درس</p><script>alert(1)</script>',
        ]);
        $otherLesson = CourseLesson::factory()->for($section, 'section')->create(['code' => 'LSN-TEXT']);
        $video = $lesson->addMediaFromString(pack('Na4a4Na4a4', 24, 'ftyp', 'mp42', 0, 'mp42', 'isom'))
            ->usingFileName('example.mp4')->toMediaCollection('video');
        $file = $lesson->addMediaFromString('Course handout')->usingFileName('handout.txt')->toMediaCollection('attachments');
        $sessionUrl = route('my-classes.session', ['session' => $session, 'tab' => 'content']);
        $videoUrl = route('my-classes.lessons.file', [$session, $lesson, $video]);
        $fileUrl = route('my-classes.lessons.file', [$session, $lesson, $file]);

        $this->inBranch($teacher, $branch)->put(route('my-classes.lessons.update', $session), [
            'lesson_codes' => [$otherLesson->code, $lesson->code],
        ])->assertRedirect($sessionUrl)->assertSessionHasNoErrors();

        $this->assertSame([$otherLesson->id, $lesson->id], $session->refresh()->shared_lesson_ids);
        $this->assertSame('مطالب قبلی', $session->summary);
        $this->assertSame(SessionStatus::Scheduled, $session->status);
        $this->assertFalse($session->isUntouched());
        $this->inBranch($learner, $branch)->get($sessionUrl)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Class::Session', false)
                ->where('sharedLessons.can_manage', false)
                ->has('sharedLessons.lessons', 2)
                ->where('sharedLessons.lessons.0.lesson.id', $otherLesson->id)
                ->where('sharedLessons.lessons.1.lesson.bodyHtml', '<p>متن اشتراکی درس</p>')
                ->where('sharedLessons.lessons.1.video', $videoUrl)
                ->where('sharedLessons.lessons.1.attachments.0.url', $fileUrl)
                ->missing('sharedLessons.lessons.1.examUrl'));
        $this->get($videoUrl)->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get($fileUrl)->assertOk()->assertDownload('handout.txt')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('my-classes.lessons.file', [$session, $otherLesson, $file]))->assertNotFound();
        $this->get(route('courses.lesson.file', [$section->course, $lesson, $file]))->assertForbidden();
        $this->get(route('courses.lesson', [$section->course, $otherLesson]))->assertForbidden();
        $this->put(route('courses.lesson.complete', [$section->course, $lesson]))->assertForbidden();
        $this->assertDatabaseMissing('course_enrollments', ['course_id' => $section->course_id, 'user_id' => $learner->id]);
    }

    #[TestWith(['پشتیبان'])]
    #[TestWith(['سرپرست'])]
    public function test_non_attendee_staff_can_replace_and_remove_session_lessons(string $role): void
    {
        Storage::fake('local');
        $branch = Tenant::factory()->create();
        $staff = $this->branchUser($branch);
        $session = ClassSession::factory()->for(Classroom::factory()->for($branch))->create();
        $this->addMember($session, $staff, ClassRole::factory()->state(['title' => $role]));
        $lesson = $this->publishedLesson();
        $file = $lesson->addMediaFromString('Course handout')->usingFileName('handout.txt')->toMediaCollection('attachments');
        $update = route('my-classes.lessons.update', $session);
        $fileUrl = route('my-classes.lessons.file', [$session, $lesson, $file]);

        $this->inBranch($staff, $branch)->put($update, ['lesson_codes' => [$lesson->code]])->assertRedirect();
        $this->get(route('my-classes.session', $session))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sharedLessons.can_manage', true)
            ->where('sharedLessons.lessons.0.lesson.code', $lesson->code));
        $this->put($update, ['lesson_codes' => []])->assertRedirect();

        $this->assertNull($session->refresh()->shared_lesson_ids);
        $this->get($fileUrl)->assertNotFound();
    }

    public function test_session_files_require_the_right_session_roster_and_branch(): void
    {
        Storage::fake('local');
        $branch = Tenant::factory()->create();
        $otherBranch = Tenant::factory()->create();
        $learner = $this->branchUser($branch);
        $outsider = $this->branchUser($branch);
        $this->grantRole($learner, $otherBranch, 'member');
        $lesson = $this->publishedLesson();
        $session = ClassSession::factory()->for(Classroom::factory()->for($branch))->create(['shared_lesson_ids' => [$lesson->id]]);
        $otherSession = ClassSession::factory()->for($session->classroom)->create(['shared_lesson_ids' => null]);
        $learnerRole = ClassRole::factory()->attendee()->state(['records_content' => true]);
        $this->addMember($session, $learner, $learnerRole);
        $file = $lesson->addMediaFromString('Course handout')->usingFileName('handout.txt')->toMediaCollection('attachments');
        $fileUrl = route('my-classes.lessons.file', [$session, $lesson, $file]);
        $updateUrl = route('my-classes.lessons.update', $session);

        $this->inBranch($learner, $branch)->put($updateUrl, ['lesson_codes' => []])->assertForbidden();
        $this->get(route('my-classes.lessons.file', [$otherSession, $lesson, $file]))->assertNotFound();
        $this->inBranch($outsider, $branch)->get($fileUrl)->assertNotFound();
        $this->inBranch($learner, $otherBranch)->get($fileUrl)->assertNotFound();
        $this->put($updateUrl, ['lesson_codes' => []])->assertNotFound();
        $this->assertSame([$lesson->id], $branch->execute(fn () => $session->refresh()->shared_lesson_ids));
    }

    public function test_a_code_from_an_unpublished_course_does_not_change_the_session_lessons(): void
    {
        $branch = Tenant::factory()->create();
        $teacher = $this->branchUser($branch);
        $published = $this->publishedLesson();
        $draft = CourseLesson::factory()->create();
        $session = ClassSession::factory()->for(Classroom::factory()->for($branch))->taughtBy($teacher)
            ->create(['shared_lesson_ids' => [$published->id]]);

        $this->inBranch($teacher, $branch)->putJson(route('my-classes.lessons.update', $session), [
            'lesson_codes' => [$draft->code],
        ])->assertUnprocessable()->assertJsonValidationErrors('lesson_codes');

        $this->assertSame([$published->id], $session->refresh()->shared_lesson_ids);
    }

    public function test_exam_and_cancelled_sessions_refuse_new_lesson_sharing(): void
    {
        $branch = Tenant::factory()->create();
        $teacher = $this->branchUser($branch);
        $classroom = Classroom::factory()->for($branch)->create();
        $exam = ClassSession::factory()->for($classroom)->taughtBy($teacher)->create(['kind' => SessionKind::Exam]);
        $cancelled = ClassSession::factory()->for($classroom)->taughtBy($teacher)->create(['status' => SessionStatus::Cancelled]);
        $data = ['lesson_codes' => [$this->publishedLesson()->code]];

        $this->inBranch($teacher, $branch)->put(route('my-classes.lessons.update', $exam), $data)->assertNotFound();
        $this->put(route('my-classes.lessons.update', $cancelled), $data)->assertForbidden();
    }

    private function publishedLesson(): CourseLesson
    {
        return CourseLesson::factory()->for(CourseSection::factory()->for(Course::factory()->state(['is_published' => true])), 'section')->create();
    }

    private function addMember(ClassSession $session, User $user, ClassRoleFactory $role): void
    {
        ClassMember::factory()->for($user)
            ->for($role->state(['tenant_id' => $session->tenant_id]), 'classRole')
            ->create(['classroom_id' => $session->classroom_id, 'tenant_id' => $session->tenant_id]);
    }

    private function branchUser(Tenant $branch): User
    {
        $branch->makeCurrent();
        $user = User::factory()->create();
        $this->grantRole($user, $branch, 'member');

        return $user;
    }

    private function inBranch(User $user, Tenant $branch): static
    {
        return $this->actingAs($user)->withSession([ResolveCurrentTenant::SESSION_KEY => $branch->id]);
    }
}
