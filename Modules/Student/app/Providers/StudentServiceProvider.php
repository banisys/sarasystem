<?php

namespace Modules\Student\Providers;

use App\Authorization\DefaultRoles;
use App\Contract\ContractParties;
use App\CustomFields\CustomForms;
use App\Documents\DocumentHolders;
use App\Workflow\WorkflowSubjects;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Student\ContractParties\LearnerContractParty;
use Modules\Student\Documents\LearnerDocumentHolder;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Workflow\PreregistrationSubject;
use Nwidart\Modules\Support\ModuleServiceProvider;

class StudentServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Student';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'student';

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
            DefaultRoles::ORG_ADMIN => DefaultRoles::manage('Program'),
            DefaultRoles::REGISTRAR => [...DefaultRoles::manage('Preregistration'), ...DefaultRoles::manage('Student')],
            DefaultRoles::EDUCATION_OFFICER => DefaultRoles::view('Student'),
            DefaultRoles::FINANCE_OFFICER => DefaultRoles::view('Student'),
        ]);

        // The public form has no captcha: a few submissions, and a few SMS
        // codes sent or checked, per hour per IP (ADR 0018).
        RateLimiter::for('preregister', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('preregister-mobile', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));

        // Admission runs as a workflow (ADR 0016).
        WorkflowSubjects::register(new PreregistrationSubject);

        // Each organization sets up the «پیش‌ثبت‌نام» form (ADR 0017).
        CustomForms::register(new PreregistrationForm);

        // Learners and applicants have documents (ADR 0019).
        DocumentHolders::register(new LearnerDocumentHolder);

        // Learners sign contracts, prepared while they apply (ADR 0020).
        ContractParties::register(new LearnerContractParty);
    }
}
