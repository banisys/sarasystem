<?php

namespace Modules\Student\Http\Controllers;

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
use Modules\Student\Documents\LearnerDocumentHolder;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\Nationality;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Http\Requests\StorePreregistrationRequest;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Support\PreregistrationMobile;

/**
 * The public «پیش‌ثبت‌نام» form at /preregister, on the primary tenant's
 * site only (ADR 0018).
 */
class PreregistrationController extends Controller
{
    /** Hidden field that people leave empty and form-filling bots don't. */
    public const HONEYPOT = 'website';

    public const SUBMITTED_MESSAGE = 'پیش‌ثبت‌نام شما ثبت شد. همکاران ما پس از بررسی با شما تماس می‌گیرند.';

    public function create(PreregistrationMobile $applicantMobile, ApplicantDocuments $documents): Response
    {
        $tenant = $this->primaryTenant();
        $programs = $tenant->execute(fn () => Program::query()->active()->ordered()->get(['id', 'tenant_id', 'title', 'description']));

        return Inertia::render('Student::Preregister', [
            ...PublicSite::layoutProps($tenant),
            // Each with the form as set up for it (ADR 0017).
            'programs' => $programs->map(fn (Program $program): array => [
                ...$program->only(['id', 'title', 'description']),
                'form' => PreregistrationForm::for($tenant, $program)->toArray(),
                // Documents the applicant uploads for it (ADR 0019).
                'documents' => $documents->fields(LearnerDocumentHolder::KEY, $program->id),
            ])->all(),
            // Until a program is chosen.
            'form' => PreregistrationForm::for($tenant)->toArray(),
            'documents' => $documents->fields(LearnerDocumentHolder::KEY, null),
            'sections' => (new PreregistrationForm)->sections(),
            'options' => [
                'nationalities' => self::options(Nationality::class),
                'genders' => self::options(Gender::class),
                'tenants' => StorePreregistrationRequest::preferredTenants()->map->only(['id', 'name'])->all(),
            ],
            'labels' => [
                'tenant' => config('sarasystem.tenant_label'),
                'learner' => config('sarasystem.learner_label'),
            ],
            'limits' => [
                'birthYears' => StorePreregistrationRequest::birthYearRange(),
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
     * confirmed mobile.
     */
    public function sendCode(Request $request, PreregistrationMobile $applicantMobile): RedirectResponse
    {
        $this->primaryTenant();
        $mobile = $this->validatedMobile($request);

        if (($wait = $applicantMobile->secondsUntilResend($mobile)) > 0) {
            throw ValidationException::withMessages(['mobile' => "{$wait} ثانیه‌ی دیگر می‌توانید کد جدید بگیرید."]);
        }

        $applicantMobile->sendCode($mobile);

        return back();
    }

    public function verifyCode(Request $request, PreregistrationMobile $applicantMobile): RedirectResponse
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
     * Sending the form again while a preregistration is open updates it,
     * wherever it is (from the same mobile and before review only, see
     * StorePreregistrationRequest); otherwise a new one is filed with the
     * primary tenant and its program's workflow starts.
     */
    public function store(Request $request, PreregistrationMobile $applicantMobile, WorkflowEngine $engine, ApplicantDocuments $documents): RedirectResponse
    {
        $tenant = $this->primaryTenant();

        if ($request->filled(self::HONEYPOT)) {
            return to_route('preregister.create')->with('status', self::SUBMITTED_MESSAGE);
        }

        $form = app(StorePreregistrationRequest::class);

        $tenant->execute(fn () => DB::transaction(function () use ($form, $engine, $documents): void {
            $preregistration = Preregistration::openFor($form->validated('national_code')) ?? new Preregistration(['status' => PreregistrationStatus::Submitted]);
            $preregistration->fill($form->preregistrationData())->save();
            $documents->store($preregistration, (array) $form->file(ApplicantDocuments::INPUT, []), (array) $form->input(ApplicantDocuments::EXPIRY_INPUT, []));

            // A new preregistration starts its program's admission workflow;
            // a resent one is noted in the running case (ADR 0016).
            $case = $preregistration->workflowCase;
            $workflow = $form->program()->workflow;

            if ($case?->isInProgress()) {
                $engine->recordSubjectUpdate($case, 'فرم پیش‌ثبت‌نام دوباره فرستاده شد.');
            } elseif ($preregistration->wasRecentlyCreated && $workflow?->is_active) {
                $engine->start($workflow, $preregistration);
            }
        }));

        $applicantMobile->forget();

        return to_route('preregister.create')->with('status', self::SUBMITTED_MESSAGE);
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
