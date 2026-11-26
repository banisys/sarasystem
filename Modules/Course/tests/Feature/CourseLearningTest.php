<?php

namespace Modules\Course\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseCategory;
use Modules\Course\Models\CourseCompletion;
use Modules\Course\Models\CourseEnrollment;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseLearningTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_public_catalog_filters_published_courses_and_shows_only_the_syllabus(): void
    {
        $branch = Tenant::factory()->create();
        $category = CourseCategory::factory()->create();
        $course = Course::factory()->for($category, 'category')->create(['title' => 'Introduction to training', 'is_published' => true]);
        Course::factory()->create(['title' => 'More training', 'is_published' => true]);
        Course::factory()->for($category, 'category')->create(['title' => 'Training draft']);
        $additionalCategory = CourseCategory::factory()->create();
        $course->additionalCategories()->attach($additionalCategory);
        $lesson = CourseLesson::factory()->for(CourseSection::factory()->for($course), 'section')->create();

        $filters = ['category' => $category->id, 'search' => 'training'];
        $this->get(route('courses.catalog.branch', ['branch' => $branch->slug, ...$filters]))
            ->assertRedirect(route('courses.catalog', $filters));
        $this->get(route('courses.catalog', $filters))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Course::Catalog', false)
                ->has('courses.data', 1)->where('courses.data.0.id', $course->id));

        $this->get(route('courses.catalog', ['category' => $additionalCategory->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('courses.data', 1)->where('courses.data.0.id', $course->id));

        $this->get(route('courses.catalog.branch', [$branch->slug, $course]))
            ->assertRedirect(route('courses.catalog', $course));
        $this->get(route('courses.catalog', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Course::CatalogCourse', false)
                ->where('sections.0.lessons.0.title', $lesson->title)->missing('sections.0.lessons.0.body'));

        $this->get(route('courses.lesson', [$course, $lesson]))->assertRedirect(route('login'));
    }

    public function test_a_global_course_opens_without_a_branch_or_changing_the_selected_branch(): void
    {
        $branch = Tenant::factory()->create();
        $otherBranch = Tenant::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $user = User::factory()->create();
        $showUrl = route('courses.show', $course);
        $openUrl = route('courses.catalog.open', [$branch->slug, $course]);

        $this->get($openUrl)->assertRedirect(route('login'));
        $this->actingAs($user)->get($showUrl)->assertOk()->assertSessionMissing(ResolveCurrentTenant::SESSION_KEY)
            ->assertInertia(fn (Assert $page) => $page->where('tenant.current', null)
                ->where('navigation.0.url', route('courses.index')));

        $this->grantRole($user, $otherBranch, 'learner');
        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $otherBranch->id])
            ->get($openUrl)->assertRedirect($showUrl)
            ->assertSessionHas(ResolveCurrentTenant::SESSION_KEY, $otherBranch->id);
    }

    public function test_enrollment_unlocks_content_and_completion_is_idempotent_and_individual(): void
    {
        $learner = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true, 'allows_self_enrollment' => true]);
        $section = CourseSection::factory()->for($course)->create();
        $lesson = CourseLesson::factory()->for($section, 'section')->create([
            'position' => 3,
            'body_html' => '<p onclick="alert(1)"><strong>متن قالب‌بندی‌شده</strong></p>',
        ]);
        $preview = CourseLesson::factory()->for($section, 'section')->create(['is_preview' => true, 'position' => 1]);

        $previewUrl = route('courses.lesson', [$course, $preview]);
        $lessonUrl = route('courses.lesson', [$course, $lesson]);
        $finishUrl = route('courses.lesson.complete', [$course, $lesson]);
        $showUrl = route('courses.show', $course);

        $this->actingAs($learner)->get($showUrl)
            ->assertInertia(fn (Assert $page) => $page->component('Course::Show', false)
                ->where('sections.0.lessons.0.url', $previewUrl)->where('sections.0.lessons.1.url', null)
                ->missing('sections.0.lessons.1.body'));
        $this->get($previewUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Course::Lesson', false)->where('lesson.body', $preview->body)->where('next', null));
        $this->put(route('courses.lesson.complete', [$course, $preview]))->assertForbidden();
        $this->get($lessonUrl)->assertForbidden();

        for ($repeat = 0; $repeat < 2; $repeat++) {
            $this->post(route('courses.enroll', $course))->assertRedirect($showUrl);
            $this->put($finishUrl)->assertRedirect($lessonUrl);
        }

        $this->assertDatabaseCount(CourseEnrollment::class, 1);
        $this->assertDatabaseCount(CourseCompletion::class, 1);
        $this->get(route('courses.index', ['mine' => 1]))->assertInertia(fn (Assert $page) => $page
            ->where('courses.data.0.url', $showUrl)->where('courses.data.0.enrolled', true));
        $this->get($showUrl)->assertInertia(fn (Assert $page) => $page->where('sections.0.lessons.1.completed', true));
        $this->get($lessonUrl)->assertInertia(fn (Assert $page) => $page->where('previous', $previewUrl)
            ->where('lesson.bodyHtml', '<p><strong>متن قالب‌بندی‌شده</strong></p>'));

        $this->actingAs($other)->post(route('courses.enroll', $course))->assertRedirect($showUrl);
        $this->get($lessonUrl)->assertInertia(fn (Assert $page) => $page->where('completed', false));
    }

    public function test_manual_enrollment_windows_and_revocation_are_enforced_for_files_and_rejoining(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->grantRole($user, $tenant, 'learner');
        [$course, $lesson, $otherLesson, $enrollment] = $tenant->execute(function () use ($user): array {
            $course = Course::factory()->create(['is_published' => true]);
            $section = CourseSection::factory()->for($course)->create();
            $lesson = CourseLesson::factory()->for($section, 'section')->create();
            $otherLesson = CourseLesson::factory()->for($section, 'section')->create();
            $enrollment = CourseEnrollment::factory()->for($course)->for($user)->create(['starts_at' => now()->addHour()]);

            return [$course, $lesson, $otherLesson, $enrollment];
        });
        $file = $lesson->addMediaFromString('private file')->usingFileName('attachment.txt')->toMediaCollection('attachments');
        $fileUrl = route('courses.lesson.file', [$course, $lesson, $file->id]);
        $audio = Process::run([
            'ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'anullsrc=r=8000:cl=mono', '-t', '1', '-f', 'wav', 'pipe:1',
        ])->throw()->output();
        $voice = $lesson->addMediaFromString($audio)->usingFileName('voice.wav')->toMediaCollection('audio');
        $audioUrl = route('courses.lesson.file', [$course, $lesson, $voice->id]);
        $this->actingAs($user)->get($fileUrl)->assertForbidden();
        $this->get($audioUrl)->assertForbidden();
        $this->post(route('courses.enroll', $course))->assertForbidden();

        $enrollment->update(['starts_at' => now()->subHour()]);
        $this->get(route('courses.lesson', [$course, $lesson]))->assertInertia(fn (Assert $page) => $page->where('audio', $audioUrl));
        $this->get($audioUrl)->assertOk()->assertHeader('Content-Type', $voice->mime_type)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get($fileUrl)->assertDownload('attachment.txt')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('courses.lesson.file', [$course, $otherLesson, $file->id]))->assertNotFound();

        $enrollment->update(['expires_at' => now()->subMinute()]);
        $this->get($fileUrl)->assertForbidden();

        $course->update(['allows_self_enrollment' => true]);
        $enrollment->update(['expires_at' => null, 'is_revoked' => true]);
        $this->get($fileUrl)->assertForbidden();
        $this->post(route('courses.enroll', $course))->assertForbidden();
        $this->assertTrue($enrollment->refresh()->is_revoked);
    }

    public function test_drafts_and_lessons_from_another_course_are_hidden_on_direct_urls(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->grantRole($user, $tenant, 'learner');

        $foreign = $branch->execute(fn () => CourseLesson::factory()->create(['is_preview' => true]));
        [$course, $otherLesson] = $tenant->execute(fn (): array => [
            Course::factory()->create(['is_published' => true, 'allows_self_enrollment' => true]),
            CourseLesson::factory()->create(['is_preview' => true]),
        ]);

        $this->actingAs($user)->get(route('courses.show', $foreign->course_id))->assertNotFound();
        $this->post(route('courses.enroll', $foreign->course_id))->assertNotFound();
        foreach ([$foreign, $otherLesson] as $lesson) {
            $this->get(route('courses.lesson', [$course, $lesson]))->assertNotFound();
            $this->put(route('courses.lesson.complete', [$course, $lesson]))->assertNotFound();
        }
        $this->get(route('courses.show', $otherLesson->course_id))->assertNotFound();
        $this->assertDatabaseCount(CourseCompletion::class, 0);
    }
}
