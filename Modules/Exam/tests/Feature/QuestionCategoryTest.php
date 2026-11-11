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

    public function test_admin_can_create_and_move_a_subcategory_and_see_its_full_path(): void
    {
        Livewire::test(ListQuestionCategories::class)
            ->callAction('create', data: ['title' => 'ریاضی'])
            ->assertHasNoActionErrors();
        $root = QuestionCategory::query()->sole();

        Livewire::test(ListQuestionCategories::class)
            ->callAction('create', data: ['title' => 'جبر', 'parent_id' => $root->id])
            ->assertHasNoActionErrors();
        $child = $root->children()->sole();
        $otherRoot = $this->category('منطق');

        Livewire::test(ListQuestionCategories::class)
            ->assertSee('ریاضی / جبر')
            ->callAction(TestAction::make('edit')->table($child), data: ['title' => 'استدلال', 'parent_id' => $otherRoot->id])
            ->assertHasNoActionErrors();
        Livewire::test(ListQuestionCategories::class)->assertSee('منطق / استدلال');

        $this->assertSame($otherRoot->id, $child->fresh()->parent_id);
        $this->assertSame($this->tenant->id, $child->tenant_id);
    }

    public function test_other_tenant_categories_are_hidden_and_cannot_be_used_as_parents_or_assigned_to_questions(): void
    {
        $own = $this->category('ریاضی');
        $foreign = QuestionCategory::factory()->create();
        $bank = QuestionBank::factory()->for($this->tenant)->create();

        Livewire::test(ListQuestionCategories::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign])
            ->callAction('create', data: ['title' => 'نامعتبر', 'parent_id' => $foreign->id])
            ->assertHasActionErrors(['parent_id']);

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

    public function test_a_category_cannot_be_moved_beneath_itself_or_a_descendant(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $grandchild = $this->category('معادله', $child);

        foreach ([$root, $grandchild] as $parent) {
            Livewire::test(ListQuestionCategories::class)
                ->callAction(TestAction::make('edit')->table($root), data: ['title' => 'ریاضی', 'parent_id' => $parent->id])
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
            ->assertSee('ریاضی / جبر / معادله')
            ->filterTable('category', $root->id)
            ->assertCanSeeTableRecords([$rootQuestion, $deepQuestion])
            ->assertCanNotSeeTableRecords([$uncategorized, $otherQuestion])
            ->filterTable('category', 'uncategorized')
            ->assertCanSeeTableRecords([$uncategorized])
            ->assertCanNotSeeTableRecords([$rootQuestion, $deepQuestion, $otherQuestion])
            ->filterTable('category', $foreign->id)
            ->assertCountTableRecords(0);
    }

    public function test_delete_requires_moving_subcategories_and_questions_first(): void
    {
        $root = $this->category('ریاضی');
        $child = $this->category('جبر', $root);
        $question = Question::factory()->for($this->tenant)->for($child, 'category')->create();

        Livewire::test(ListQuestionCategories::class)
            ->callAction(TestAction::make('delete')->table($root))
            ->assertNotified('دسته‌ای که سؤال یا زیردسته دارد قابل حذف نیست.')
            ->callAction(TestAction::make('delete')->table($child))
            ->assertNotified('دسته‌ای که سؤال یا زیردسته دارد قابل حذف نیست.');

        $this->assertModelExists($root);
        $this->assertSame($child->id, $question->fresh()->category_id);
        $question->update(['category_id' => null]);

        Livewire::test(ListQuestionCategories::class)->callAction(TestAction::make('delete')->table($child));

        $this->assertModelMissing($child);
        $this->assertModelExists($question);
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
