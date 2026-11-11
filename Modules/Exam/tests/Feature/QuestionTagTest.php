<?php

namespace Modules\Exam\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Filament\Resources\Questions\Pages\CreateQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\EditQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\ListQuestions;
use Modules\Exam\Filament\Resources\QuestionTags\Pages\ManageQuestionTags;
use Modules\Exam\Filament\Resources\QuestionTags\QuestionTagResource;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Models\QuestionTag;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class QuestionTagTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $manager = User::factory()->create();
        $this->makeStaff($manager, $this->tenant);
        app(DefaultRoles::class)->install();
        $this->grantRole($manager, $this->tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($manager, $this->tenant);
    }

    public function test_a_tag_can_be_created_renamed_and_deleted_in_its_tenant(): void
    {
        $foreign = QuestionTag::factory()->create(['title' => 'مرور']);

        Livewire::test(ManageQuestionTags::class)
            ->assertCanNotSeeTableRecords([$foreign])
            ->callAction('create', data: ['title' => ' مرور '])
            ->assertHasNoActionErrors()
            ->callAction('create', data: ['title' => 'مرور'])
            ->assertHasActionErrors(['title']);

        $tag = QuestionTag::query()->sole();
        $this->assertSame('مرور', $tag->title);
        $question = Question::factory()->for($this->tenant)->create();
        $question->tags()->attach($tag->id, ['tenant_id' => $this->tenant->id]);

        Livewire::test(ManageQuestionTags::class)
            ->assertCanSeeTableRecords([$tag])
            ->assertTableColumnStateSet('questions_count', 1, record: $tag)
            ->callAction(TestAction::make('edit')->table($tag), data: ['title' => 'تمرین'])
            ->assertHasNoActionErrors();
        $this->assertSame('تمرین', $tag->refresh()->title);
        $this->assertTrue($question->tags()->sole()->is($tag));

        Livewire::test(ManageQuestionTags::class)->callAction(TestAction::make('delete')->table($tag));
        $this->assertModelMissing($tag);
        $this->assertModelExists($question);
        $this->assertFalse($question->tags()->exists());
        $this->assertModelExists($foreign);
    }

    public function test_questions_can_have_several_tags_and_the_list_filters_by_any_of_them(): void
    {
        $review = QuestionTag::factory()->for($this->tenant)->create(['title' => 'مرور']);
        $practice = QuestionTag::factory()->for($this->tenant)->create(['title' => 'تمرین']);
        $bank = QuestionBank::factory()->for($this->tenant)->create();

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$bank->id], 'tags' => [$review->id, $practice->id], 'type' => QuestionType::Descriptive->value, 'body' => 'چرا؟'])
            ->call('create')
            ->assertHasNoFormErrors();
        $question = Question::query()->sole();
        $this->assertEqualsCanonicalizing([$review->id, $practice->id], $question->tags->modelKeys());

        $editor = Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
            ->fillForm(['tags' => [$practice->id]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame([$practice->id], $question->fresh()->tags->modelKeys());

        $reviewQuestion = Question::factory()->for($this->tenant)->create();
        $reviewQuestion->tags()->attach($review->id, ['tenant_id' => $this->tenant->id]);
        $untagged = Question::factory()->for($this->tenant)->create();
        Livewire::test(ListQuestions::class)
            ->assertSee('تمرین')
            ->filterTable('tags', [$review->id, $practice->id])
            ->assertCanSeeTableRecords([$question, $reviewQuestion])
            ->assertCanNotSeeTableRecords([$untagged])
            ->filterTable('tags', [$practice->id])
            ->assertCanSeeTableRecords([$question])
            ->assertCanNotSeeTableRecords([$reviewQuestion, $untagged]);

        $editor->fillForm(['tags' => []])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($question->tags()->exists());
        $this->assertTrue($question->banks()->sole()->is($bank));
    }

    public function test_tags_from_another_tenant_cannot_be_assigned_or_used_to_find_questions(): void
    {
        $own = QuestionTag::factory()->for($this->tenant)->create();
        $foreign = QuestionTag::factory()->create();
        $foreignQuestion = Question::factory()->create(['tenant_id' => $foreign->tenant_id]);
        $foreignQuestion->tags()->attach($foreign->id, ['tenant_id' => $foreign->tenant_id]);
        $bank = QuestionBank::factory()->for($this->tenant)->create();

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$bank->id], 'tags' => [$own->id, $foreign->id], 'type' => QuestionType::Descriptive->value, 'body' => 'چرا؟'])
            ->call('create')
            ->assertHasFormErrors();
        $this->assertSame(0, Question::query()->count());

        $question = Question::factory()->for($this->tenant)->create();
        $question->banks()->attach($bank);
        $question->tags()->attach($own->id, ['tenant_id' => $this->tenant->id]);
        Livewire::test(EditQuestion::class, ['record' => $question->getRouteKey()])
            ->fillForm(['tags' => [$foreign->id]])
            ->call('save')
            ->assertHasFormErrors();
        $this->assertSame([$own->id], $question->fresh()->tags->modelKeys());

        Livewire::test(ListQuestions::class)
            ->assertCanNotSeeTableRecords([$foreignQuestion])
            ->filterTable('tags', [$foreign->id])
            ->assertCountTableRecords(0);

        $this->expectException(QueryException::class);
        DB::transaction(fn () => $question->tags()->attach($foreign->id, ['tenant_id' => $this->tenant->id]));
    }

    public function test_tag_management_requires_its_own_permission(): void
    {
        $editor = User::factory()->create();
        $this->makeStaff($editor, $this->tenant);
        $this->roleWithPermissions('question_editor', DefaultRoles::manage('Question'));
        $this->grantRole($editor, $this->tenant, 'question_editor');
        $this->actingInPanel($editor, $this->tenant);

        $this->get(QuestionTagResource::getUrl('index', tenant: $this->tenant))->assertForbidden();
    }

    public function test_the_system_map_links_to_the_tag_catalog_in_each_tenant(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach ([$this->tenant, Tenant::factory()->create()] as $tenant) {
            $catalog = QuestionTagResource::getUrl('index', tenant: $tenant);

            $this->actingAs($admin)->get("/cpadmin/{$tenant->slug}/system-map")
                ->assertOk()
                ->assertSee($catalog, escape: false);

            $this->get($catalog)->assertOk()->assertSee('برچسب‌های سؤال');
        }
    }
}
