<?php

namespace Modules\Exam\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\ExamGroup;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ExamPerformanceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_the_learner_menu_shows_the_report_before_any_exams_are_assigned(): void
    {
        $tenant = Tenant::factory()->create();
        $learner = User::factory()->create();
        $this->grantRole($learner, $tenant, DefaultRoles::LEARNER);

        $this->actingAs($learner)->get(route('dashboard'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('navigation', fn ($items) => $items->contains('url', route('exams.performance'))));

        $this->get(route('exams.performance'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Exam::Performance')
            ->has('groups', 0)
            ->has('subjects', 0));
    }

    public function test_a_participant_can_switch_groups_and_see_their_latest_finished_score(): void
    {
        $tenant = Tenant::factory()->create();
        $learner = User::factory()->create();
        $this->grantRole($learner, $tenant, 'exam-participant');
        $aban = ExamGroup::factory()->for($tenant)->create(['title' => 'آزمون‌های کلاسی آبان']);
        $azar = ExamGroup::factory()->for($tenant)->create(['title' => 'آزمون‌های کلاسی آذر']);
        $exam = Exam::factory()->for($tenant)->for($aban, 'examGroup')->hasAttached($learner, relationship: 'participantUsers')->create();
        $otherExam = Exam::factory()->for($tenant)->for($azar, 'examGroup')->hasAttached($learner, relationship: 'participantUsers')->create();
        $this->recordScore($exam, $learner, 17);
        $this->recordScore($exam, $learner, 0);
        ExamAttempt::factory()->for($exam)->for($learner, 'user')->create();
        $this->recordScore($exam, User::factory()->create(), 19);
        $this->recordScore($otherExam, $learner, 14.5);

        $this->actingAs($learner)->get(route('exams.performance'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Exam::Performance')
            ->has('groups', 2)
            ->where('groups.0.title', $azar->title)
            ->where('groups.1.title', $aban->title)
            ->where('selectedGroup', $azar->id)
            ->where('subjects.0.exams.0.score', 14.5)
            ->where('navigation', fn ($items) => $items->contains('title', 'گزارش عملکرد')));

        $this->get(route('exams.performance', ['group' => $aban->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedGroup', $aban->id)
            ->has('subjects', 1)
            ->where('subjects.0.title', 'آزمون‌های بدون درس')
            ->has('subjects.0.exams', 1)
            ->where('subjects.0.exams.0.id', $exam->id)
            ->where('subjects.0.exams.0.score', 0)
            ->where('subjects.0.exams.0.total_score', 20));
    }

    public function test_the_report_excludes_hidden_practice_draft_ungraded_and_other_participants_results(): void
    {
        $tenant = Tenant::factory()->create();
        $learner = User::factory()->create();
        $this->grantRole($learner, $tenant, DefaultRoles::LEARNER);
        $group = ExamGroup::factory()->for($tenant)->create();
        $exams = Exam::factory()->for($tenant)->for($group, 'examGroup')->hasAttached($learner, relationship: 'participantUsers');
        foreach ([
            ['result_visibility' => ResultVisibility::Hidden],
            ['result_visibility' => ResultVisibility::PassFail],
            ['is_practice' => true],
            ['is_published' => false],
        ] as $settings) {
            $this->recordScore($exams->create($settings), $learner, 18);
        }
        $pendingExam = $exams->create();
        $this->recordScore($pendingExam, $learner, 13);
        ExamAttempt::factory()->for($pendingExam)->for($learner, 'user')->create(['status' => AttemptStatus::PendingReview]);
        $this->recordScore($exams->create(), User::factory()->create(), 11);
        $notAssigned = Exam::factory()->for($tenant)->for($group, 'examGroup')->create();
        $this->recordScore($notAssigned, $learner, 16);

        $this->actingAs($learner)->get(route('exams.performance'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selectedGroup', $group->id)
            ->has('subjects', 0));
    }

    public function test_a_group_from_another_branch_cannot_be_selected_even_by_its_participant(): void
    {
        $current = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $learner = User::factory()->create();
        $this->grantRole($learner, $current, 'staff');
        $this->grantRole($learner, $other, DefaultRoles::LEARNER);
        $foreignGroup = ExamGroup::factory()->for($other)->create();
        $foreignExam = Exam::factory()->for($other)->for($foreignGroup, 'examGroup')->hasAttached($learner, relationship: 'participantUsers')->create();
        $this->recordScore($foreignExam, $learner, 18);

        $this->actingAs($learner)->withSession([ResolveCurrentTenant::SESSION_KEY => $current->id])
            ->get(route('exams.performance', ['group' => $foreignGroup->id]))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('groups', 0)
            ->where('selectedGroup', null)
            ->has('subjects', 0)
            ->where('navigation', fn ($items) => ! $items->contains('title', 'گزارش عملکرد')));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('exams.performance'))->assertRedirect(route('login'));
    }

    private function recordScore(Exam $exam, User $learner, float $score): void
    {
        ExamAttempt::factory()->for($exam)->for($learner, 'user')->create([
            'status' => AttemptStatus::Completed,
            'submitted_at' => now(),
            'score' => $score,
        ]);
    }
}
