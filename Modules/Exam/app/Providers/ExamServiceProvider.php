<?php

namespace Modules\Exam\Providers;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use App\UserPanel\UserPanel;
use Modules\Exam\Models\Exam;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ExamServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Exam';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'exam';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // The screens' permissions for the default roles (ADR 0039).
        app(DefaultRoles::class)->grant([
            DefaultRoles::EDUCATION_OFFICER => [...DefaultRoles::manage('QuestionBank'), ...DefaultRoles::manage('Question'), ...DefaultRoles::manage('Exam'), ...DefaultRoles::manage('ExamAttempt')],
        ]);

        // «آزمون‌ها» in the user-panel header, for participants of a
        // published exam in the current tenant (ADR 0013).
        UserPanel::navigationUsing('exams', fn (User $user, Tenant $tenant): array => Exam::query()->published()->forParticipant($user)->exists()
            ? [['title' => 'آزمون‌ها', 'url' => route('exams.index')]]
            : []);
    }
}
