<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                ->where('sections.0.lessons.0.title', $lesson->title)
                ->missing('sections.0.lessons.0.body')
                ->missing('sections.0.lessons.0.code'));

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
                ->where('navigation.0.title', 'دوره‌های من')
                ->where('navigation.0.url', route('courses.index')));

        $this->grantRole($user, $otherBranch, 'learner');
        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $otherBranch->id])
            ->get($openUrl)->assertRedirect($showUrl)
            ->assertSessionHas(ResolveCurrentTenant::SESSION_KEY, $otherBranch->id);
    }

    public function test_my_courses_lists_only_published_courses_with_an_active_enrollment_for_the_signed_in_user(): void
    {
        $learner = User::factory()->create();
        $otherLearner = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $publishedCourses = Course::factory()->state(['is_published' => true]);
        CourseEnrollment::factory()->for($course)->for($learner)->create();
        CourseEnrollment::factory()->for($publishedCourses)->for($otherLearner, 'user')->create();
        CourseEnrollment::factory()->for($publishedCourses)->for($learner)->create(['is_revoked' => true]);
        CourseEnrollment::factory()->for($publishedCourses)->for($learner)->create(['starts_at' => now()->addDay()]);
        CourseEnrollment::factory()->for($publishedCourses)->for($learner)->create(['expires_at' => now()->subDay()]);
        CourseEnrollment::factory()->for($learner)->create();
        $publishedCourses->create(['allows_self_enrollment' => true]);

        $this->actingAs($learner)->get(route('courses.index', ['all' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Course::Index', false)
                ->where('canShowAll', false)
                ->where('showAll', false)
                ->has('courses.data', 1)
                ->where('courses.data.0.url', route('courses.show', $course))
                ->where('courses.data.0.cover_url', null));
    }

    public function test_all_courses_uses_the_selected_branch_role_and_paginates_the_published_catalog(): void
    {
        Storage::fake('local');
        $image = UploadedFile::fake()->image('cover.png');
        $cover = $image->storeAs('courses/images', 'cover.png', 'local');
        $staffBranch = Tenant::factory()->create();
        $learnerBranch = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->grantRole($user, $staffBranch, DefaultRoles::INSTRUCTOR);
        $this->grantRole($user, $learnerBranch, DefaultRoles::LEARNER);

        $published = Course::factory()->state(['is_published' => true]);
        $unenrolled = $published->create(['cover_image' => $cover]);
        $enrolled = $published->create();
        CourseEnrollment::factory()->for($enrolled)->for($user)->create();
        $published->count(14)->create();
        $unpublished = Course::factory()->create(['is_published' => false, 'cover_image' => $cover]);

        $secondPage = route('courses.index', ['all' => 1, 'page' => 2]);
        $coverUrl = route('courses.cover', $unenrolled);
        $this->actingAs($user)->withSession([ResolveCurrentTenant::SESSION_KEY => $staffBranch->id]);
        $this->get(route('courses.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canShowAll', true)
                ->where('showAll', false)
                ->has('courses.data', 1)
                ->where('courses.data.0.enrolled', true));
        $this->get(route('courses.index', ['all' => 1]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showAll', true)
                ->has('courses.data', 15)
                ->where('courses.total', 16)
                ->where('courses.next_page_url', $secondPage));
        $this->get($secondPage)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showAll', true)
                ->where('courses.current_page', 2)
                ->has('courses.data', 1)
                ->where('courses.data.0.url', route('courses.show', $unenrolled))
                ->where('courses.data.0.enrolled', false)
                ->where('courses.data.0.cover_url', $coverUrl));
        $this->get($coverUrl)->assertOk()->assertStreamedContent($image->getContent());
        $this->get(route('courses.cover', $unpublished))->assertNotFound();

        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $learnerBranch->id])
            ->get(route('courses.index', ['all' => 1]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canShowAll', false)
                ->where('showAll', false)
                ->has('courses.data', 1)
                ->where('courses.data.0.url', route('courses.show', $enrolled)));
        $this->get($coverUrl)->assertForbidden();
    }

    public function test_search_filters_course_titles_within_the_selected_list_before_pagination(): void
    {
        $branch = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->grantRole($user, $branch, DefaultRoles::INSTRUCTOR);

        $published = Course::factory()->state(['is_published' => true]);
        $match = $published->create(['title' => 'آموزش طراحی']);
        $other = $published->create(['title' => 'آموزش برنامه‌نویسی']);
        CourseEnrollment::factory()->for($match)->for($user)->create();
        CourseEnrollment::factory()->for($other)->for($user)->create();

        for ($number = 1; $number <= 15; $number++) {
            $published->create(['title' => "طراحی پیشرفته {$number}"]);
        }
        Course::factory()->create(['title' => 'پیش‌نویس طراحی', 'is_published' => false]);

        $this->actingAs($user)->get(route('courses.index', ['search' => ' طراحی ']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('search', 'طراحی')
                ->has('courses.data', 1)
                ->where('courses.data.0.url', route('courses.show', $match)));

        $filters = ['all' => 1, 'search' => 'طراحی'];
        $secondPage = route('courses.index', [...$filters, 'page' => 2]);
        $this->get(route('courses.index', $filters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showAll', true)
                ->where('courses.total', 16)
                ->has('courses.data', 15)
                ->where('courses.next_page_url', $secondPage));
        $this->get($secondPage)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('search', 'طراحی')
                ->where('courses.current_page', 2)
                ->has('courses.data', 1)
                ->where('courses.data.0.url', route('courses.show', $match)));
    }

    public function test_learners_need_an_active_enrollment_to_view_a_published_course_cover(): void
    {
        Storage::fake('local');
        $image = UploadedFile::fake()->image('cover.png');
        $path = $image->storeAs('courses/images', 'cover.png', 'local');
        $course = Course::factory()->create(['is_published' => true, 'cover_image' => $path]);
        $learner = User::factory()->create();
        $this->grantRole($learner, Tenant::factory()->create(), DefaultRoles::LEARNER);
        $enrollment = CourseEnrollment::factory()->for($course)->for($learner)->create();
        $coverUrl = route('courses.cover', $course);

        $this->get($coverUrl)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($coverUrl)->assertForbidden();
        $this->actingAs($learner)->get(route('courses.index'))
            ->assertInertia(fn (Assert $page) => $page->where('courses.data.0.cover_url', $coverUrl));
        $this->get($coverUrl)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')->assertStreamedContent($image->getContent());

        $course->update(['is_published' => false]);
        $this->get($coverUrl)->assertNotFound();
        $course->update(['is_published' => true]);
        $enrollment->update(['is_revoked' => true]);
        $this->get($coverUrl)->assertForbidden();
    }

    public function test_course_syllabus_includes_lesson_codes_only_in_a_non_learner_branch_role(): void
    {
        $staffBranch = Tenant::factory()->create();
        $learnerBranch = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->grantRole($user, $staffBranch, DefaultRoles::INSTRUCTOR);
        $this->grantRole($user, $learnerBranch, DefaultRoles::LEARNER);

        $course = Course::factory()->create(['is_published' => true]);
        $section = CourseSection::factory()->for($course)->create();
        $lesson = CourseLesson::factory()->for($section, 'section')->create(['position' => 1, 'code' => 'LSN-FIRST']);
        $preview = CourseLesson::factory()->for($section, 'section')->create(['position' => 2, 'code' => 'LSN-PREVIEW', 'is_preview' => true]);
        $url = route('courses.show', $course);
        $lessonUrl = route('courses.lesson', [$course, $lesson]);

        $this->actingAs($user)->withSession([ResolveCurrentTenant::SESSION_KEY => $staffBranch->id])
            ->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sections.0.lessons.0.code', $lesson->code)
                ->where('sections.0.lessons.1.code', $preview->code)
                ->where('sections.0.lessons.0.url', null));
        $this->get($lessonUrl)->assertForbidden();

        CourseEnrollment::factory()->for($course)->for($user)->create();
        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $learnerBranch->id])
            ->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('sections.0.lessons.0.code')
                ->missing('sections.0.lessons.1.code')
                ->where('sections.0.lessons.0.url', $lessonUrl));
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
        $this->get(route('courses.index'))->assertInertia(fn (Assert $page) => $page
            ->where('courses.data.0.url', $showUrl));
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
