<?php

namespace Modules\Exam\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Filament\Resources\QuestionCategories\Pages\ListQuestionCategories;
use Modules\Exam\Filament\Resources\QuestionCategories\QuestionCategoryResource;
use Modules\Exam\Filament\Resources\Questions\Pages\CreateQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\EditQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\ListQuestions;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Models\QuestionCategory;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class QuestionCategoryTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $manager = User::factory()->create();
        $this->makeStaff($manager, $this->tenant);
        $this->roleWithPermissions('exam_manager', [
            ...DefaultRoles::manage('QuestionCategory'),
            ...DefaultRoles::manage('Question'),
        ]);
        $this->grantRole($manager, $this->tenant, 'exam_manager');
        $this->actingInPanel($manager, $this->tenant);
    }

    public function test_admin_can_create_and_move_a_subcategory_from_the_tree(): void
    {
        Livewire::test(ListQuestionCategories::class)
            ->callAction('create', data: ['title' => 'ریاضی'])
            ->assertHasNoActionErrors();
        $root = QuestionCategory::query()->sole();

        Livewire::test(ListQuestionCategories::class)
            ->mountAction('create', ['parent' => $root->id])
            ->assertActionDataSet(['parent_id' => $root->id])
            ->setActionData(['title' => 'جبر', 'parent_id' => $root->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $child = $root->children()->sole();
        $otherRoot = $this->category('منطق');

        Livewire::test(ListQuestionCategories::class)
            ->assertSeeHtml('data-parent-id="'.$root->id.'"')
            ->callAction('edit', data: ['title' => 'استدلال', 'parent_id' => $otherRoot->id], arguments: ['category' => $child->id])
            ->assertHasNoActionErrors()
            ->assertDontSeeHtml('data-parent-id="'.$root->id.'"')
            ->assertSeeHtml('data-parent-id="'.$otherRoot->id.'"');

        $this->assertSame($otherRoot->id, $child->fresh()->parent_id);
        $this->assertSame($this->tenant->id, $child->tenant_id);
    }

    public function test_other_tenant_categories_are_hidden_and_cannot_be_used_as_parents_or_assigned_to_questions(): void
    {
        $own = $this->category('ریاضی');
        $foreign = QuestionCategory::factory()->create();
        $bank = QuestionBank::factory()->for($this->tenant)->create();

        Livewire::test(ListQuestionCategories::class)
            ->assertSee($own->title)
            ->assertDontSee($foreign->title)
            ->callAction('create', data: ['title' => 'نامعتبر', 'parent_id' => $foreign->id])
            ->assertHasActionErrors(['parent_id']);

        Livewire::test(ListQuestionCategories::class)
            ->mountAction('edit', ['category' => $foreign->id])
            ->assertNotFound();

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$bank->id], 'type' => QuestionType::Descriptive->value, 'body' => 'چرا؟', 'category_id' => $foreign->id])
            ->call('create')
            ->assertHasFormErrors(['category_id']);

        $this->assertSame(1, QuestionCategory::query()->count());
        $this->assertSame(0, Question::query()->count());
    }

    public function test_category_management_requires_its_own_permission(): void
    {
        $reader = User::factory()->create();
        $this->makeStaff($reader, $this->tenant);
        $this->actingInPanel($reader, $this->tenant);

        $this->get(QuestionCategoryResource::getUrl('index', tenant: $this->tenant))->assertForbidden();
    }

    public function test_a_view_only_user_can_read_the_tree_but_cannot_mount_write_actions(): void
    {
        $category = $this->category('ریاضی');
        $reader = User::factory()->create();
        $this->makeStaff($reader, $this->tenant);
        $this->roleWithPermissions('category_reader', ['ViewAny:QuestionCategory']);
        $this->grantRole($reader, $this->tenant, 'category_reader');
        $this->actingInPanel($reader, $this->tenant);

        Livewire::test(ListQuestionCategories::class)
            ->assertSee($category->title)
            ->assertDontSee('افزودن زیردسته')
            ->mountAction('create', ['parent' => $category->id])
            ->assertActionNotMounted()
            ->mountAction('edit', ['category' => $category->id])
            ->assertActionNotMounted()
            ->mountAction('delete', ['category' => $category->id])
            ->assertActionNotMounted();
    }

    public function test_search_keeps_matching_categories_in_context_and_displays_direct_question_counts(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $grandchild = $this->category('معادله', $child);
        $this->category('هندسه', $root);
        Question::factory()->for($this->tenant)->for($grandchild, 'category')->create();

        Livewire::test(ListQuestionCategories::class)
            ->set('search', 'معادله')
            ->assertSee('ریاضی')
            ->assertSee('جبر')
            ->assertSee('معادله')
            ->assertSee('1 سؤال')
            ->assertDontSee('هندسه')
            ->set('search', 'زیست')
            ->assertSee('دسته‌ای مطابق جست‌وجو پیدا نشد.');
    }

    public function test_a_category_cannot_be_moved_beneath_itself_or_a_descendant(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $grandchild = $this->category('معادله', $child);

        foreach ([$root, $grandchild] as $parent) {
            Livewire::test(ListQuestionCategories::class)
                ->callAction('edit', data: ['title' => 'ریاضی', 'parent_id' => $parent->id], arguments: ['category' => $root->id])
                ->assertHasActionErrors(['parent_id']);
        }

        try {
            $root->update(['parent_id' => $grandchild->id]);
            $this->fail('Direct category writes must also reject cycles.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_question_category_can_be_assigned_changed_and_cleared(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $bank = QuestionBank::factory()->for($this->tenant)->create();

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$bank->id], 'type' => QuestionType::Descriptive->value, 'body' => 'چرا؟', 'category_id' => $child->id])
            ->call('create')
            ->assertHasNoFormErrors();
        $question = Question::query()->sole();
        $this->assertTrue($question->category->is($child));

        $form = Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()]);
        $form->assertSchemaStateSet(['category_id' => $child->id])
            ->fillForm(['category_id' => $root->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($root->id, $question->fresh()->category_id);

        $form->fillForm(['category_id' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($question->fresh()->category_id);
        $this->assertSame([$bank->id], $question->banks()->pluck('exam_question_banks.id')->all());
    }

    public function test_category_filter_includes_all_depths_and_can_find_uncategorized_questions(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $grandchild = $this->category('معادله', $child);
        $other = $this->category('منطق');
        [$rootQuestion, $deepQuestion, $otherQuestion] = collect([$root, $grandchild, $other])
            ->map(fn (QuestionCategory $category): Question => Question::factory()->for($this->tenant)->for($category, 'category')->create())
            ->all();
        $uncategorized = Question::factory()->for($this->tenant)->create();
        $foreign = QuestionCategory::factory()->create();

        Livewire::test(ListQuestions::class)
            ->assertSee('fi-hierarchy-path-separator')
            ->assertSeeInOrder(['ریاضی', 'جبر', 'معادله'])
            ->filterTable('category', $root->id)
            ->assertCanSeeTableRecords([$rootQuestion, $deepQuestion])
            ->assertCanNotSeeTableRecords([$uncategorized, $otherQuestion])
            ->filterTable('category', [$root->id, $child->id, $other->id])
            ->assertCanSeeTableRecords([$rootQuestion, $deepQuestion, $otherQuestion])
            ->assertCanNotSeeTableRecords([$uncategorized])
            ->filterTable('category', [$other->id, 'uncategorized', $foreign->id])
            ->assertCanSeeTableRecords([$uncategorized, $otherQuestion])
            ->assertCanNotSeeTableRecords([$rootQuestion, $deepQuestion])
            ->filterTable('category', 'uncategorized')
            ->assertCanSeeTableRecords([$uncategorized])
            ->assertCanNotSeeTableRecords([$rootQuestion, $deepQuestion, $otherQuestion])
            ->filterTable('category', $foreign->id)
            ->assertCountTableRecords(0);

        Livewire::withQueryParams(['filters' => ['category' => ['value' => (string) $root->id]]])
            ->test(ListQuestions::class)
            ->assertCanSeeTableRecords([$rootQuestion, $deepQuestion])
            ->assertCanNotSeeTableRecords([$uncategorized, $otherQuestion])
            ->filterTable('category', [])
            ->assertCanSeeTableRecords([$rootQuestion, $deepQuestion, $uncategorized, $otherQuestion]);
    }

    public function test_delete_requires_moving_subcategories_and_questions_first(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $question = Question::factory()->for($this->tenant)->for($child, 'category')->create();

        Livewire::test(ListQuestionCategories::class)
            ->callAction([TestAction::make('edit')->arguments(['category' => $root->id]), 'delete'])
            ->assertNotified('دسته‌ای که سؤال یا زیردسته دارد قابل حذف نیست.')
            ->assertActionMounted('edit')
            ->unmountAction()
            ->callAction([TestAction::make('edit')->arguments(['category' => $child->id]), 'delete'])
            ->assertNotified('دسته‌ای که سؤال یا زیردسته دارد قابل حذف نیست.');

        $this->assertModelExists($root);
        $this->assertSame($child->id, $question->fresh()->category_id);
        $question->update(['category_id' => null]);

        Livewire::test(ListQuestionCategories::class)
            ->callAction([TestAction::make('edit')->arguments(['category' => $child->id]), 'delete'])
            ->assertActionNotMounted();

        $this->assertModelMissing($child);
        $this->assertModelExists($question);
    }

    public function test_edit_and_delete_permissions_are_independent_inside_the_category_modal(): void
    {
        $category = $this->category('ریاضی');
        $editor = User::factory()->create();
        $remover = User::factory()->create();
        $this->makeStaff($editor, $this->tenant);
        $this->makeStaff($remover, $this->tenant);
        $this->roleWithPermissions('category_editor', ['ViewAny:QuestionCategory', 'Update:QuestionCategory']);
        $this->roleWithPermissions('category_remover', ['ViewAny:QuestionCategory', 'Delete:QuestionCategory']);
        $this->grantRole($editor, $this->tenant, 'category_editor');
        $this->grantRole($remover, $this->tenant, 'category_remover');
        $delete = [TestAction::make('edit')->arguments(['category' => $category->id]), 'delete'];

        $this->actingInPanel($editor, $this->tenant);
        Livewire::test(ListQuestionCategories::class)
            ->assertActionHidden($delete)
            ->mountAction('edit', ['category' => $category->id])
            ->assertActionMounted('edit')
            ->call('mountAction', 'delete')
            ->assertSet('mountedActions.0.name', 'edit')
            ->assertSet('mountedActions.1.name', null);
        $this->assertModelExists($category);

        $this->actingInPanel($remover, $this->tenant);
        Livewire::test(ListQuestionCategories::class)
            ->assertActionVisible($delete)
            ->mountAction('edit', ['category' => $category->id])
            ->assertActionMounted('edit')
            ->callMountedAction()
            ->assertForbidden();

        Livewire::test(ListQuestionCategories::class)->callAction($delete);
        $this->assertModelMissing($category);
    }

    public function test_database_rejects_foreign_category_links_even_when_bypassing_models(): void
    {
        $category = $this->category('ریاضی');
        $question = Question::factory()->for($this->tenant)->create();
        $foreign = QuestionCategory::factory()->create();

        foreach ([[$category, 'parent_id'], [$question, 'category_id']] as [$record, $column]) {
            try {
                DB::transaction(fn () => DB::table($record->getTable())->where('id', $record->id)->update([$column => $foreign->id]));
                $this->fail('Expected the tenant-matched foreign key to reject the update.');
            } catch (QueryException $exception) {
                $this->assertSame('23503', $exception->getCode());
            }
        }
    }

    private function category(string $title, ?QuestionCategory $parent = null): QuestionCategory
    {
        return QuestionCategory::factory()->for($this->tenant)->create(['title' => $title, 'parent_id' => $parent?->id]);
    }
}
