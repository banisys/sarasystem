<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Pages\CreateCourse;
use Modules\Course\Filament\Resources\Courses\Pages\CreateCourseLesson;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourse;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourseLesson;
use Modules\Course\Filament\Resources\Courses\Pages\ListCourses;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseLessons;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseSections;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\InstructorsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseCategory;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Modules\Course\Models\CourseTeacher;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_head_office_officers_create_global_courses_and_manage_their_content(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $branch = Tenant::factory()->create();
        $existingCourse = $branch->execute(fn (): Course => Course::factory()->create());
        $this->actAsEducationOfficer($tenant);
        $category = CourseCategory::factory()->create();
        $additionalCategory = CourseCategory::factory()->create();
        $instructor = User::factory()->create();
        $inactiveInstructor = User::factory()->create();
        $this->grantRole($instructor, $branch, DefaultRoles::INSTRUCTOR);
        $this->grantRole($inactiveInstructor, $tenant, DefaultRoles::INSTRUCTOR);
        CourseTeacher::factory()->for($inactiveInstructor)->create(['is_active' => false]);

        $component = Livewire::test(CreateCourse::class)->fillForm([
            'title' => 'آشنایی با آموزش سازمانی',
            'category_ids' => [999999],
            'instructor_ids' => [$inactiveInstructor->id],
        ]);
        $component->call('create')->assertHasFormErrors(['category_ids.0', 'instructor_ids.0']);
        $this->assertDatabaseCount(Course::class, 1);
        $component->fillForm([
            'category_ids' => [$category->id, $additionalCategory->id],
            'instructor_ids' => [$instructor->id],
            'type' => 'package',
            'capacity' => 25,
            'description_html' => '<p dir="rtl">مقدمهٔ <strong>دوره</strong></p>',
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'has_prepayment' => true,
            'prepayment_amount' => 50000,
        ])->call('create')->assertHasNoFormErrors();

        $course = Course::query()->whereKeyNot($existingCourse->id)->sole();
        $this->assertSame($category->id, $course->course_category_id);
        $this->assertSame($additionalCategory->id, $course->additionalCategories()->sole()->id);
        $this->assertSame($instructor->id, $course->instructors()->sole()->user_id);
        $this->assertStringStartsWith('CRS-', $course->code);
        $this->assertSame('package', $course->type);
        $this->assertSame(25, $course->capacity);
        $this->assertSame('مقدمهٔ دوره', $course->description);
        $this->assertSame('<p dir="rtl">مقدمهٔ <strong>دوره</strong></p>', $course->description_html);
        $this->assertSame('2026-12-01', $course->starts_on);
        $this->assertSame('2026-12-31', $course->ends_on);
        $this->assertSame(50000, $course->prepayment_amount);
        Livewire::test(ListCourses::class)->assertCanSeeTableRecords([$course, $existingCourse]);
        $this->get(CourseResource::getUrl('edit', ['record' => $existingCourse], tenant: $tenant))->assertOk();
        $this->get(CourseResource::getUrl('edit', ['record' => $course], tenant: $tenant))
            ->assertOk()->assertSee('اطلاعات کلی')->assertDontSee('relationManagerTabs');

        Livewire::test(EditCourse::class, ['record' => $course->id])
            ->assertFormSet(['category_ids' => [(string) $category->id, (string) $additionalCategory->id], 'has_prepayment' => true])
            ->fillForm(['category_ids' => [$additionalCategory->id, $category->id], 'has_prepayment' => false])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($additionalCategory->id, $course->refresh()->course_category_id);
        $this->assertSame($category->id, $course->additionalCategories()->sole()->id);
        $this->assertNull($course->prepayment_amount);
        $this->assertSame($instructor->id, $course->instructors()->sole()->user_id);

        Livewire::test(SectionsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => ManageCourseSections::class])
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'شروع', 'position' => 1])
            ->assertHasNoActionErrors();

        $section = $course->sections()->sole();
        $unrelated = CourseSection::factory()->create();
        $createLesson = Livewire::test(CreateCourseLesson::class, ['course' => $course->id]);
        $createLesson->fillForm(['title' => 'درس اول', 'course_section_id' => $unrelated->id])
            ->call('create')
            ->assertHasFormErrors(['course_section_id']);

        $createLesson->fillForm([
            'course_section_id' => $section->id,
            'body_html' => '<p><strong>متن درس</strong></p>',
            'position' => 1,
            'attachments' => [UploadedFile::fake()->create('guide.txt', 1, 'text/plain')],
        ])->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(CourseResource::getUrl('lessons', ['record' => $course], tenant: $tenant));

        $lesson = $section->lessons()->sole();
        $editUrl = CourseResource::getUrl('edit-lesson', ['course' => $course, 'record' => $lesson], tenant: $tenant);
        $this->get($editUrl)->assertOk()->assertSee('اطلاعات درس');
        Livewire::test(EditCourseLesson::class, ['course' => $course->id, 'record' => $lesson->id])
            ->fillForm(['title' => 'درس ویرایش‌شده'])
            ->call('save')->assertHasNoFormErrors()
            ->assertRedirect(CourseResource::getUrl('lessons', ['record' => $course], tenant: $tenant));
        $this->assertSame('درس ویرایش‌شده', $lesson->refresh()->title);
        $this->get(CourseResource::getUrl('edit-lesson', ['course' => $unrelated->course, 'record' => $lesson], tenant: $tenant))
            ->assertNotFound();

        $this->assertSame('<p><strong>متن درس</strong></p>', $lesson->body_html);
        $attachment = $lesson->getMedia('attachments')->sole();
        $this->assertSame('guide.txt', $attachment->file_name);
        Storage::disk('local')->assertExists($attachment->getPathRelativeToRoot());
    }

    public function test_course_list_links_to_manage_sections_for_the_selected_course(): void
    {
        $tenant = Tenant::factory()->create();
        $anotherCourse = Course::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $course = Course::factory()->create(['title' => 'دورهٔ آموزش سازمانی']);
        $section = CourseSection::factory()->for($course)->create(['title' => 'آشنایی با فرایندها']);
        CourseSection::factory()->create(['title' => 'سرفصل دورهٔ دیگر']);
        $url = CourseResource::getUrl('sections', ['record' => $course], tenant: $tenant);

        Livewire::test(ListCourses::class)
            ->assertActionHasUrl(TestAction::make('sections')->table($course), $url);
        Livewire::withoutLazyLoading();
        $this->get($url)->assertOk()->assertSee(['سرفصل‌ها', $course->title, $section->title])->assertDontSee('سرفصل دورهٔ دیگر');
        $this->get(CourseResource::getUrl('sections', ['record' => $anotherCourse], tenant: $tenant))->assertOk();

        Livewire::test(SectionsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => ManageCourseSections::class])
            ->callAction(TestAction::make('edit')->table($section), data: ['title' => 'شناخت فرایندها', 'position' => 2])
            ->assertHasNoActionErrors();
        $this->assertSame('شناخت فرایندها', $section->refresh()->title);

        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $tenant);
        $this->grantRole($viewer, $tenant, $this->roleWithPermissions('course-viewer', ['ViewAny:Course', 'View:Course'])->name);
        $this->actingInPanel($viewer, $tenant);
        Livewire::test(ListCourses::class)->assertActionHidden(TestAction::make('sections')->table($course));
        $this->get($url)->assertForbidden();
    }

    public function test_course_lessons_link_opens_only_the_selected_courses_lessons(): void
    {
        $tenant = Tenant::factory()->create();
        $anotherCourse = Course::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $course = Course::factory()->create();
        $lesson = CourseLesson::factory()->for(CourseSection::factory()->for($course), 'section')
            ->create(['title' => 'درس آموزشی این دوره']);
        $otherLesson = CourseLesson::factory()->create(['title' => 'درس آموزشی دورهٔ دیگر']);
        $url = CourseResource::getUrl('lessons', ['record' => $course], tenant: $tenant);
        $createUrl = CourseResource::getUrl('create-lesson', ['course' => $course], tenant: $tenant);
        $legacyUrl = url("/cpadmin/{$tenant->slug}/courses/{$course->getRouteKey()}/activities");

        Livewire::test(ListCourses::class)
            ->assertActionHasUrl(TestAction::make('lessons')->table($course), $url);
        Livewire::withoutLazyLoading();
        $this->get($legacyUrl)->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee(['درس‌ها', $course->title, $lesson->title])
            ->assertDontSee($otherLesson->title)
            ->assertSee($createUrl);
        $this->get($createUrl)->assertOk()->assertSee(['ایجاد درس', $course->title, 'متن درس']);
        Livewire::test(LessonsRelationManager::class, ['ownerRecord' => $anotherCourse, 'pageClass' => ManageCourseLessons::class])
            ->assertActionDisabled(TestAction::make('create')->table());
        $this->get(CourseResource::getUrl('lessons', ['record' => $anotherCourse], tenant: $tenant))->assertOk();

        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $tenant);
        $this->grantRole($viewer, $tenant, $this->roleWithPermissions('course-viewer', ['ViewAny:Course', 'View:Course'])->name);
        $this->actingInPanel($viewer, $tenant);
        Livewire::test(ListCourses::class)->assertActionHidden(TestAction::make('lessons')->table($course));
        $this->get($url)->assertForbidden();
        $this->get($createUrl)->assertForbidden();
    }

    public function test_lesson_can_contain_video_and_attachments_but_rejects_more_than_five_files(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $section = CourseSection::factory()->create();
        $files = array_map(fn (int $number): UploadedFile => UploadedFile::fake()->create("guide-{$number}.txt", 1, 'text/plain'), range(1, 6));

        $video = fn (int $seconds): string => Process::run([
            'ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'color=s=16x16:r=1', '-t', (string) $seconds,
            '-c:v', 'mpeg4', '-movflags', 'frag_keyframe+empty_moov', '-f', 'mp4', 'pipe:1',
        ])->throw()->output();

        $audio = fn (int $seconds): string => Process::run([
            'ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', 'anullsrc=r=8000:cl=mono', '-t', (string) $seconds,
            '-c:a', 'pcm_s16le', '-f', 'wav', 'pipe:1',
        ])->throw()->output();

        $form = Livewire::test(CreateCourseLesson::class, ['course' => $section->course_id])
            ->assertFormFieldDoesNotExist('duration_minutes')
            ->assertFormFieldVisible('video')
            ->assertFormFieldVisible('attachments')
            ->fillForm([
                'title' => 'درس نمونه',
                'course_section_id' => $section->id,
                'video' => UploadedFile::fake()->createWithContent('lesson.mp4', $video(61))->mimeType('video/mp4'),
                'audio' => UploadedFile::fake()->createWithContent('voice.wav', $audio(3))->mimeType('audio/wav'),
                'attachments' => $files,
            ])
            ->call('create')->assertHasFormErrors(['attachments' => 'max']);
        $this->assertDatabaseCount(CourseLesson::class, 0);

        $form->fillForm(['attachments' => array_slice($files, 0, 5)])
            ->call('create')->assertHasNoFormErrors();

        $lesson = $section->lessons()->sole();
        $this->assertTrue($lesson->hasMedia('video'));
        $this->assertSame(2, $lesson->duration_minutes);
        $this->assertTrue($lesson->hasMedia('audio'));
        $this->assertSame(3, $lesson->audio_duration_seconds);
        $this->assertCount(5, $lesson->getMedia('attachments'));

        $edit = ['course' => $section->course_id, 'record' => $lesson->id];
        $fileUrl = fn ($file): string => CourseResource::getUrl('lesson-file', [
            'course' => $section->course_id,
            'lesson' => $lesson,
            'file' => $file,
        ], tenant: $tenant);
        $videoUrl = $fileUrl($lesson->getFirstMedia('video'));
        $savedAudio = $lesson->getFirstMedia('audio');
        $audioUrl = $fileUrl($savedAudio);

        $this->get(CourseResource::getUrl('edit-lesson', $edit, tenant: $tenant))
            ->assertOk()
            ->assertSee(['<video controls', '<audio controls', $videoUrl, $audioUrl, 'guide-1.txt'], escape: false);
        $this->get($videoUrl, ['Range' => 'bytes=0-15'])
            ->assertStatus(206)->assertHeader('Content-Type', 'video/mp4');
        $this->get($audioUrl)->assertOk()->assertHeader('Content-Type', $savedAudio->mime_type);

        Livewire::test(EditCourseLesson::class, $edit)
            ->set('data.video', [])
            ->fillForm(['video' => UploadedFile::fake()->create('invalid.mp4', 1, 'video/mp4')])
            ->call('save')
            ->assertHasFormErrors(['video']);
        $this->assertTrue($lesson->refresh()->hasMedia('video'));
        $this->assertSame(2, $lesson->duration_minutes);

        Livewire::test(EditCourseLesson::class, $edit)
            ->set('data.video', [])
            ->fillForm(['video' => UploadedFile::fake()->createWithContent('replacement.mp4', $video(30))->mimeType('video/mp4')])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertCount(1, $lesson->refresh()->getMedia('video'));
        $this->assertSame(1, $lesson->duration_minutes);

        Livewire::test(EditCourseLesson::class, $edit)
            ->fillForm(['video' => null])->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($lesson->refresh()->hasMedia('video'));
        $this->assertNull($lesson->duration_minutes);
        $this->assertSame(3, $lesson->audio_duration_seconds);

        Livewire::test(EditCourseLesson::class, $edit)
            ->set('data.audio', [])
            ->fillForm(['audio' => UploadedFile::fake()->createWithContent('replacement.wav', $audio(7))->mimeType('audio/wav')])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertCount(1, $lesson->refresh()->getMedia('audio'));
        $this->assertSame(7, $lesson->audio_duration_seconds);

        Livewire::test(EditCourseLesson::class, $edit)
            ->fillForm(['audio' => null])->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($lesson->refresh()->hasMedia('audio'));
        $this->assertNull($lesson->audio_duration_seconds);
    }

    public function test_lesson_file_previews_require_course_edit_access_and_scope_files_to_the_lesson(): void
    {
        Storage::fake('local');
        $primary = Tenant::factory()->create();
        $branch = Tenant::factory()->create();
        $course = Course::factory()->create(['is_published' => false]);
        $section = CourseSection::factory()->for($course)->create();
        $lesson = CourseLesson::factory()->for($section, 'section')->create();
        $otherLesson = CourseLesson::factory()->for($section, 'section')->create();
        $image = $lesson->addMedia(UploadedFile::fake()->image('handout.png'))->toMediaCollection('attachments');
        $pdf = $lesson->addMediaFromString('%PDF-1.4')->usingFileName('handout.pdf')->toMediaCollection('attachments');
        $html = $lesson->addMediaFromString('<html><body>Handout</body></html>')
            ->usingFileName('handout.html')->toMediaCollection('attachments');
        $otherFile = $otherLesson->addMediaFromString('Other lesson')->usingFileName('other.txt')->toMediaCollection('attachments');
        $fileUrl = fn ($file, Tenant $tenant): string => CourseResource::getUrl('lesson-file', [
            'course' => $course,
            'lesson' => $lesson,
            'file' => $file,
        ], tenant: $tenant);

        $this->get($fileUrl($image, $primary))->assertRedirect(route('login'));
        $this->actAsEducationOfficer($primary);

        $this->get(CourseResource::getUrl('edit-lesson', ['course' => $course, 'record' => $lesson], tenant: $primary))
            ->assertOk()->assertSee([
                '<img src="'.$fileUrl($image, $primary).'"',
                'href="'.$fileUrl($pdf, $primary).'"',
                'href="'.$fileUrl($html, $primary).'"',
            ], escape: false);
        $this->get($fileUrl($image, $primary))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($fileUrl($pdf, $primary))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($fileUrl($html, $primary))->assertOk()->assertDownload('handout.html')
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($fileUrl($otherFile, $primary))->assertNotFound();
        $this->get(CourseResource::getUrl('lesson-file', [
            'course' => Course::factory()->create(),
            'lesson' => $lesson,
            'file' => $image,
        ], tenant: $primary))->assertNotFound();

        $branchOfficer = User::factory()->create();
        $this->makeStaff($branchOfficer, $branch);
        $this->grantRole($branchOfficer, $branch, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($branchOfficer, $branch);
        $this->get($fileUrl($image, $branch))->assertForbidden();
        $this->get($fileUrl($image, $primary))->assertNotFound();
    }

    public function test_head_office_officers_can_assign_users_from_different_branches(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Tenant::factory()->create();
        $instructor = User::factory()->create();
        $learner = User::factory()->create();
        $this->grantRole($instructor, $tenant, DefaultRoles::INSTRUCTOR);
        $this->grantRole($learner, $branch, DefaultRoles::LEARNER);
        $this->actAsEducationOfficer($tenant);

        $course = Course::factory()->create();
        $owner = ['ownerRecord' => $course, 'pageClass' => EditCourse::class];
        Livewire::test(InstructorsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $instructor->id])
            ->assertHasNoActionErrors();
        $this->assertSame($instructor->id, $course->instructors()->sole()->user_id);

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => 999999])
            ->assertHasActionErrors(['user_id']);

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $learner->id, 'expires_at' => now()->addDay()->toDateTimeString()])
            ->assertHasNoActionErrors();

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $learner->id])
            ->assertHasActionErrors(['user_id']);

        $enrollment = $course->enrollments()->sole();
        $this->assertSame($learner->id, $enrollment->user_id);
        $this->assertNotNull($enrollment->expires_at);

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('edit')->table($enrollment), data: ['is_revoked' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue($enrollment->refresh()->is_revoked);
    }

    public function test_course_form_validates_schedule_and_prepayment_and_stores_media_privately(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $existingCourse = Course::factory()->create(['code' => 'CRS-EXISTING']);

        $this->get(CourseResource::getUrl('create', tenant: $tenant))
            ->assertOk()->assertSee(['اطلاعات کلی', 'رسانه', 'زمان‌بندی', 'قیمت‌گذاری', 'مدرسان', 'گالری تصاویر'])
            ->assertDontSee('جنسیت مخاطب')->assertDontSee('توضیح کوتاه');

        $form = Livewire::test(CreateCourse::class)->fillForm([
            'title' => 'دوره با رسانه',
            'code' => $existingCourse->code,
            'starts_on' => '2026-12-31',
            'ends_on' => '2026-12-01',
            'has_prepayment' => true,
        ]);

        $form->call('create')->assertHasFormErrors(['code', 'ends_on' => 'after_or_equal', 'prepayment_amount' => 'required']);
        $this->assertSame(1, Course::query()->count());

        $form->fillForm([
            'code' => 'CRS-MEDIA',
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'has_prepayment' => false,
            'cover_image' => UploadedFile::fake()->image('cover.png'),
            'gallery_images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')],
        ])->call('create')->assertHasNoFormErrors();

        $course = Course::query()->where('code', 'CRS-MEDIA')->sole();
        $this->assertCount(2, $course->gallery_images);
        Storage::disk('local')->assertExists([$course->cover_image, ...$course->gallery_images]);

        Livewire::test(EditCourse::class, ['record' => $course->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($course->gallery_images, $course->refresh()->gallery_images);
    }

    public function test_course_deletion_is_available_from_the_list_for_courses_without_lessons(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $course = Course::factory()->create();
        $courseWithLessons = Course::factory()->create();
        $lesson = CourseLesson::factory()->for(CourseSection::factory()->for($courseWithLessons), 'section')->create();

        Livewire::test(EditCourse::class, ['record' => $course->id])->assertActionDoesNotExist('delete');

        $list = Livewire::test(ListCourses::class);
        $blockedAction = TestAction::make('delete')->table($courseWithLessons);
        $list->assertActionHidden($blockedAction)->mountAction($blockedAction)->assertSet('mountedActions', []);
        $this->assertModelExists($courseWithLessons);
        $this->assertModelExists($lesson);

        $delete = TestAction::make('delete')->table($course);
        $list->assertActionVisible($delete)->callAction($delete)->assertHasNoActionErrors();
        $this->assertModelMissing($course);

        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $tenant);
        $this->grantRole($viewer, $tenant, $this->roleWithPermissions('course-viewer', ['ViewAny:Course', 'View:Course'])->name);
        $this->actingInPanel($viewer, $tenant);
        $viewableCourse = Course::factory()->create();
        Livewire::test(ListCourses::class)->assertActionHidden(TestAction::make('delete')->table($viewableCourse));
    }

    public function test_course_introduction_accepts_a_100_megabyte_video(): void
    {
        Storage::fake('local');
        Storage::fake('tmp-for-tests');
        $tenant = Tenant::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $course = Course::factory()->create();
        $video = UploadedFile::fake()->create('intro.mp4', 102400, 'video/mp4');
        $tooLarge = UploadedFile::fake()->create('large.mp4', 102401, 'video/mp4');
        $uploadUrl = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);

        $this->post($uploadUrl, ['files' => [$video]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(1, 'paths');
        $this->post($uploadUrl, ['files' => [$tooLarge]], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');

        Livewire::test(EditCourse::class, ['record' => $course->id])
            ->fillForm(['intro_video' => $video])
            ->call('save')->assertHasNoFormErrors();

        $this->assertStringStartsWith('courses/intros/', $course->refresh()->intro_video);
        Storage::disk('local')->assertExists($course->intro_video);
    }

    private function actAsEducationOfficer(Tenant $tenant): void
    {
        $tenant->makePrimary();
        $officer = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->makeStaff($officer, $tenant);
        $this->grantRole($officer, $tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($officer, $tenant);
    }
}
