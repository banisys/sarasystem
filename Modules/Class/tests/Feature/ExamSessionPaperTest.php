<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewExamSession;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Models\Employee;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExamSessionPaperTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }
    }

    public function test_an_active_employee_without_class_or_exam_permissions_can_print_only_the_paper(): void
    {
        if (! Module::isEnabled('Employee')) {
            $this->markTestSkipped('Employee is disabled.');
        }

        $branch = Tenant::factory()->create();
        $session = $this->examSession($branch);
        $user = User::factory()->create();
        $this->makeStaff($user, $branch);
        $employee = Employee::factory()->for($branch)->for($user)->create();
        $exam = $this->attachExam($session);
        $exam->forceFill(['question_snapshot' => [[
            'type' => QuestionType::Descriptive->value, 'body' => 'متن برگهٔ ذخیره‌شده',
            'score' => 3.75, 'model_answer' => 'پاسخ محرمانهٔ تصحیح',
        ]]])->save();

        $this->actingInPanel($user, $branch);
        $url = ClassSessionResource::detailsUrl($session, $branch);
        $this->get($url)->assertOk()->assertSee('برگهٔ آزمون جلسه')->assertDontSee('محل مخصوص مدیر');
        Livewire::test(ViewExamSession::class, ['record' => $session->id])
            ->assertActionDoesNotExist('seatLayout')
            ->assertActionDoesNotExist('sessionExam')
            ->assertActionDoesNotExist('editExamSchedule')
            ->mountAction('sessionExams')
            ->assertNoRedirect()
            ->assertMountedActionModalSee([
                'متن برگهٔ ذخیره‌شده', 'چاپ آزمون', 'ردیف', 'شرح سؤال', 'جمع نمرات برگه',
                '۳٫۷۵', $branch->name, $session->classroom->title,
            ])
            ->assertMountedActionModalDontSee('پاسخ محرمانهٔ تصحیح');

        $employee->update(['status' => EmploymentStatus::Suspended]);
        $this->get($url)->assertForbidden();
    }

    public function test_a_user_employed_in_a_different_branch_cannot_open_the_paper(): void
    {
        if (! Module::isEnabled('Employee')) {
            $this->markTestSkipped('Employee is disabled.');
        }

        $branch = Tenant::factory()->create();
        $session = $this->examSession($branch);
        $user = User::factory()->create();
        $this->makeStaff($user, $branch);
        Employee::factory()->for(Tenant::factory())->for($user)->create();

        $this->actingInPanel($user, $branch);
        $this->get(ClassSessionResource::detailsUrl($session, $branch))->assertForbidden();
    }

    public function test_duplicate_legacy_exams_keep_their_records_and_the_modal_shows_one_paper(): void
    {
        $branch = Tenant::factory()->create();
        $session = $this->examSession($branch);
        $this->actingInPanel(User::factory()->superAdmin()->create(), $branch);

        Livewire::test(ViewExamSession::class, ['record' => $session->id])->mountAction('sessionExams')
            ->assertMountedActionModalSee('برای این جلسه هنوز آزمونی تعریف نشده است.')
            ->assertMountedActionModalDontSee('چاپ آزمون');

        $first = $this->attachExam($session, 'برگهٔ قبلی');
        $second = $this->attachExam($session, 'برگهٔ فعلی');
        $bank = QuestionBank::factory()->for($branch)->create();
        $question = Question::factory()->for($branch)->create(['body' => 'سؤال بانک برگه']);
        $bank->questions()->attach($question);
        $second->sections()->create(['question_bank_id' => $bank->id, 'question_count' => 1]);

        Livewire::test(ViewExamSession::class, ['record' => $session->id])->mountAction('sessionExams')
            ->assertMountedActionModalSee([$second->title, 'سؤال بانک برگه', 'چاپ آزمون'])
            ->assertMountedActionModalDontSee($first->title);

        $this->assertModelExists($first);
    }

    private function examSession(Tenant $tenant): ClassSession
    {
        return ClassSession::factory()->for(Classroom::factory()->for($tenant))->create([
            'kind' => SessionKind::Exam,
            'location' => 'محل مخصوص مدیر',
        ]);
    }

    private function attachExam(ClassSession $session, string $title = 'آزمون نمونه'): Exam
    {
        $exam = Exam::factory()->for($session->tenant)->create(['title' => $title]);
        $exam->forceFill(['context_type' => 'class-session', 'context_id' => $session->id])->save();

        return $exam;
    }
}
