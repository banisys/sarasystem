<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Modules\Course\Filament\Resources\CourseCategories\CourseCategoryResource;
use Modules\Course\Filament\Resources\CourseCategories\Pages\ManageCourseCategories;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseCategory;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseCategoryTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['is_primary' => true]);
        $officer = User::factory()->create();
        $this->makeStaff($officer, $this->tenant);
        $this->roleWithPermissions('course_category_manager', DefaultRoles::manage('CourseCategory'));
        $this->grantRole($officer, $this->tenant, 'course_category_manager');
        $this->actingInPanel($officer, $this->tenant);
    }

    public function test_course_category_fields_round_trip_in_the_tree_modals(): void
    {
        $root = CourseCategory::factory()->create(['title' => 'آموزش سازمانی']);
        $data = [
            'title' => 'آشنایی با سازمان',
            'alias' => 'آشنایی-با-سازمان',
            'short_description' => 'دوره‌های آغاز به کار',
            'position' => 7,
            'is_active' => false,
        ];

        $this->get(CourseCategoryResource::getUrl(tenant: $this->tenant))->assertOk()->assertSee($root->title);

        Livewire::test(ManageCourseCategories::class)
            ->mountAction('create', ['parent' => $root->id])
            ->assertActionDataSet(['parent_id' => $root->id, 'position' => 0, 'is_active' => true])
            ->setActionData($data + ['parent_id' => $root->id])
            ->callMountedAction()->assertHasNoActionErrors();

        $record = $root->children()->sole();
        foreach ($data as $field => $value) {
            $this->assertSame($value, $record->{$field});
        }
        $record->update([
            'locale' => 'fa',
            'translation_key' => 'courses.onboarding',
            'metadata' => ['badge' => ['color' => 'blue']],
        ]);

        Livewire::test(ManageCourseCategories::class)
            ->set('search', $record->alias)
            ->assertSee($root->title)->assertSee($record->title)->assertSee('غیرفعال')
            ->mountAction('edit', ['category' => $record->id])
            ->assertActionDataSet($data)
            ->assertDontSee('اطلاعات تکمیلی')
            ->setActionData(['parent_id' => null, 'is_active' => true])
            ->callMountedAction()->assertHasNoActionErrors();

        $record->refresh();
        $this->assertNull($record->parent_id);
        $this->assertTrue($record->is_active);
        $this->assertSame('fa', $record->locale);
        $this->assertSame('courses.onboarding', $record->translation_key);
        $this->assertSame('blue', $record->metadata->badge->color);
    }

    public function test_forged_parents_and_duplicate_aliases_are_rejected(): void
    {
        $root = CourseCategory::factory()->create(['title' => 'دستهٔ اصلی شعبه', 'alias' => 'root']);
        $child = CourseCategory::factory()->for($root, 'parent')->create();
        $otherTenant = Tenant::factory()->create();
        $foreign = $otherTenant->execute(fn () => CourseCategory::factory()->create(
            ['title' => 'دستهٔ اختصاصی شعبهٔ دیگر', 'alias' => 'root'],
        ));

        Livewire::test(ManageCourseCategories::class)->assertDontSee($foreign->title)
            ->mountAction('edit', ['category' => $foreign->id])->assertNotFound();

        Livewire::test(ManageCourseCategories::class)
            ->callAction('create', data: ['title' => 'New', 'alias' => 'new', 'parent_id' => $foreign->id])
            ->assertHasActionErrors(['parent_id']);

        Livewire::test(ManageCourseCategories::class)
            ->callAction('edit', data: ['parent_id' => $child->id], arguments: ['category' => $root->id])
            ->assertHasActionErrors(['parent_id']);

        Livewire::test(ManageCourseCategories::class)
            ->callAction('create', data: ['title' => 'Duplicate alias', 'alias' => 'root'])
            ->assertHasActionErrors(['alias']);

        $this->assertNull($root->refresh()->parent_id);
        $this->assertSame(2, CourseCategory::query()->count());
    }

    public function test_tree_shows_sibling_order_and_counts_and_protects_categories_with_dependents(): void
    {
        $root = CourseCategory::factory()->create();
        $late = CourseCategory::factory()->for($root, 'parent')->create(['title' => 'آخر', 'position' => 9]);
        $early = CourseCategory::factory()->for($root, 'parent')->create(['title' => 'اول', 'position' => 1]);
        Course::factory()->for($late, 'category')->create();

        Livewire::test(ManageCourseCategories::class)
            ->assertSeeInOrder([$root->title, $early->title, $late->title, '1 دوره']);

        foreach ([$root, $late] as $blocked) {
            Livewire::test(ManageCourseCategories::class)
                ->mountAction('edit', ['category' => $blocked->id])
                ->callAction(TestAction::make('delete'))
                ->assertNotified('دسته‌ای که دوره یا زیردسته دارد قابل حذف نیست.');
            $this->assertModelExists($blocked);
        }

        Livewire::test(ManageCourseCategories::class)
            ->mountAction('edit', ['category' => $early->id])
            ->callAction(TestAction::make('delete'));
        $this->assertModelMissing($early);
    }

    public function test_public_catalog_filters_active_branches_at_every_depth(): void
    {
        $root = CourseCategory::factory()->create(['title' => 'آموزش']);
        $child = CourseCategory::factory()->for($root, 'parent')->create(['title' => 'مهارت']);
        $inactive = CourseCategory::factory()->for($root, 'parent')->create(['is_active' => false]);
        $hiddenChild = CourseCategory::factory()->for($inactive, 'parent')->create();
        $published = Course::factory()->for($child, 'category')->create(['is_published' => true]);
        Course::factory()->for($hiddenChild, 'category')->create(['is_published' => true]);

        $this->get(route('courses.catalog', ['category' => $root->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('courses.data', 1)
                ->where('courses.data.0.id', $published->id)
                ->has('categories', 2)->where('categories.1.title', 'آموزش / مهارت'));

        $this->get(route('courses.catalog', ['category' => $hiddenChild->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('courses.data', 0));
    }
}
