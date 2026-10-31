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
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Models\Employee;
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
        return 'یک '.config('sarasystem.tenant_label').'، یک مدرس و یک کارشناس ثبت‌نام؛ پیش‌ثبت‌نام و پذیرش یک '.config('sarasystem.learner_label').' با فرایند، و تشکیل اولین کلاس تا حضور و غیاب جلسه‌ی اول.';
    }

    public function chapters(): array
    {
        $tenant = config('sarasystem.tenant_label');
        $tenants = config('sarasystem.tenant_plural_label');
        $learner = config('sarasystem.learner_label');

        return [
            'ساختار آموزشگاه' => [
                Mission::make('tenant', "ساخت {$tenant}")
                    ->steps([
                        "از منوی «{$tenants}» دکمه‌ی «ایجاد» را بزنید.",
                        'نام را «شعبه ونک» و نشانی را «vanak» بگذارید و ذخیره کنید.',
                    ])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->doneWhen(fn (TutorialRun $run): bool => self::branch($run) !== null),
                Mission::make('instructor', 'ثبت مدرس')
                    ->steps([
                        "از منوی بالای پنل، {$tenant} را «شعبه ونک» کنید.",
                        'در «کارکنان → کارمندان» دکمه‌ی «ثبت سریع کارمند» را بزنید.',
                        'کد ملی، نام «سارا احمدی»، موبایل و عنوان شغلی «مدرس زبان» را بنویسید و نقش «مدرس» را بزنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, EmployeeResource::class))
                    ->doneWhen(fn (TutorialRun $run): bool => self::employeeWithRole($run, DefaultRoles::INSTRUCTOR) !== null),
                Mission::make('registrar', 'ثبت کارشناس ثبت‌نام')
                    ->steps([
                        'باز در «کارکنان → کارمندان» با «ثبت سریع کارمند»، «رضا کریمی» را ثبت کنید.',
                        'نقش «کارشناس ثبت‌نام» را بزنید و «دسترسی به پنل مدیریت» را روشن کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, EmployeeResource::class))
                    ->doneWhen(fn (TutorialRun $run): bool => self::registrar($run, withPanel: true) !== null),
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
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Term::class)->exists()),
                Mission::make('classroom', 'ساخت کلاس')
                    ->steps([
                        "{$tenant} را «شعبه ونک» کنید و در «کلاس‌ها → کلاس‌ها» دکمه‌ی «ایجاد» را بزنید.",
                        'نام «English A1»، دوره‌ی «ترم پاییز» و نوع «حضوری» را بگذارید و ذخیره کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, ClassroomResource::class))
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
