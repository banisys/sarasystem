<?php

namespace Modules\Student\Http\Controllers;

use App\Auth\OneTimePassword;
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
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\Nationality;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Http\Requests\StorePreregistrationRequest;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;
use Modules\Student\Support\GuardianMobile;

/**
 * The public student «پیش‌ثبت‌نام» form at /preregister, on the primary
 * tenant's site only (ADR 0018).
 */
class PreregistrationController extends Controller
{
    /** Hidden field that people leave empty and form-filling bots don't. */
    public const HONEYPOT = 'website';

    public const SUBMITTED_MESSAGE = 'پیش‌ثبت‌نام شما ثبت شد. همکاران ما پس از بررسی با شما تماس می‌گیرند.';

    public function create(GuardianMobile $guardianMobile): Response
    {
        $tenant = $this->primaryTenant();
        $stages = $tenant->execute(fn () => Grade::query()
            ->active()
            ->stages()
            ->ordered()
            ->with(['children' => fn ($query) => $query->active()->ordered()])
            ->get());

        return Inertia::render('Student::Preregister', [
            ...PublicSite::layoutProps($tenant),
            // Each with its grades and the form as set up for it (ADR 0017).
            'stages' => $stages->map(fn (Grade $stage): array => [
                ...$stage->only(['id', 'title', 'description']),
                'grades' => $stage->children->map->only(['id', 'title'])->all(),
                'form' => PreregistrationForm::for($tenant, $stage)->toArray(),
            ])->all(),
            // Until a stage is chosen.
            'form' => PreregistrationForm::for($tenant)->toArray(),
            'sections' => (new PreregistrationForm)->sections(),
            'options' => [
                'nationalities' => self::options(Nationality::class),
                'genders' => self::options(Gender::class),
                'tenants' => StorePreregistrationRequest::preferredTenants()->map->only(['id', 'name'])->all(),
            ],
            'tenantLabel' => config('sarasystem.tenant_label'),
            'limits' => [
                'birthYears' => StorePreregistrationRequest::birthYearRange(),
            ],
            'honeypot' => self::HONEYPOT,
            'mobileVerification' => [
                'verifiedMobile' => $guardianMobile->verified(),
                'codeLength' => OneTimePassword::LENGTH,
                'resendAfterSeconds' => OneTimePassword::RESEND_AFTER_SECONDS,
            ],
        ]);
    }

    /**
     * Sends a code to the family's mobile; the form is saved only with a
     * confirmed mobile.
     */
    public function sendCode(Request $request, GuardianMobile $guardianMobile): RedirectResponse
    {
        $this->primaryTenant();
        $mobile = $this->validatedMobile($request);

        if (($wait = $guardianMobile->secondsUntilResend($mobile)) > 0) {
            throw ValidationException::withMessages(['mobile' => "{$wait} ثانیه‌ی دیگر می‌توانید کد جدید بگیرید."]);
        }

        $guardianMobile->sendCode($mobile);

        return back();
    }

    public function verifyCode(Request $request, GuardianMobile $guardianMobile): RedirectResponse
    {
        $this->primaryTenant();
        $mobile = $this->validatedMobile($request);
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'کد پیامک‌شده را وارد کنید.']);

        if (! $guardianMobile->verify($mobile, Identifiers::toLatinDigits($request->string('code')->trim()->toString()))) {
            throw ValidationException::withMessages(['code' => 'کد وارد شده درست نیست یا منقضی شده است.']);
        }

        return back();
    }

    /**
     * Sending the form again while a preregistration is open updates it,
     * wherever it is (from the same mobile and before review only, see
     * StorePreregistrationRequest); otherwise a new one is filed with the
     * primary tenant and its stage's workflow starts.
     */
    public function store(Request $request, GuardianMobile $guardianMobile, WorkflowEngine $engine): RedirectResponse
    {
        $tenant = $this->primaryTenant();

        if ($request->filled(self::HONEYPOT)) {
            return to_route('preregister.create')->with('status', self::SUBMITTED_MESSAGE);
        }

        $form = app(StorePreregistrationRequest::class);

        $tenant->execute(fn () => DB::transaction(function () use ($form, $engine): void {
            $preregistration = Preregistration::openFor($form->validated('national_code')) ?? new Preregistration(['status' => PreregistrationStatus::Submitted]);
            $preregistration->fill($form->preregistrationData())->save();

            // A new preregistration starts its stage's admission workflow;
            // a resent one is noted in the running case (ADR 0016).
            $case = $preregistration->workflowCase;
            $workflow = $form->grade()->stage()->workflow;

            if ($case?->isInProgress()) {
                $engine->recordSubjectUpdate($case, 'فرم پیش‌ثبت‌نام دوباره فرستاده شد.');
            } elseif ($preregistration->wasRecentlyCreated && $workflow?->is_active) {
                $engine->start($workflow, $preregistration);
            }
        }));

        $guardianMobile->forget();

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
