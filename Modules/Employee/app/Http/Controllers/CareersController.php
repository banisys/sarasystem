<?php

namespace Modules\Employee\Http\Controllers;

use App\Auth\OneTimePassword;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\PublicSite\PublicSite;
use App\Rules\IranianMobile;
use App\Support\Identifiers;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EducationLevel;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\MilitaryStatus;
use Modules\Employee\Enums\Nationality;
use Modules\Employee\Enums\UniversityType;
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

    public function create(ApplicantMobile $applicantMobile): Response
    {
        $tenant = $this->primaryTenant();
        [$minBirthYear, $maxBirthYear] = StoreJobApplicationRequest::birthYearRange();

        return Inertia::render('Employee::Careers', [
            ...PublicSite::layoutProps($tenant),
            'positions' => $tenant->execute(fn (): array => JobPosition::query()->active()->ordered()->get(['id', 'title', 'description'])->all()),
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
     * is (from the same mobile only, see StoreJobApplicationRequest);
     * otherwise a new application is filed with the primary tenant.
     */
    public function store(Request $request, ApplicantMobile $applicantMobile): RedirectResponse
    {
        $tenant = $this->primaryTenant();

        if ($request->filled(self::HONEYPOT)) {
            return to_route('careers.create')->with('status', self::SUBMITTED_MESSAGE);
        }

        $form = app(StoreJobApplicationRequest::class);

        $tenant->execute(fn () => DB::transaction(function () use ($form): void {
            $position = JobPosition::query()->findOrFail($form->validated('job_position_id'));
            $application = JobApplication::openFor($form->validated('national_code')) ?? new JobApplication(['status' => ApplicationStatus::Submitted]);

            $application->fill([
                ...$form->applicationData(),
                'job_position_id' => $position->getKey(),
                'job_title' => $position->title,
            ])->save();

            $application->addMedia($form->file('resume'))
                ->usingFileName('resume.pdf')
                ->toMediaCollection(JobApplication::RESUME_COLLECTION);
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
