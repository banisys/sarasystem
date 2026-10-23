<?php

namespace Modules\Exam\Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Filament\Resources\ExamAttempts\Pages\GradeExamAttempt;
use Modules\Exam\Filament\Resources\ExamAttempts\Pages\ListExamAttempts;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Filament\Resources\Exams\Pages\CreateExam;
use Modules\Exam\Filament\Resources\Exams\Pages\ListExams;
use Modules\Exam\Filament\Resources\QuestionBanks\Pages\ListQuestionBanks;
use Modules\Exam\Filament\Resources\QuestionBanks\QuestionBankResource;
use Modules\Exam\Filament\Resources\Questions\Pages\CreateQuestion;
use Modules\Exam\Filament\Resources\Questions\Pages\ListQuestions;
use Modules\Exam\Filament\Resources\Questions\QuestionResource;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Services\ExamTaking;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExamAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->otherTenant] = Tenant::factory()->count(2)->create();

        $permissions = collect(['Exam', 'Question', 'QuestionBank', 'ExamAttempt'])
            ->flatMap(fn (string $subject): array => ["ViewAny:{$subject}", "View:{$subject}", "Create:{$subject}", "Update:{$subject}", "Delete:{$subject}"])
            ->all();
        $this->roleWithPermissions('exam_manager', $permissions);

        $this->manager = User::factory()->create();
        $this->makeStaff($this->manager, $this->tenant);
        $this->grantRole($this->manager, $this->tenant, 'exam_manager');
    }

    public function test_lists_show_only_the_current_tenants_records(): void
    {
        $ownBank = QuestionBank::factory()->for($this->tenant)->create();
        $foreignBank = QuestionBank::factory()->for($this->otherTenant)->create();
        $ownQuestion = Question::factory()->for($this->tenant)->create();
        $foreignQuestion = Question::factory()->for($this->otherTenant)->create();
        $ownExam = Exam::factory()->for($this->tenant)->create();
        $foreignExam = Exam::factory()->for($this->otherTenant)->create();
        $ownAttempt = ExamAttempt::factory()->for($ownExam)->create();
        $foreignAttempt = ExamAttempt::factory()->for($foreignExam)->create();

        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(ListQuestionBanks::class)->assertCanSeeTableRecords([$ownBank])->assertCanNotSeeTableRecords([$foreignBank]);
        Livewire::test(ListQuestions::class)->assertCanSeeTableRecords([$ownQuestion])->assertCanNotSeeTableRecords([$foreignQuestion]);
        Livewire::test(ListExams::class)->assertCanSeeTableRecords([$ownExam])->assertCanNotSeeTableRecords([$foreignExam]);
        Livewire::test(ListExamAttempts::class)->assertCanSeeTableRecords([$ownAttempt])->assertCanNotSeeTableRecords([$foreignAttempt]);

        $this->get(ExamAttemptResource::getUrl('edit', ['record' => $foreignAttempt], tenant: $this->tenant))->assertNotFound();
    }

    public function test_a_choice_question_needs_exactly_one_correct_option_and_a_bank_of_this_tenant(): void
    {
        $bank = QuestionBank::factory()->for($this->tenant)->create();
        $foreignBank = QuestionBank::factory()->for($this->otherTenant)->create();
        $this->actingInPanel($this->manager, $this->tenant);

        $options = fn (bool $secondCorrect): array => [
            ['key' => 'x', 'text' => 'یک', 'is_correct' => true],
            ['key' => 'y', 'text' => 'دو', 'is_correct' => $secondCorrect],
        ];

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$bank->id], 'type' => 'choice', 'body' => 'سؤال؟', 'score' => 1, 'options' => $options(true)])
            ->call('create')
            ->assertHasFormErrors(['options']);

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$foreignBank->id], 'type' => 'choice', 'body' => 'سؤال؟', 'score' => 1, 'options' => $options(false)])
            ->call('create')
            ->assertHasFormErrors(['banks']);

        Livewire::test(CreateQuestion::class)
            ->fillForm(['banks' => [$bank->id], 'type' => 'choice', 'body' => 'سؤال؟', 'score' => 2, 'options' => $options(false)])
            ->call('create')
            ->assertHasNoFormErrors();

        $question = Question::query()->sole();
        $this->assertSame($this->tenant->id, $question->tenant_id);
        $this->assertSame('x', $question->correctOptionKey());
        $this->assertSame([$bank->id], $question->banks->pluck('id')->all());
    }

    public function test_an_exam_takes_participants_only_from_this_tenant(): void
    {
        $bank = QuestionBank::factory()->for($this->tenant)->create();
        $member = User::factory()->create();
        $this->grantRole($member, $this->tenant, 'student');
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->otherTenant, 'student');
        $student = Role::findByName('student', 'web');
        $superAdmin = Role::findOrCreate(Role::SUPER_ADMIN, 'web');
        $this->actingInPanel($this->manager, $this->tenant);

        $data = fn (array $users, array $roles): array => [
            'title' => 'میان‌ترم',
            'sections' => [['question_bank_id' => $bank->id, 'question_count' => 3]],
            'duration_minutes' => 20, 'grace_minutes' => 2, 'questions_per_page' => 5,
            'total_score' => 20, 'passing_score' => 10, 'max_attempts' => 1,
            'retake_policy' => 'always', 'result_visibility' => 'score',
            'participantUsers' => $users, 'participantRoles' => $roles,
        ];

        Livewire::test(CreateExam::class)->fillForm($data([$outsider->id], []))->call('create')->assertHasFormErrors(['participantUsers']);
        Livewire::test(CreateExam::class)->fillForm($data([], [$superAdmin->id]))->call('create')->assertHasFormErrors(['participantRoles']);

        Livewire::test(CreateExam::class)->fillForm($data([$member->id], [$student->id]))->call('create')->assertHasNoFormErrors();

        $exam = Exam::query()->sole();
        $this->assertSame([$member->id], $exam->participantUsers->pluck('id')->all());
        $this->assertSame([$student->id], $exam->participantRoles->pluck('id')->all());
        $this->assertSame(3, $exam->sections()->sole()->question_count);
    }

    public function test_written_answers_are_graded_and_the_score_is_summed_again(): void
    {
        [$attempt, $choice, $written] = $this->submittedAttempt();
        $this->actingInPanel($this->manager, $this->tenant);

        $this->assertSame(AttemptStatus::PendingReview, $attempt->status);

        Livewire::test(GradeExamAttempt::class, ['record' => $attempt->getRouteKey()])
            ->set("data.answers.record-{$written->id}.score", 99)
            ->call('save')
            ->assertHasErrors();

        Livewire::test(GradeExamAttempt::class, ['record' => $attempt->getRouteKey()])
            ->set("data.answers.record-{$written->id}.score", 3)
            ->set("data.answers.record-{$choice->id}.score", 0)
            ->call('save')
            ->assertHasNoErrors();

        $written->refresh();
        $this->assertSame(3.0, $written->score);
        $this->assertSame($this->manager->id, $written->graded_by);
        // The choice answer keeps its automatic score.
        $this->assertSame(1.0, $choice->fresh()->score);

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Completed, $attempt->status);
        // (1 + 3) of (1 + 4) raw → 16 of 20.
        $this->assertSame(16.0, $attempt->score);
    }

    public function test_overdue_attempts_are_closed_when_the_results_are_listed(): void
    {
        $exam = Exam::factory()->for($this->tenant)->create(['grace_minutes' => 0]);
        $attempt = ExamAttempt::factory()->for($exam)->create(['deadline_at' => now()->subMinute()]);
        $running = ExamAttempt::factory()->for($exam)->create();
        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(ListExamAttempts::class);

        $this->assertTrue($attempt->fresh()->timed_out);
        $this->assertSame(AttemptStatus::Completed, $attempt->fresh()->status);
        $this->assertSame(AttemptStatus::InProgress, $running->fresh()->status);
    }

    public function test_the_edit_pages_open(): void
    {
        [$attempt] = $this->submittedAttempt();
        $question = Question::query()->firstOrFail();

        $this->actingAs($this->manager);

        $this->get(QuestionResource::getUrl('edit', ['record' => $question], tenant: $this->tenant))->assertOk();
        $this->get(ExamResource::getUrl('edit', ['record' => $attempt->exam], tenant: $this->tenant))->assertOk();
        $this->get(ExamResource::getUrl('create', tenant: $this->tenant))->assertOk();
        $this->get(ExamAttemptResource::getUrl('edit', ['record' => $attempt], tenant: $this->tenant))->assertOk()->assertSee('پاسخ');
        $this->get(QuestionBankResource::getUrl('index', tenant: $this->tenant))->assertOk();
    }

    public function test_staff_without_exam_permissions_cannot_open_the_lists(): void
    {
        $teacher = User::factory()->create();
        $this->makeStaff($teacher, $this->tenant);

        $this->actingAs($teacher)->get(ExamAttemptResource::getUrl('index', tenant: $this->tenant))->assertForbidden();
    }

    /**
     * A submitted attempt with a right choice answer (1 point) and an
     * ungraded written one (4 points).
     *
     * @return array{0: ExamAttempt, 1: AttemptAnswer, 2: AttemptAnswer}
     */
    private function submittedAttempt(): array
    {
        $this->tenant->makeCurrent();
        $bank = QuestionBank::factory()->for($this->tenant)->create();
        Question::factory()->for($this->tenant)->create()->banks()->attach($bank);
        Question::factory()->for($this->tenant)->descriptive()->create(['score' => 4])->banks()->attach($bank);
        $exam = Exam::factory()->for($this->tenant)->create();
        $exam->sections()->create(['question_bank_id' => $bank->id, 'question_count' => 2]);
        $student = User::factory()->create();

        $taking = app(ExamTaking::class);
        $attempt = $taking->start($exam, $student);
        [$choice, $written] = $attempt->answers()->get()->partition(fn (AttemptAnswer $answer): bool => $answer->isChoice())
            ->map->first()->all();
        $taking->submit($attempt, [$choice->id => 'a', $written->id => 'پاسخ']);

        return [$attempt->fresh(), $choice->fresh(), $written->fresh()];
    }
}
