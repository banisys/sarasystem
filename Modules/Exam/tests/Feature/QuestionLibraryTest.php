<?php

namespace Modules\Exam\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class QuestionLibraryTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_instructors_can_manage_and_share_their_own_library(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->instructor($tenant);

        $this->post(route('exams.library.banks.store'), ['title' => 'مکالمه', 'is_shared' => false, 'owner_id' => 999])->assertRedirect(route('exams.library'));
        $bank = QuestionBank::query()->sole();
        $this->assertSame($owner->id, $bank->owner_id);

        $this->post(route('exams.library.questions.store'), [
            'type' => 'fill_in_the_blank', 'body' => 'My name is ___.', 'score' => 2,
            'is_active' => true, 'is_shared' => false, 'accepted_answers' => ['Ali'], 'bank_ids' => [$bank->id],
            'return_bank' => $bank->id,
        ])->assertRedirect(route('exams.library.banks.show', $bank));
        $question = Question::query()->sole();
        $this->assertSame($owner->id, $question->owner_id);
        $this->assertSame([$bank->id], $question->banks()->get()->modelKeys());

        $this->patch(route('exams.library.banks.update', $bank), ['title' => 'مکالمه', 'is_shared' => true])->assertRedirect();
        $this->patch(route('exams.library.questions.update', $question), [
            'type' => 'descriptive', 'body' => 'Introduce yourself.', 'score' => 3,
            'is_active' => true, 'is_shared' => true, 'model_answer' => 'My name is Ali.', 'bank_ids' => [$bank->id],
        ])->assertRedirect();

        $colleague = $this->instructor($tenant);
        $destination = QuestionBank::factory()->for($tenant)->create(['owner_id' => $colleague->id]);
        $this->get(route('exams.library', ['bank' => $bank->id]))->assertRedirect(route('exams.library.banks.show', $bank));
        $this->get(route('exams.library.banks.show', $bank))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Exam::LibraryQuestions')->where('questions.data.0.model_answer', 'My name is Ali.')
            ->where('questions.data.0.is_mine', false));
        $this->patch(route('exams.library.banks.update', $bank), ['title' => 'تغییر'])->assertNotFound();
        $this->delete(route('exams.library.questions.destroy', $question))->assertNotFound();
        $this->post(route('exams.library.questions.banks.store', $question), ['bank_id' => $destination->id])->assertRedirect();
        $this->assertSame([$question->id], $destination->questions()->get()->modelKeys());

        $this->actingAs($owner);
        $this->delete(route('exams.library.banks.destroy', $bank))->assertRedirect();
        $this->assertModelMissing($bank);
        $this->assertModelExists($question);
        $this->get(route('exams.library.questions'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Exam::LibraryQuestions')->where('selectedBank', null)->where('questions.data.0.id', $question->id));
        $this->delete(route('exams.library.questions.destroy', $question))->assertRedirect();
        $this->assertModelMissing($question);
    }

    public function test_sharing_preserves_question_privacy_and_tenant_boundaries(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->instructor($tenant);
        $ownBank = QuestionBank::factory()->for($tenant)->create(['owner_id' => $actor->id]);
        $colleague = User::factory()->create();
        $public = QuestionBank::factory()->for($tenant)->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $private = QuestionBank::factory()->for($tenant)->create(['owner_id' => $colleague->id]);
        $question = Question::factory()->for($tenant)->create(['owner_id' => $colleague->id]);
        $public->questions()->attach($question);
        $foreign = QuestionBank::factory()->for(Tenant::factory())->create(['owner_id' => $actor->id, 'is_shared' => true]);

        $this->get(route('exams.library'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Exam::QuestionLibrary')->has('banks.data', 2)->missing('questions'));
        $this->get(route('exams.library.banks.show', $public))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Exam::LibraryQuestions')->where('selectedBank.id', $public->id)->has('questions.data', 0)->missing('banks'));
        $this->get(route('exams.library.banks.show', $private))->assertNotFound();
        $this->get(route('exams.library.banks.show', $foreign))->assertNotFound();
        $this->post(route('exams.library.questions.banks.store', $question), ['bank_id' => $ownBank->id])->assertNotFound();
        $this->post(route('exams.library.questions.store'), [
            'type' => 'descriptive', 'body' => 'یک سؤال', 'score' => 1,
            'is_active' => true, 'is_shared' => false, 'bank_ids' => [$public->id, $foreign->id],
        ])->assertSessionHasErrors('bank_ids');
        $this->assertSame(0, $ownBank->questions()->count());
    }

    #[TestWith(['choice', 'سؤال', ['options' => [['text' => 'الف', 'is_correct' => true], ['text' => 'ب', 'is_correct' => true]]], 'options'])]
    #[TestWith(['fill_in_the_blank', 'متن بدون جای خالی', ['accepted_answers' => ['الف']], 'body'])]
    public function test_question_rules_match_the_selected_type(string $type, string $body, array $answer, string $error): void
    {
        $this->instructor(Tenant::factory()->create());
        $this->post(route('exams.library.questions.store'), [
            'type' => $type, 'body' => $body, 'score' => 1, 'is_active' => true, 'is_shared' => false, 'bank_ids' => [],
        ] + $answer)->assertSessionHasErrors($error);
        $this->assertSame(0, Question::query()->count());
    }

    public function test_learners_cannot_access_the_staff_library(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();
        $learner = User::factory()->create();
        $this->grantRole($learner, $tenant, DefaultRoles::LEARNER);
        $this->actingAs($learner)->withSession([ResolveCurrentTenant::SESSION_KEY => $tenant->id]);

        $this->get(route('exams.library'))->assertForbidden();
        $this->get(route('exams.library.questions'))->assertForbidden();
        $this->post(route('exams.library.banks.store'), ['title' => 'بانک', 'is_shared' => true])->assertForbidden();
    }

    private function instructor(Tenant $tenant): User
    {
        $tenant->makeCurrent();
        $user = User::factory()->create();
        $this->grantRole($user, $tenant, DefaultRoles::INSTRUCTOR);
        $this->actingAs($user)->withSession([ResolveCurrentTenant::SESSION_KEY => $tenant->id]);

        return $user;
    }
}
