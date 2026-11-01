<?php

namespace Modules\Tutorial\Scenarios;

use App\Authorization\DefaultRoles;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Terms\TermResource;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Term;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Filament\Resources\JobPositions\JobPositionResource;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Student\Filament\Resources\Programs\ProgramResource;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Tutorial\Models\TutorialRun;

/**
 * A language institute with one branch, the fewest options (ADR 0040):
 * staff, a learner admitted through a workflow, and a class whose teacher
 * takes the first attendance.
 */
class LanguageInstitute extends Scenario
{
    public function key(): string
    {
        return 'language-institute';
    }

    public function title(): string
    {
        return 'آموزشگاه زبان';
    }

    public function description(): string
    {
        return 'یک '.config('sarasystem.tenant_label').'، یک کارشناس ثبت‌نام با ثبت سریع و استخدام یک مدرس از فرم «همکاری با ما» با فرایند؛ پیش‌ثبت‌نام و پذیرش یک '.config('sarasystem.learner_label').' با فرایند، و تشکیل اولین کلاس تا حضور و غیاب جلسه‌ی اول.';
    }

    public function chapters(): array
    {
        $tenant = config('sarasystem.tenant_label');
        $tenants = config('sarasystem.tenant_plural_label');
        $learner = config('sarasystem.learner_label');
        $primary = Tenant::primary()?->name ?? 'ستاد';

        return [
            'ساختار آموزشگاه' => [
                Mission::make('tenant', "ساخت {$tenant}")
                    ->steps([
                        "از منوی «{$tenants}» دکمه‌ی «ایجاد» را بزنید.",
                        'نام را «شعبه ونک» و نشانی را «vanak» بگذارید و ذخیره کنید.',
                    ])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => 'شعبه ونک', 'slug' => 'vanak'])
                    ->doneWhen(fn (TutorialRun $run): bool => self::branch($run) !== null),
                Mission::make('registrar', 'ثبت کارشناس ثبت‌نام')
                    ->steps([
                        "از منوی بالای پنل، {$tenant} را «شعبه ونک» کنید.",
                        'در «کارکنان → کارمندان» دکمه‌ی «ثبت سریع کارمند» را بزنید.',
                        'کد ملی، نام «رضا کریمی»، موبایل و عنوان شغلی «کارشناس ثبت‌نام» را بنویسید.',
                        'نقش «کارشناس ثبت‌نام» را بزنید و «دسترسی به پنل مدیریت» را روشن کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, EmployeeResource::class))
                    ->fill(fn (): array => [
                        'national_code' => '1002003008',
                        'name' => 'رضا کریمی',
                        'mobile' => '09120000002',
                        'job_title' => 'کارشناس ثبت‌نام',
                        'role_ids' => self::roleIds(DefaultRoles::REGISTRAR),
                        'panel_access' => true,
                    ])
                    ->doneWhen(fn (TutorialRun $run): bool => self::registrar($run, withPanel: true) !== null),
            ],
            'استخدام مدرس' => [
                Mission::make('hiring-workflow', 'ساخت فرایند استخدام')
                    ->steps([
                        "اگر {$tenant} را عوض کرده‌اید، به «{$primary}» برگردید.",
                        'در «کارتابل → فرایندها» دکمه‌ی «ایجاد» را بزنید.',
                        'نام را «استخدام مدرس» و «برای» را «درخواست همکاری» بگذارید.',
                        "یک مرحله‌ی «مصاحبه» بسازید: {$tenant} «{$primary}»، مسئول «شخص»، شخص: خودتان؛ و ذخیره کنید.",
                    ])
                    ->url(fn (): string => WorkflowResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => 'استخدام مدرس', 'subject_type' => (new JobApplication)->getMorphClass()])
                    ->doneWhen(fn (TutorialRun $run): bool => self::hiringWorkflow($run) !== null),
                Mission::make('job-position', 'ساخت موقعیت شغلی')
                    ->steps([
                        'در «همکاری با ما → موقعیت‌های شغلی» دکمه‌ی «ایجاد» را بزنید.',
                        'عنوان را «مدرس زبان انگلیسی» بگذارید و فرایند «استخدام مدرس» را برایش انتخاب کنید.',
                        '«نمایش در فرم همکاری با ما» را روشن بگذارید و ذخیره کنید.',
                    ])
                    ->url(fn (): string => JobPositionResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => [
                        'title' => 'مدرس زبان انگلیسی',
                        'workflow_id' => self::hiringWorkflow($run)?->getKey(),
                        'is_active' => true,
                    ])
                    ->doneWhen(fn (TutorialRun $run): bool => self::jobPosition($run) !== null),
                Mission::make('job-application', 'درخواست همکاری از سایت')
                    ->actor('متقاضی (فرم عمومی سایت)')
                    ->steps([
                        'فرم «همکاری با ما» سایت را باز کنید و شغل «مدرس زبان انگلیسی» را انتخاب کنید.',
                        'نام «سارا احمدی»، جنسیت «زن» و کد ملی ۱۲۳۴۵۶۷۸۹۱ را بنویسید.',
                        'موبایل خودتان را بنویسید تا کد تأیید پیامک شود.',
                        'بقیه‌ی فیلدهای لازم (سوابق تحصیلی، نشانی، رزومه‌ی PDF و …) را پر کنید و فرم را بفرستید.',
                        'این موقعیت شغلی فقط برای شما در حالت آموزش دیده می‌شود، نه برای بازدیدکننده‌های سایت.',
                    ])
                    ->url(fn (): string => route('careers.create'), 'باز کردن فرم')
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(JobApplication::class)->whereIn('job_position_id', $run->made(JobPosition::class)->select('id'))->exists()),
                Mission::make('hiring', 'استخدام مدرس')
                    ->steps([
                        "در «کارتابل من» ({$tenant} «{$primary}») پرونده‌ی «سارا احمدی» را باز کنید و «تأیید» را بزنید.",
                        "{$tenant} محل خدمت را «شعبه ونک» و نقش «مدرس» را بگذارید.",
                        '«دسترسی به پنل مدیریت» را خاموش کنید (مدرس از «کلاس‌های من» کار می‌کند) و تأیید کنید.',
                    ])
                    ->url(fn (): string => WorkflowCaseResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => [
                        'tenant_id' => self::branch($run)?->getKey(),
                        'role_ids' => self::roleIds(DefaultRoles::INSTRUCTOR),
                        'panel_access' => false,
                    ])
                    ->doneWhen(fn (TutorialRun $run): bool => self::employeeWithRole($run, DefaultRoles::INSTRUCTOR) !== null
                        && $run->made(JobApplication::class)->where('status', ApplicationStatus::Hired)->exists()),
            ],
            'پیش‌ثبت‌نام و پذیرش' => [
                Mission::make('workflow', 'ساخت فرایند پذیرش')
                    ->steps([
                        'در «کارتابل → فرایندها» دکمه‌ی «ایجاد» را بزنید.',
                        'نام را «پذیرش زبان‌آموز» و «برای» را «پیش‌ثبت‌نام» بگذارید.',
                        "یک مرحله بسازید: {$tenant} «شعبه ونک»، مسئول «نقش»، نقش «کارشناس ثبت‌نام»، و ذخیره کنید.",
                    ])
                    ->url(fn (): string => WorkflowResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->doneWhen(fn (TutorialRun $run): bool => self::admissionWorkflow($run) !== null),
                Mission::make('program', 'ساخت برنامه‌ی آموزشی')
                    ->steps([
                        'در «پیش‌ثبت‌نام → برنامه‌های آموزشی» دکمه‌ی «ایجاد» را بزنید.',
                        'عنوان را «زبان انگلیسی - مقدماتی» بگذارید و فرایند «پذیرش زبان‌آموز» را برایش انتخاب کنید.',
                        'آن را در «شعبه ونک» ارائه دهید و فعال ذخیره کنید.',
                    ])
                    ->url(fn (): string => ProgramResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Program::class)->where('is_active', true)->whereIn('workflow_id', $run->made(Workflow::class)->select('id'))->exists()),
                Mission::make('preregistration', 'پیش‌ثبت‌نام از سایت')
                    ->actor('متقاضی (فرم عمومی سایت)')
                    ->steps([
                        'فرم «پیش‌ثبت‌نام» سایت را باز کنید و برنامه‌ی «زبان انگلیسی - مقدماتی» را انتخاب کنید.',
                        'نام «علی رضایی» و یک کد ملی درست بنویسید (مثلاً ۰۰۱۳۵۴۲۴۱۹).',
                        'موبایل خودتان را بنویسید تا کد تأیید پیامک شود، و فرم را بفرستید.',
                        'این برنامه و «شعبه ونک» فقط برای شما در حالت آموزش دیده می‌شوند، نه برای بازدیدکننده‌های سایت.',
                    ])
                    ->url(fn (): string => route('preregister.create'), 'باز کردن فرم')
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Preregistration::class)->exists()),
                Mission::make('admission', "پذیرش {$learner}")
                    ->actor('کارشناس ثبت‌نام', fn (TutorialRun $run): ?User => self::registrar($run, withPanel: true))
                    ->steps([
                        'با دکمه‌ی «ورود با حساب …» با حساب کارشناس ثبت‌نام وارد شوید.',
                        'در «کارتابل من» پرونده‌ی پیش‌ثبت‌نام را باز کنید و «تأیید» را بزنید.',
                        "{$tenant} را «شعبه ونک» و نقش «{$learner}» را بگذارید و تأیید کنید.",
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, WorkflowCaseResource::class))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Student::class)->exists()),
            ],
            'تشکیل کلاس' => [
                Mission::make('term', 'ساخت دوره‌ی آموزشی')
                    ->steps([
                        'اگر با حساب دیگری وارد شده‌اید، با «بازگشت به حساب خودم» برگردید.',
                        'در «کلاس‌ها → دوره‌های آموزشی» دوره‌ی «ترم پاییز» را با تاریخ شروع امروز و پایان سه ماه بعد بسازید.',
                    ])
                    ->url(fn (): string => TermResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => [
                        'title' => 'ترم پاییز',
                        'starts_on' => now()->toDateString(),
                        'ends_on' => now()->addMonths(3)->toDateString(),
                    ])
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Term::class)->exists()),
                Mission::make('classroom', 'ساخت کلاس')
                    ->steps([
                        "{$tenant} را «شعبه ونک» کنید و در «کلاس‌ها → کلاس‌ها» دکمه‌ی «ایجاد» را بزنید.",
                        'نام «English A1»، دوره‌ی «ترم پاییز» و نوع «حضوری» را بگذارید و ذخیره کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, ClassroomResource::class))
                    ->fill(fn (TutorialRun $run): array => [
                        'title' => 'English A1',
                        'term_id' => $run->made(Term::class)->oldest('id')->value('id'),
                        'mode' => 'in_person',
                    ])
                    ->doneWhen(fn (TutorialRun $run): bool => self::classroom($run) !== null),
                Mission::make('members', 'افزودن اعضای کلاس')
                    ->steps([
                        'کلاس «English A1» را باز کنید و به زبانه‌ی «اعضا» بروید.',
                        "با «افزودن عضو»، «علی رضایی» را با نقش «{$learner}» و «سارا احمدی» را با نقش «مدرس» اضافه کنید.",
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classroomUrl($run))
                    ->doneWhen(function (TutorialRun $run): bool {
                        $attendeeRoles = ClassRole::withoutTenantScope()->where('is_attendee', true)->select('id');
                        $members = $run->made(ClassMember::class)->whereNull('left_on');

                        return (clone $members)->whereIn('class_role_id', $attendeeRoles)->exists()
                            && (clone $members)->whereNotIn('class_role_id', $attendeeRoles)->exists();
                    }),
                Mission::make('schedule', 'برنامه‌ی کلاس')
                    ->steps([
                        'در زبانه‌ی «برنامه» دکمه‌ی «افزودن برنامه» را بزنید.',
                        'تکرار «یک بار»، تاریخ امروز، از ده دقیقه‌ی بعد به مدت یک ساعت، با مدرس «سارا احمدی».',
                        'ذخیره کنید و جلسه را در زبانه‌ی «جلسه‌ها» ببینید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classroomUrl($run))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(ClassSession::class)->exists()),
                Mission::make('attendance', 'حضور و غیاب جلسه‌ی اول')
                    ->actor('مدرس', fn (TutorialRun $run): ?User => self::employeeWithRole($run, DefaultRoles::INSTRUCTOR))
                    ->steps([
                        'با دکمه‌ی «ورود با حساب …» با حساب مدرس وارد شوید.',
                        'در «کلاس‌های من» جلسه‌ی امروز را باز کنید.',
                        "«{$learner}» را «حاضر» بزنید و حضور و غیاب را ثبت کنید.",
                    ])
                    ->url(fn (): string => route('my-classes.index'))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Attendance::class)->exists()),
            ],
        ];
    }

    /**
     * The branch made in the first mission.
     */
    public static function branch(TutorialRun $run): ?Tenant
    {
        return $run->made(Tenant::class)->oldest('id')->first();
    }

    /**
     * @param  class-string  $resource
     */
    private static function branchUrl(TutorialRun $run, string $resource): ?string
    {
        $branch = self::branch($run);

        return $branch ? $resource::getUrl('index', panel: 'admin', tenant: $branch) : null;
    }

    /**
     * The user of an employee made in the run who holds the role in the branch.
     */
    private static function employeeWithRole(TutorialRun $run, string $roleName, bool $withPanel = false): ?User
    {
        $branch = self::branch($run);
        $role = Role::query()->whereNull('tenant_id')->where('name', $roleName)->first();

        if ($branch === null || $role === null) {
            return null;
        }

        return $run->made(Employee::class)->where('tenant_id', $branch->getKey())->with('user')->get()
            ->map(fn (Employee $employee): ?User => $employee->user)
            ->first(fn (?User $user): bool => $user !== null
                && in_array($role->getKey(), $user->roleIdsIn($branch))
                && (! $withPanel || $user->staffTenants()->whereKey($branch->getKey())->exists()));
    }

    /**
     * The ids (as the form's checkboxes send them) of the default role.
     *
     * @return list<string>
     */
    private static function roleIds(string $roleName): array
    {
        return Role::query()->whereNull('tenant_id')->where('name', $roleName)->pluck('id')->map(fn (int $id): string => (string) $id)->all();
    }

    private static function registrar(TutorialRun $run, bool $withPanel): ?User
    {
        return self::employeeWithRole($run, DefaultRoles::REGISTRAR, $withPanel);
    }

    private static function admissionWorkflow(TutorialRun $run): ?Workflow
    {
        return $run->made(Workflow::class)
            ->where('subject_type', (new Preregistration)->getMorphClass())
            ->get()
            ->first(fn (Workflow $workflow): bool => $workflow->stepList() !== []);
    }

    private static function hiringWorkflow(TutorialRun $run): ?Workflow
    {
        return $run->made(Workflow::class)
            ->where('subject_type', (new JobApplication)->getMorphClass())
            ->get()
            ->first(fn (Workflow $workflow): bool => $workflow->stepList() !== []);
    }

    private static function jobPosition(TutorialRun $run): ?JobPosition
    {
        $workflow = self::hiringWorkflow($run);

        return $workflow ? $run->made(JobPosition::class)->where('is_active', true)->where('workflow_id', $workflow->getKey())->first() : null;
    }

    private static function classroom(TutorialRun $run): ?Classroom
    {
        $branch = self::branch($run);

        return $branch ? $run->made(Classroom::class)->where('tenant_id', $branch->getKey())->oldest('id')->first() : null;
    }

    private static function classroomUrl(TutorialRun $run): ?string
    {
        $classroom = self::classroom($run);

        return $classroom ? ClassroomResource::getUrl('view', ['record' => $classroom], panel: 'admin', tenant: self::branch($run)) : null;
    }
}
