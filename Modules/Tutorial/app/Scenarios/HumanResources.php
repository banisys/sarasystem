<?php

namespace Modules\Tutorial\Scenarios;

use App\Authorization\DefaultRoles;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\AssigneeType;
use App\Workflow\CaseActionType;
use App\Workflow\StepAction;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Enums\SignedVia;
use Modules\Contract\Filament\Resources\Contracts\ContractResource;
use Modules\Contract\Filament\Resources\ContractTypes\ContractTypeResource;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\Contracts;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Enums\FileKind;
use Modules\Document\Filament\Resources\DocumentTypes\DocumentTypeResource;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Filament\Resources\JobPositions\JobPositionResource;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Employee\Services\RegisterEmployee;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Concerns\AppliesForJobs;
use Modules\Tutorial\Scenarios\Concerns\FindsEmployees;

/**
 * «کارگزینی: مدارک و قراردادها» (ADR 0040, docs/tutorial-scenarios.md): a
 * staff member's paperwork from application to renewal. A hiring workflow
 * across tenants with an edit and a return, documents with expiry, a
 * contract type with its own fields, the electronic and the paper
 * signature, an amendment, a renewal, a contract ending soon and a
 * cancellation.
 */
class HumanResources extends Scenario
{
    use AppliesForJobs, FindsEmployees;

    private const string SAMPLE_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    /** Added fields of the contract type and the job position. */
    private const string SALARY_FIELD = 'tutorial_monthly_salary';

    private const string HOURS_FIELD = 'tutorial_weekly_hours';

    private const string SOFTWARE_FIELD = 'tutorial_accounting_software';

    public function key(): string
    {
        return 'human-resources';
    }

    public function title(): string
    {
        return 'کارگزینی: مدارک و قراردادها';
    }

    public function description(): string
    {
        return 'پرونده‌ی یک کارمند از درخواست همکاری تا تمدید قرارداد: فرایند استخدام بین ستاد و '.config('sarasystem.tenant_label').' با ویرایش و برگشت، مدارک تاریخ‌دار، نوع قرارداد با فیلدهای خودش، امضای الکترونیکی و کاغذی، الحاقیه، تمدید، قرارداد رو به پایان و لغو.';
    }

    public function requiredModules(): array
    {
        return ['Contract', 'Document', 'Employee'];
    }

