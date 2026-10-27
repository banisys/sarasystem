<?php

namespace Modules\Employee\Http\Controllers;

use App\Auth\OneTimePassword;
use App\Documents\ApplicantDocuments;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\PublicSite\PublicSite;
use App\Rules\IranianMobile;
use App\Support\Identifiers;
use App\Workflow\WorkflowEngine;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Employee\Documents\EmployeeDocumentHolder;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EducationLevel;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\MilitaryStatus;
use Modules\Employee\Enums\Nationality;
use Modules\Employee\Enums\UniversityType;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Http\Requests\StoreJobApplicationRequest;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Employee\Support\ApplicantMobile;

/**
 * The public «همکاری با ما» form at /careers, on the primary tenant's site
 * only (ADR 0014).
 */
class CareersController extends Controller
{
    /** Hidden field that people leave empty and form-filling bots don't. */
    public const HONEYPOT = 'website';

    public const SUBMITTED_MESSAGE = 'درخواست همکاری شما ثبت شد. همکاران ما پس از بررسی با شما تماس می‌گیرند.';

    public function create(ApplicantMobile $applicantMobile, ApplicantDocuments $documents): Response
    {
        $tenant = $this->primaryTenant();
        [$minBirthYear, $maxBirthYear] = StoreJobApplicationRequest::birthYearRange();
        $positions = $tenant->execute(fn () => JobPosition::query()->active()->ordered()->get(['id', 'tenant_id', 'title', 'description']));

        return Inertia::render('Employee::Careers', [
            ...PublicSite::layoutProps($tenant),
            // Each with the form as set up for it (ADR 0017).
            'positions' => $positions->map(fn (JobPosition $position): array => [
                ...$position->only(['id', 'title', 'description']),
                'form' => JobApplicationForm::for($tenant, $position)->toArray(),
                // Documents the applicant uploads for it (ADR 0019).
                'documents' => $documents->fields(EmployeeDocumentHolder::KEY, $position->id),
            ])->all(),
            // Until a position is chosen.
            'form' => JobApplicationForm::for($tenant)->toArray(),
            'documents' => $documents->fields(EmployeeDocumentHolder::KEY, null),
            'sections' => (new JobApplicationForm)->sections(),
            'options' => [
                'nationalities' => self::options(Nationality::class),
                'genders' => self::options(Gender::class),
                'militaryStatuses' => self::options(MilitaryStatus::class),
                'educationLevels' => collect(EducationLevel::cases())->map(fn (EducationLevel $level): array => [
                    'value' => $level->value, 'label' => $level->getLabel(), 'university' => $level->isUniversity(),
                ])->all(),
                'universityTypes' => self::options(UniversityType::class),
            ],
            'limits' => [
                'birthYears' => [$minBirthYear, $maxBirthYear],
                'educationRows' => StoreJobApplicationRequest::MAX_EDUCATION_ROWS,
                'workRows' => StoreJobApplicationRequest::MAX_WORK_ROWS,
                'resumeMegabytes' => StoreJobApplicationRequest::RESUME_MAX_KILOBYTES / 1024,
            ],
            'honeypot' => self::HONEYPOT,
            'mobileVerification' => [
                'verifiedMobile' => $applicantMobile->verified(),
                'codeLength' => OneTimePassword::LENGTH,
                'resendAfterSeconds' => OneTimePassword::RESEND_AFTER_SECONDS,
            ],
        ]);
    }

    /**
     * Sends a code to the applicant's mobile; the form is saved only with a
     * confirmed mobile (ADR 0014).
     */
    public function sendCode(Request $request, ApplicantMobile $applicantMobile): RedirectResponse
    {
        $this->primaryTenant();
        $mobile = $this->validatedMobile($request);

        if (($wait = $applicantMobile->secondsUntilResend($mobile)) > 0) {
            throw ValidationException::withMessages(['mobile' => "{$wait} ثانیه‌ی دیگر می‌توانید کد جدید بگیرید."]);
        }

        $applicantMobile->sendCode($mobile);

        return back();
    }

    public function verifyCode(Request $request, ApplicantMobile $applicantMobile): RedirectResponse
    {
        $this->primaryTenant();
        $mobile = $this->validatedMobile($request);
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'کد پیامک‌شده را وارد کنید.']);

        if (! $applicantMobile->verify($mobile, Identifiers::toLatinDigits($request->string('code')->trim()->toString()))) {
            throw ValidationException::withMessages(['code' => 'کد وارد شده درست نیست یا منقضی شده است.']);
        }

        return back();
    }

    /**
     * Applying again while an application is open updates it, wherever it
     * is (from the same mobile and before review only, see
     * StoreJobApplicationRequest); otherwise a new application is filed with
     * the primary tenant and its position's workflow starts.
     */
    public function store(Request $request, ApplicantMobile $applicantMobile, WorkflowEngine $engine, ApplicantDocuments $documents): RedirectResponse
    {
        $tenant = $this->primaryTenant();

        if ($request->filled(self::HONEYPOT)) {
            return to_route('careers.create')->with('status', self::SUBMITTED_MESSAGE);
        }

        $form = app(StoreJobApplicationRequest::class);

        $tenant->execute(fn () => DB::transaction(function () use ($form, $engine, $documents): void {
            $position = JobPosition::query()->findOrFail($form->validated('job_position_id'));
            $application = JobApplication::openFor($form->validated('national_code')) ?? new JobApplication(['status' => ApplicationStatus::Submitted]);

            $application->fill([
                ...$form->applicationData(),
                'job_position_id' => $position->getKey(),
                'job_title' => $position->title,
            ])->save();

            // The résumé may be optional or not asked (ADR 0017); a resent form keeps the old one.
            if ($form->validated('resume') !== null) {
                $application->addMedia($form->validated('resume'))
                    ->usingFileName('resume.pdf')
                    ->toMediaCollection(JobApplication::RESUME_COLLECTION);
            }

            $documents->store($application, (array) $form->file(ApplicantDocuments::INPUT, []), (array) $form->input(ApplicantDocuments::EXPIRY_INPUT, []));

            // A new application starts its position's hiring workflow; a
            // resent one is noted in the running case (ADR 0016).
            $case = $application->workflowCase;

            if ($case?->isInProgress()) {
                $engine->recordSubjectUpdate($case, 'متقاضی فرم را دوباره فرستاد.');
            } elseif ($application->wasRecentlyCreated && $position->workflow?->is_active) {
                $engine->start($position->workflow, $application);
            }
        }));

        $applicantMobile->forget();

        return to_route('careers.create')->with('status', self::SUBMITTED_MESSAGE);
    }

    private function validatedMobile(Request $request): string
    {
        $request->validate(['mobile' => ['required', new IranianMobile]], ['mobile.required' => 'شماره موبایل را وارد کنید.']);

        return Identifiers::normalizeMobile($request->string('mobile')->toString());
    }

    private function primaryTenant(): Tenant
    {
        return Tenant::primary() ?? abort(404);
    }

    /**
     * @param  class-string<\BackedEnum&HasLabel>  $enum
     * @return list<array{value: string, label: string}>
     */
    private static function options(string $enum): array
    {
        return array_map(fn ($case): array => ['value' => $case->value, 'label' => $case->getLabel()], $enum::cases());
    }
}