    public function chapters(): array
    {
        $tenant = config('sarasystem.tenant_label');
        $tenants = config('sarasystem.tenant_plural_label');
        $primary = Tenant::primary()?->name ?? 'ستاد';
        $manager = 'مدیر '.$tenant;
        $myCartable = fn (): string => WorkflowCaseResource::getUrl('index', panel: 'admin', tenant: Tenant::primary());
        $managerCartable = fn (TutorialRun $run): ?string => self::branchUrl($run, WorkflowCaseResource::class);

        return [
            'آماده‌سازی' => [
                Mission::make('branch', "{$tenant} و مدیر آن")
                    ->steps([
                        "در «{$tenants}» «شعبه مرکزی» با نشانی «markazi» بسازید.",
                        "{$tenant} را «شعبه مرکزی» کنید و در «کارکنان → کارمندان» با «ثبت سریع کارمند»، «بهرام قاسمی» (کد ملی ۰۱۰۰۱۲۰۱۰۵) را با نقش «{$manager}» و دسترسی به پنل ثبت کنید.",
                    ])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => self::branch($run) === null
                        ? ['name' => 'شعبه مرکزی', 'slug' => 'markazi']
                        : ['national_code' => '0100120105', 'name' => 'بهرام قاسمی', 'mobile' => '09120000031', 'job_title' => 'مدیر شعبه', 'role_ids' => self::roleIds(DefaultRoles::TENANT_MANAGER), 'panel_access' => true])
                    ->perform(function (TutorialRun $run, User $owner): void {
                        $branch = Tenant::query()->create(['name' => 'شعبه مرکزی', 'slug' => 'markazi', 'is_active' => true]);
                        app(RegisterEmployee::class)->register(
                            $owner,
                            $branch,
                            person: ['name' => 'بهرام قاسمی', 'national_code' => '0100120105', 'mobile' => '09120000031'],
                            employment: ['job_title' => 'مدیر شعبه', 'employment_type' => EmploymentType::FullTime, 'hired_at' => today()],
                            roleIds: self::roleIds(DefaultRoles::TENANT_MANAGER),
                            panelAccess: true,
                        );
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::manager($run) !== null),
                Mission::make('document-types', 'انواع مدرک')
                    ->steps([
                        "{$tenant} را به «{$primary}» برگردانید؛ در «مدارک → انواع مدرک» «کارت ملی» را بسازید.",
                        '«گواهی سلامت» را هم بسازید و «تاریخ انقضا دارد» را روشن کنید.',
                    ])
                    ->url(fn (): string => DocumentTypeResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => self::idCard($run) === null ? ['title' => 'کارت ملی'] : ['title' => 'گواهی سلامت', 'has_expiry' => true])
                    ->perform(function (): void {
                        foreach ([['کارت ملی', false], ['گواهی سلامت', true]] as [$title, $hasExpiry]) {
                            DocumentType::query()->create([
                                'tenant_id' => Tenant::primary()->getKey(), 'title' => $title, 'file_kind' => FileKind::ImageOrPdf,
                                'max_files' => 1, 'max_kilobytes' => 5120, 'has_expiry' => $hasExpiry, 'is_active' => true,
                            ]);
                        }
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::idCard($run) !== null && self::healthCertificate($run) !== null),
                Mission::make('contract-type', 'نوع قرارداد با فیلدهای خودش')
                    ->steps([
                        'در «قراردادها → انواع قرارداد» «قرارداد کار معین» را بسازید؛ روش امضا «الکترونیکی».',
                        'در متن با «{{» نام، کد ملی، تاریخ شروع و پایان و «مبلغ به حروف» را درج کنید.',
                        'از منوی ردیف «فیلدهای قرارداد» را بزنید: «مبلغ» را «الزامی» کنید و فیلدهای عددی «حقوق ماهانه» و «ساعت کار هفتگی» را اضافه کنید.',
                    ])
                    ->url(fn (): string => ContractTypeResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['title' => 'قرارداد کار معین', 'signature_method' => SignatureMethod::Electronic->value])
                    ->perform(function (): void {
                        $type = ContractType::query()->create([
                            'tenant_id' => Tenant::primary()->getKey(),
                            'title' => 'قرارداد کار معین',
                            'body' => '<p>این قرارداد میان <span data-type="mergeTag" data-id="tenant_name"></span> و <span data-type="mergeTag" data-id="person_name"></span> به کد ملی <span data-type="mergeTag" data-id="national_code"></span> از <span data-type="mergeTag" data-id="starts_on"></span> تا <span data-type="mergeTag" data-id="ends_on"></span> به مبلغ <span data-type="mergeTag" data-id="amount_words"></span> ریال بسته می‌شود.</p>',
                            'signature_method' => SignatureMethod::Electronic,
                            'is_amendment' => false,
                            'is_active' => true,
                        ]);
                        FormCustomization::query()->create([
                            'tenant_id' => $type->tenant_id,
                            'form' => ContractForm::KEY,
                            'owner_type' => $type->getMorphClass(),
                            'owner_id' => $type->getKey(),
                            'field_modes' => ['amount' => 'required'],
                            'fields' => [
                                self::field(self::SALARY_FIELD, 'حقوق ماهانه', 'number'),
                                self::field(self::HOURS_FIELD, 'ساعت کار هفتگی', 'number'),
                            ],
                        ]);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::contractType($run) !== null),
                Mission::make('amendment-type', 'نوع الحاقیه')
                    ->steps([
                        'باز در «انواع قرارداد» «الحاقیه افزایش حقوق» را بسازید؛ «الحاقیه است» را روشن کنید و روش امضا را «کاغذی» بگذارید.',
                        'در متن الحاقیه می‌توانید شماره و عنوان «قرارداد اصلی / قبلی» را درج کنید.',
                    ])
                    ->url(fn (): string => ContractTypeResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['title' => 'الحاقیه افزایش حقوق', 'is_amendment' => true, 'signature_method' => SignatureMethod::Paper->value])
                    ->perform(fn (): ContractType => ContractType::query()->create([
                        'tenant_id' => Tenant::primary()->getKey(),
                        'title' => 'الحاقیه افزایش حقوق',
                        'body' => '<p>به استناد قرارداد شماره‌ی <span data-type="mergeTag" data-id="parent_number"></span>، حقوق <span data-type="mergeTag" data-id="person_name"></span> از <span data-type="mergeTag" data-id="starts_on"></span> افزایش می‌یابد.</p>',
                        'signature_method' => SignatureMethod::Paper,
                        'is_amendment' => true,
                        'is_active' => true,
                    ]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::amendmentType($run) !== null),
            ],
            'استخدام' => [
                Mission::make('workflow', 'فرایند استخدام سه‌مرحله‌ای')
                    ->steps([
                        'در «کارتابل → فرایندها» فرایند «استخدام کارشناس مالی» را برای «درخواست همکاری» بسازید.',
                        "مرحله‌ی «بررسی مدارک»: {$tenant} «{$primary}»، شخص: خودتان؛ «کارت ملی» را در «مدارکی که در این مرحله تأیید می‌شوند» بزنید و «ویرایش اطلاعات» را مجاز کنید.",
                        "مرحله‌ی «مصاحبه»: {$tenant} «شعبه مرکزی»، نقش «{$manager}»؛ «برگشت» را مجاز کنید.",
                        "مرحله‌ی «تنظیم قرارداد»: {$tenant} «{$primary}»، شخص: خودتان؛ «قرارداد کار معین» را در «قراردادهایی که در این مرحله تنظیم می‌شوند» بزنید.",
                    ])
                    ->url(fn (): string => WorkflowResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => 'استخدام کارشناس مالی', 'subject_type' => (new JobApplication)->getMorphClass()])
                    ->perform(fn (TutorialRun $run, User $owner): Workflow => Workflow::query()->create([
                        'subject_type' => (new JobApplication)->getMorphClass(),
                        'name' => 'استخدام کارشناس مالی',
                        'is_active' => true,
                        'steps' => [
                            (new WorkflowStep('بررسی مدارک', Tenant::primary()->getKey(), AssigneeType::User, null, $owner->getKey(), [StepAction::Edit], ['documents' => ['upload' => [], 'verify' => [(string) self::idCard($run)->getKey()]]]))->toArray(),
                            (new WorkflowStep('مصاحبه', self::branch($run)->getKey(), AssigneeType::Role, (int) self::roleIds(DefaultRoles::TENANT_MANAGER)[0], null, [StepAction::Return]))->toArray(),
                            (new WorkflowStep('تنظیم قرارداد', Tenant::primary()->getKey(), AssigneeType::User, null, $owner->getKey(), [], ['contracts' => ['prepare' => [(string) self::contractType($run)->getKey()]]]))->toArray(),
                        ],
                    ]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::workflow($run) !== null),
                Mission::make('job-position', 'موقعیت شغلی و فرم آن')
                    ->steps([
                        'در «همکاری با ما → موقعیت‌های شغلی» «کارشناس مالی» را با فرایند «استخدام کارشناس مالی» بسازید.',
                        'از منوی ردیف «فیلدهای فرم» را بزنید: «فایل رزومه» را «اختیاری» کنید و فیلد متنی «سابقه‌ی نرم‌افزار حسابداری» را اضافه کنید.',
                        'در بخش «مدارک»، «کارت ملی» را «الزامی» و «گواهی سلامت» را «اختیاری» کنید و ذخیره کنید.',
                    ])
                    ->url(fn (): string => JobPositionResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => ['title' => 'کارشناس مالی', 'workflow_id' => self::workflow($run)?->getKey(), 'is_active' => true])
                    ->perform(function (TutorialRun $run): void {
                        $position = JobPosition::query()->create([
                            'tenant_id' => Tenant::primary()->getKey(), 'workflow_id' => self::workflow($run)->getKey(), 'title' => 'کارشناس مالی', 'is_active' => true,
                        ]);
                        $position->formCustomizations()->create([
                            'tenant_id' => $position->tenant_id,
                            'form' => JobApplicationForm::KEY,
                            'field_modes' => [
                                'resume' => 'optional',
                                'document_'.self::idCard($run)->getKey() => 'required',
                                'document_'.self::healthCertificate($run)->getKey() => 'optional',
                            ],
                            'fields' => [self::field(self::SOFTWARE_FIELD, 'سابقه‌ی نرم‌افزار حسابداری', 'text')],
                        ]);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::position($run) !== null),
                Mission::make('job-application', 'درخواست همکاری با مدارک')
                    ->actor('متقاضی (فرم عمومی سایت)')
                    ->steps([
                        'در فرم «همکاری با ما» شغل «کارشناس مالی» را انتخاب کنید: فیلد تازه و بخش «مدارک» در فرم آمده‌اند.',
                        'نام «لیلا فرهادی»، کد ملی ۰۱۱۰۱۳۰۱۰۳ و موبایل خودتان (برای کد تأیید) را بنویسید.',
                        'برای «کارت ملی» یک فایل بارگذاری کنید، بقیه‌ی فیلدهای لازم را پر کنید و بفرستید.',
                    ])
                    ->url(fn (): string => route('careers.create'), 'باز کردن فرم')
                    ->perform(fn (TutorialRun $run): JobApplication => self::applyForJob(
                        self::position($run),
                        ['first_name' => 'لیلا', 'last_name' => 'فرهادی', 'national_code' => '0110130103', 'gender' => Gender::Female, 'mobile' => '09120000032'],
                        fn (JobApplication $application) => app(Documents::class)->upload($application, self::idCard($run), [self::samplePdf('id-card')], null, null),
                    ))
                    ->doneWhen(fn (TutorialRun $run): bool => self::idCardUpload($run) !== null && self::case($run) !== null),
                Mission::make('check-and-edit', 'بررسی مدارک و ویرایش اطلاعات')
                    ->steps([
                        'در «کارتابل من» پرونده‌ی «لیلا فرهادی» را باز کنید.',
                        '«بررسی مدارک» را بزنید و «کارت ملی» را تأیید کنید.',
                        'با «ویرایش اطلاعات» نام خانوادگی را «فرهادی‌نیا» کنید (تغییر در سابقه‌ی پرونده می‌ماند) و «تأیید» را بزنید.',
                    ])
                    ->url($myCartable)
                    ->perform(fn (TutorialRun $run, User $owner): mixed => Tenant::primary()->execute(function () use ($run, $owner): void {
                        $engine = app(WorkflowEngine::class);
                        app(Documents::class)->approve(self::idCardUpload($run), $owner);
                        $engine->edit(self::case($run), $owner, ['last_name' => 'فرهادی‌نیا']);
                        $engine->approve(self::case($run), $owner);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::hasAction($run, CaseActionType::Edited) && (self::case($run)?->current_step ?? 0) >= 1),
                Mission::make('return', 'برگشت از مصاحبه')
                    ->actor($manager, fn (TutorialRun $run): ?User => self::manager($run))
                    ->steps([
                        'با حساب «بهرام قاسمی» وارد شوید و در «کارتابل من» پرونده را باز کنید.',
                        '«برگشت» را بزنید و با علت «گواهی سلامت را هم بخواهید» به «بررسی مدارک» برگردانید.',
                    ])
                    ->url($managerCartable)
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run)->execute(fn () => app(WorkflowEngine::class)->returnTo(self::case($run), self::manager($run), 0, 'گواهی سلامت را هم بخواهید.')))
                    ->doneWhen(fn (TutorialRun $run): bool => self::hasAction($run, CaseActionType::Returned)),
                Mission::make('approve-again', 'تأیید دوباره و مصاحبه')
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید؛ پرونده با علت برگشت در «کارتابل من» است. یادداشتی بگذارید و «تأیید» را بزنید.',
                        'با حساب «بهرام قاسمی» دوباره وارد شوید و پس از مصاحبه «تأیید» را بزنید.',
                    ])
                    ->url($myCartable)
                    ->perform(function (TutorialRun $run, User $owner): void {
                        Tenant::primary()->execute(fn () => app(WorkflowEngine::class)->approve(self::case($run), $owner, 'گواهی سلامت پس از استخدام گرفته می‌شود.'));
                        self::branch($run)->execute(fn () => app(WorkflowEngine::class)->approve(self::case($run), self::manager($run)));
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::hasAction($run, CaseActionType::Returned) && (self::case($run)?->current_step ?? 0) >= 2),
                Mission::make('contract-draft', 'تنظیم قرارداد')
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید؛ در «کارتابل من» «تنظیم قرارداد» را بزنید.',
                        'تاریخ شروع امروز، پایان یک سال بعد، مبلغ، حقوق ماهانه و ساعت کار هفتگی را بنویسید و ذخیره کنید؛ «متن قرارداد» را ببینید.',
                    ])
                    ->url($myCartable)
                    ->perform(fn (TutorialRun $run, User $owner): mixed => Tenant::primary()->execute(function () use ($run, $owner): void {
                        app(Contracts::class)->saveDraft(null, self::contractType($run), self::application($run), [
                            'starts_on' => today()->toDateString(),
                            'ends_on' => today()->addYear()->subDay()->toDateString(),
                            'amount' => 3600000000,
                            'custom' => [self::SALARY_FIELD => 300000000, self::HOURS_FIELD => 44],
                        ], $owner);
                        // As the cartable's «تنظیم قرارداد» notes it in the case.
                        app(WorkflowEngine::class)->comment(self::case($run), $owner, 'قرارداد تنظیم شد: '.self::contractType($run)->title);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::mainContract($run)?->amount !== null),
                Mission::make('hire', 'استخدام و صدور قرارداد')
                    ->steps([
                        "«تأیید» را بزنید: {$tenant} محل خدمت «شعبه مرکزی»، نقش «کارمند»، دسترسی به پنل خاموش.",
                        'لیلا کارمند شعبه می‌شود، مدرکش به پرونده‌ی کارمندی‌اش می‌رود، و قرارداد صادر و برای امضا به او پیامک می‌شود.',
                    ])
                    ->url($myCartable)
                    ->fill(fn (TutorialRun $run): array => ['tenant_id' => self::branch($run)?->getKey(), 'role_ids' => self::roleIds(DefaultRoles::STAFF), 'panel_access' => false])
                    ->perform(fn (TutorialRun $run, User $owner): mixed => Tenant::primary()->execute(fn () => app(WorkflowEngine::class)->approve(self::case($run), $owner, data: [
                        'tenant_id' => self::branch($run)->getKey(),
                        'role_ids' => self::roleIds(DefaultRoles::STAFF),
                        'panel_access' => false,
                        'employment_type' => EmploymentType::Contract->value,
                        'hired_at' => today()->toDateString(),
                    ])))
                    ->doneWhen(fn (TutorialRun $run): bool => self::employee($run) !== null && self::mainContract($run)?->status === ContractStatus::Issued),
            ],
            'پرونده‌ی کارمند' => [
                Mission::make('health-certificate', 'مدرک تاریخ‌دار')
                    ->steps([
                        "{$tenant} را «شعبه مرکزی» کنید؛ در «کارکنان → کارمندان» ردیف لیلا را «مشاهده» کنید.",
                        'در زبانه‌ی «مدارک» «بارگذاری مدرک» را بزنید: «گواهی سلامت» با تاریخ انقضای یک ماه بعد؛ بعد آن را تأیید کنید.',
                        'فهرست «مدارک → مدارک» فیلتر انقضا دارد و مدرک نزدیک به انقضا را نشان می‌دهد.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, EmployeeResource::class))
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::branch($run)->execute(function () use ($run, $owner): void {
                        $documents = app(Documents::class);
                        $document = $documents->upload(self::employee($run), self::healthCertificate($run), [self::samplePdf('health')], today()->addMonth()->toDateString(), $owner);
                        $documents->approve($document, $owner);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::healthUpload($run)?->status === DocumentStatus::Approved && self::healthUpload($run)->expires_at !== null),
                Mission::make('e-sign', 'امضای الکترونیکی')
                    ->actor('کارمند (لیلا فرهادی‌نیا)', fn (TutorialRun $run): ?User => self::employee($run)?->user)
                    ->steps([
                        'با حساب لیلا وارد شوید و در پنل کاربری «قراردادها» را باز کنید.',
                        'قرارداد را باز کنید؛ در «امضای قرارداد» متن را بپذیرید، کدی را که به موبایل پیامک می‌شود وارد کنید (در نصب آزمایشی در storage/logs/sms.log) و امضا کنید.',
                    ])
                    ->url(fn (): string => route('contracts.index'))
                    ->perform(fn (TutorialRun $run): Contract => app(Contracts::class)->signElectronically(self::mainContract($run), self::employee($run)->user, null))
                    ->doneWhen(fn (TutorialRun $run): bool => self::mainContract($run)?->signed_via === SignedVia::Electronic),
                Mission::make('amendment', 'الحاقیه با امضای کاغذی')
                    ->steps([
                        "با «بازگشت به حساب خودم» برگردید؛ {$tenant} «شعبه مرکزی»، «قراردادها → قراردادها».",
                        'روی قرارداد امضاشده‌ی لیلا «الحاقیه» را بزنید و «الحاقیه افزایش حقوق» را از ماه بعد بسازید؛ شماره‌اش پس از قرارداد اصلی می‌آید (…/۱).',
                        '«صدور» را بزنید، چاپ و امضا شده فرض کنید و «ثبت امضای کاغذی» را با تصویر نسخه‌ی امضاشده بزنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, ContractResource::class))
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::branch($run)->execute(function () use ($run, $owner): void {
                        $contracts = app(Contracts::class);
                        $amendment = $contracts->draftAmendment(self::mainContract($run), self::amendmentType($run), ['starts_on' => today()->addMonth()->toDateString()], $owner);
                        $contracts->issue($amendment, $owner);
                        $contracts->recordPaperSignature($amendment->refresh(), $owner, self::samplePdf('signed-amendment'));
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::contractOf($run, ContractKind::Amendment)?->signed_via === SignedVia::Paper),
                Mission::make('renewal', 'تمدید قرارداد')
                    ->steps([
                        'روی همان قرارداد اصلی «تمدید» را بزنید: پیش‌نویس از روز بعد از پایان آن، به همان مدت و با همان مبلغ و پاسخ‌ها ساخته می‌شود.',
                        '«صدور» را بزنید. هر قرارداد فقط یک تمدید دارد (مگر لغو شود).',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, ContractResource::class))
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::branch($run)->execute(function () use ($run, $owner): void {
                        $contracts = app(Contracts::class);
                        $renewal = $contracts->draftRenewal(self::mainContract($run), self::contractType($run), Contracts::renewalState(self::mainContract($run)), $owner);
                        $contracts->issue($renewal, $owner);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::contractOf($run, ContractKind::Renewal)?->status === ContractStatus::Issued),
                Mission::make('ending-soon', 'قرارداد رو به پایان')
                    ->steps([
                        'با «قرارداد جدید» برای لیلا یک «قرارداد کار معین» دیگر بسازید که بیست روز دیگر تمام شود، و «صدور» را بزنید.',
                        'فیلتر «پایان» را «پایان نزدیک» کنید: تاریخ پایانش رنگی است چون تمدید ندارد. هر روز ساعت ۸ به مسئولان قرارداد و صادرکننده یادآوری می‌شود.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, ContractResource::class))
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::branch($run)->execute(function () use ($run, $owner): void {
                        $contracts = app(Contracts::class);
                        $contract = $contracts->saveDraft(null, self::contractType($run), self::employee($run), [
                            'starts_on' => today()->subMonths(11)->toDateString(),
                            'ends_on' => today()->addDays(20)->toDateString(),
                            'amount' => 1200000000,
                            'custom' => [self::SALARY_FIELD => 100000000, self::HOURS_FIELD => 20],
                        ], $owner);
                        $contracts->issue($contract, $owner);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::endingSoon($run) !== null),
                Mission::make('cancel', 'لغو قرارداد')
                    ->steps([
                        'روی همین قرارداد رو به پایان «لغو قرارداد» را بزنید و «علت لغو» را بنویسید («جایگزین با قرارداد تمام‌وقت»).',
                        'قرارداد لغوشده با علت و تاریخ لغو در فهرست می‌ماند.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, ContractResource::class))
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::branch($run)->execute(fn () => app(Contracts::class)->cancel(self::endingSoon($run), $owner, 'جایگزین با قرارداد تمام‌وقت.')))
                    ->doneWhen(fn (TutorialRun $run): bool => self::endingSoon($run, status: ContractStatus::Cancelled) !== null),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, options: list<string>, help: null, section: null}
     */
    private static function field(string $key, string $label, string $type): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => false, 'options' => [], 'help' => null, 'section' => null];
    }

    /**
     * A sample file on the local disk, as an upload path.
     */
    private static function samplePdf(string $name): string
    {
        $path = 'tutorial/'.Str::random(16).'/'.$name.'.pdf';
        Storage::disk('local')->put($path, self::SAMPLE_PDF);

        return $path;
    }

    private static function branch(TutorialRun $run): ?Tenant
    {
        return $run->made(Tenant::class)->oldest('id')->first();
    }

    /**
     * @param  class-string  $resource
     */
    private static function branchUrl(TutorialRun $run, string $resource): ?string
    {
        return ($branch = self::branch($run)) ? $resource::getUrl('index', panel: 'admin', tenant: $branch) : null;
    }

    private static function manager(TutorialRun $run): ?User
    {
        return self::employeeIn($run, self::branch($run), DefaultRoles::TENANT_MANAGER, withPanel: true);
    }

    private static function idCard(TutorialRun $run): ?DocumentType
    {
        return $run->made(DocumentType::class)->where('is_active', true)->where('has_expiry', false)->oldest('id')->first();
    }

    private static function healthCertificate(TutorialRun $run): ?DocumentType
    {
        return $run->made(DocumentType::class)->where('is_active', true)->where('has_expiry', true)->oldest('id')->first();
    }

    /**
     * The contract type with its own terms form: the amount required.
     */
    private static function contractType(TutorialRun $run): ?ContractType
    {
        return $run->made(ContractType::class)->where('is_active', true)->where('is_amendment', false)->get()
            ->first(fn (ContractType $type): bool => ContractForm::for($type)->requires('amount') && ContractForm::for($type)->fields !== []);
    }

    private static function amendmentType(TutorialRun $run): ?ContractType
    {
        return $run->made(ContractType::class)->where('is_active', true)->where('is_amendment', true)
            ->whereIn('signature_method', [SignatureMethod::Paper, SignatureMethod::Either])->oldest('id')->first();
    }

    private static function workflow(TutorialRun $run): ?Workflow
    {
        $idCard = self::idCard($run)?->getKey();
        $contractType = self::contractType($run)?->getKey();

        return $run->made(Workflow::class)->where('subject_type', (new JobApplication)->getMorphClass())->get()
            ->first(function (Workflow $workflow) use ($idCard, $contractType): bool {
                $steps = collect($workflow->stepList());
                $names = fn (WorkflowStep $step, string $extension, string $setting): array => array_map('intval', (array) ($step->extension($extension)[$setting] ?? []));

                return $steps->count() >= 3
                    && $steps->contains(fn (WorkflowStep $step): bool => $step->allows(StepAction::Edit) && in_array($idCard, $names($step, 'documents', 'verify'), true))
                    && $steps->contains(fn (WorkflowStep $step): bool => $step->assigneeType === AssigneeType::Role && $step->allows(StepAction::Return))
                    && $steps->contains(fn (WorkflowStep $step): bool => in_array($contractType, $names($step, 'contracts', 'prepare'), true));
            });
    }

    /**
     * The position whose own form asks for the ID card.
     */
    private static function position(TutorialRun $run): ?JobPosition
    {
        $workflow = self::workflow($run);
        $idCard = self::idCard($run);

        return $workflow && $idCard ? $run->made(JobPosition::class)->where('is_active', true)->where('workflow_id', $workflow->getKey())->get()
            ->first(fn (JobPosition $position): bool => FormCustomization::withoutTenantScope()->where('owner_type', $position->getMorphClass())->where('owner_id', $position->getKey())->get()
                ->contains(fn (FormCustomization $form): bool => ($form->field_modes['document_'.$idCard->getKey()] ?? null) === 'required')) : null;
    }

    private static function application(TutorialRun $run): ?JobApplication
    {
        $position = self::position($run);

        return $position ? $run->made(JobApplication::class)->where('job_position_id', $position->getKey())->oldest('id')->first() : null;
    }

    private static function idCardUpload(TutorialRun $run): ?Document
    {
        $application = self::application($run);
        $idCard = self::idCard($run);

        return $application && $idCard ? $run->made(Document::class)->where('document_type_id', $idCard->getKey())
            ->where('documentable_type', $application->getMorphClass())->where('documentable_id', $application->getKey())->first() : null;
    }

    private static function case(TutorialRun $run): ?WorkflowCase
    {
        $application = self::application($run);

        return $application ? WorkflowCase::withoutTenantScope()->where('subject_type', $application->getMorphClass())->where('subject_id', $application->getKey())->latest('id')->first() : null;
    }

    private static function hasAction(TutorialRun $run, CaseActionType $type): bool
    {
        return (bool) self::case($run)?->actions()->where('type', $type)->exists();
    }

    /**
     * Leila as an employee of the branch, hired from her application.
     */
    private static function employee(TutorialRun $run): ?Employee
    {
        $application = self::application($run);
        $branch = self::branch($run);

        return $application && $branch ? $run->made(Employee::class)->where('tenant_id', $branch->getKey())->where('job_application_id', $application->getKey())->with('user')->first() : null;
    }

    /**
     * The contract prepared in the workflow (the applicant's, then the employee's).
     */
    private static function mainContract(TutorialRun $run): ?Contract
    {
        $type = self::contractType($run);

        return $type ? $run->made(Contract::class)->where('contract_type_id', $type->getKey())->where('kind', ContractKind::Original)->oldest('id')->first() : null;
    }

    private static function contractOf(TutorialRun $run, ContractKind $kind): ?Contract
    {
        $main = self::mainContract($run);

        return $main ? $run->made(Contract::class)->where('parent_id', $main->getKey())->where('kind', $kind)->oldest('id')->first() : null;
    }

    private static function healthUpload(TutorialRun $run): ?Document
    {
        $employee = self::employee($run);
        $type = self::healthCertificate($run);

        return $employee && $type ? $run->made(Document::class)->where('document_type_id', $type->getKey())
            ->where('documentable_type', $employee->getMorphClass())->where('documentable_id', $employee->getKey())->first() : null;
    }

    /**
     * A second contract of the employee, ending within 30 days.
     */
    private static function endingSoon(TutorialRun $run, ContractStatus $status = ContractStatus::Issued): ?Contract
    {
        $main = self::mainContract($run);
        $employee = self::employee($run);

        return $main && $employee ? $run->made(Contract::class)->whereKeyNot($main->getKey())->where('kind', ContractKind::Original)
            ->where('contractable_type', $employee->getMorphClass())->where('contractable_id', $employee->getKey())
            ->where('status', $status)->whereBetween('ends_on', [today(), today()->addDays(30)])->oldest('id')->first() : null;
    }
}
