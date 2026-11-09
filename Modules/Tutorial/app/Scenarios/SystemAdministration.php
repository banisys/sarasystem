<?php

namespace Modules\Tutorial\Scenarios;

use App\Authorization\DefaultRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\AssigneeType;
use App\Workflow\CaseActionType;
use App\Workflow\CaseStatus;
use App\Workflow\StepAction;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Str;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Filament\Resources\JobPositions\JobPositionResource;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Concerns\AppliesForJobs;
use Spatie\Permission\Models\Permission;

/**
 * «مدیریت سامانه و دسترسی‌ها» (ADR 0040, docs/tutorial-scenarios.md):
 * the organization's units, people with different roles in each, what a
 * manager may and may not do, and how a case moves through a workflow:
 * claim, note, return, approve again and reject. Core only; the workflow
 * chapter needs a subject, so it is left out while Employee is off.
 */
class SystemAdministration extends Scenario
{
    use AppliesForJobs;

    /** The custom role's key and the permissions it gets. */
    private const string LIBRARIAN = 'librarian';

    private const array LIBRARIAN_PERMISSIONS = ['ViewAny:User', 'View:User'];

    public function key(): string
    {
        return 'system-administration';
    }

    public function title(): string
    {
        return 'مدیریت سامانه و دسترسی‌ها';
    }

    public function description(): string
    {
        $tenant = config('sarasystem.tenant_label');

        return "ساختار سازمان با دو {$tenant}، کاربرانی با نقش‌های متفاوت در هر {$tenant}، ساخت نقش تازه، آنچه یک مدیر {$tenant} می‌تواند و نمی‌تواند، و گردش یک پرونده در کارتابل: برداشتن، یادداشت، برگرداندن، تأیید و رد.";
    }

    public function chapters(): array
    {
        $tenant = config('sarasystem.tenant_label');
        $tenants = config('sarasystem.tenant_plural_label');
        $manager = 'مدیر '.$tenant;

        return array_filter([
            'ساختار سازمان' => [
                Mission::make('tenants', "ساخت دو {$tenant}")
                    ->steps([
                        'ابتدا «نقشهٔ سامانه» را باز کنید؛ بخش‌ها، ارتباط‌های کاری و امکانات فعال را ببینید. با باز کردن کارت هر بخش، به صفحه‌های آن می‌رسید؛ «بازسازی نقشه» آخرین وضعیت را نشان می‌دهد.',
                        "از منوی «{$tenants}» دکمه‌ی «ایجاد» را بزنید و «شعبه شرق» با نشانی «east» بسازید. در «رنگ اصلی» یک رنگ، مثلاً صورتی، انتخاب کنید؛ سایت این {$tenant} به همین رنگ دیده می‌شود.",
                        'دوباره «ایجاد» را بزنید و «شعبه غرب» با نشانی «west» و رنگ دیگری، مثلاً آبی، بسازید.',
                        "هر {$tenant} صفحه‌ی اصلی خودش را در سایت دارد (/east و /west)؛ {$tenant} اصلی (ستاره در فهرست) صفحه‌ی اصلی سایت (/) است.",
                    ])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => $run->made(Tenant::class)->exists()
                        ? ['name' => 'شعبه غرب', 'slug' => 'west', 'primary_color' => '#2563eb']
                        : ['name' => 'شعبه شرق', 'slug' => 'east', 'primary_color' => '#ec4899'])
                    ->perform(function (): void {
                        Tenant::query()->create(['name' => 'شعبه شرق', 'slug' => 'east', 'is_active' => true, 'primary_color' => '#ec4899']);
                        Tenant::query()->create(['name' => 'شعبه غرب', 'slug' => 'west', 'is_active' => true, 'primary_color' => '#2563eb']);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::west($run) !== null),
            ],
            'کاربران و نقش‌ها' => [
                Mission::make('manager', "ساخت {$manager}")
                    ->steps([
                        "از منوی بالای پنل، {$tenant} را «شعبه شرق» کنید.",
                        'در «کاربران» دکمه‌ی «ایجاد کاربر» را بزنید.',
                        'نام «مریم صادقی»، کد ملی ۰۰۲۰۰۴۰۰۶۷ و یک رمز عبور بنویسید.',
                        "نقش‌های «{$manager}» و «".self::roleLabel(Utils::getPanelUserRoleName()).'» (ورود به پنل) را بزنید و ذخیره کنید.',
                        "نقش‌ها مال هر {$tenant} جداست: مریم فقط در «شعبه شرق» مدیر است.",
                        'در فهرست‌ها، ستون‌های «تاریخ ایجاد» و «آخرین ویرایش» زمان شمسی ثبت و تغییر رکورد را نشان می‌دهند. روی عنوان هر ستون کلیک کنید تا فهرست بر اساس آن مرتب شود؛ کلیک دوباره جهت مرتب‌سازی را عوض می‌کند.',
                        'اختیاری: در فهرست کاربران روی متن یک ردیف کلیک کنید؛ کادر نقطه‌چین جای شما را نشان می‌دهد. با کلیدهای ↑ و ↓ بین ردیف‌های همین صفحه حرکت کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::usersUrl(self::east($run)))
                    ->fill(fn (): array => [
                        'name' => 'مریم صادقی',
                        'national_code' => '0020040067',
                        'mobile' => '09120000011',
                        'password' => 'tutorial-1405',
                        'roles' => self::roleIds(DefaultRoles::TENANT_MANAGER, Utils::getPanelUserRoleName()),
                    ])
                    ->perform(fn (TutorialRun $run): User => self::makeUser('مریم صادقی', '0020040067', '09120000011', self::east($run), DefaultRoles::TENANT_MANAGER, Utils::getPanelUserRoleName()))
                    ->doneWhen(fn (TutorialRun $run): bool => self::manager($run) !== null),
                Mission::make('custom-role', 'ساخت نقش تازه')
                    ->steps([
                        'در «نقش‌ها» دکمه‌ی «ایجاد» را بزنید.',
                        'نام (کلید انگلیسی) را «librarian» و عنوان را «مسئول کتابخانه» بگذارید.',
                        'از دسترسی‌ها یکی دو مورد را بزنید (مثلاً دیدن فهرست و جزئیات کاربران) و ذخیره کنید.',
                        'نقش‌ها بین همه‌ی '.$tenants.' مشترک‌اند؛ هر کس آن‌ها را در هر '.$tenant.' جداگانه می‌گیرد.',
                        ...(self::enabled('Campus') ? ['اختیاری: در «نقش‌ها» دکمه‌ی «هرم قدرت نقش‌ها» را بزنید: هر طبقه یک سطح است و ستون بلندتر مجوز بیشتری دارد؛ با کلیک روی «مسئول کتابخانه» بالادست و زیردست آن را ببینید.'] : []),
                    ])
                    ->url(fn (): string => RoleResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => self::LIBRARIAN, 'label' => 'مسئول کتابخانه'])
                    ->perform(function (): void {
                        Role::query()->create(['name' => self::LIBRARIAN, 'label' => 'مسئول کتابخانه', 'guard_name' => 'web', 'tenant_id' => null])
                            ->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), self::LIBRARIAN_PERMISSIONS));
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::customRole($run) !== null),
                Mission::make('two-tenants', "یک نفر در دو {$tenant}")
                    ->steps([
                        "در «کاربران» ({$tenant} «شعبه شرق») کاربر «حمید نوری» با کد ملی ۰۰۳۰۰۵۰۰۷۳ و نقش «کارمند» بسازید.",
                        "{$tenant} را «شعبه غرب» کنید؛ در «کاربران» با «افزودن کاربر موجود» حمید را پیدا کنید و نقش «مسئول کتابخانه» را به او بدهید.",
                        'در «نقشه‌ی دسترسی»، «ماتریس کاربر × '.$tenant.'» را ببینید: حمید در هر '.$tenant.' نقش دیگری دارد.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::usersUrl(self::east($run)))
                    ->fill(fn (TutorialRun $run): array => self::hamid($run) === null
                        ? ['name' => 'حمید نوری', 'national_code' => '0030050073', 'mobile' => '09120000012', 'password' => 'tutorial-1405', 'roles' => self::roleIds(DefaultRoles::STAFF)]
                        : ['roles' => self::roleIds(self::LIBRARIAN)])
                    ->perform(function (TutorialRun $run): void {
                        $hamid = self::makeUser('حمید نوری', '0030050073', '09120000012', self::east($run), DefaultRoles::STAFF);
                        self::giveRoles($hamid, self::west($run), self::LIBRARIAN);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::hamid($run, inBoth: true) !== null),
            ],
            "از نگاه {$manager}" => [
                Mission::make('manager-adds-staff', 'کار با حساب مدیر')
                    ->actor($manager, fn (TutorialRun $run): ?User => self::manager($run))
                    ->steps([
                        'با دکمه‌ی «ورود با حساب …» با حساب مریم صادقی وارد شوید.',
                        "ببینید که فقط «شعبه شرق» را دارد و در «کاربران» نقش‌های «مدیر ستاد» و «{$manager}» را نمی‌تواند بدهد: هر کس فقط نقشی را می‌دهد که همه‌ی دسترسی‌هایش را خودش دارد.",
                        'کاربر «سمیرا عباسی» با کد ملی ۰۰۴۰۰۶۰۰۸۱ و نقش «کارمند» بسازید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::usersUrl(self::east($run)))
                    ->fill(fn (): array => ['name' => 'سمیرا عباسی', 'national_code' => '0040060081', 'mobile' => '09120000013', 'password' => 'tutorial-1405', 'roles' => self::roleIds(DefaultRoles::STAFF)])
                    ->perform(fn (TutorialRun $run): User => self::makeUser('سمیرا عباسی', '0040060081', '09120000013', self::east($run), DefaultRoles::STAFF))
                    ->doneWhen(fn (TutorialRun $run): bool => self::staffAddedByManager($run)),
                Mission::make('deactivate', "غیرفعال کردن {$tenant}")
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید.',
                        "در «{$tenants}» «شعبه غرب» را ویرایش کنید و «فعال» را خاموش کنید.",
                        "{$tenant} غیرفعال از منوی {$tenants} و سایت کنار می‌رود ولی داده‌هایش می‌ماند؛ به جای حذف از همین استفاده کنید.",
                    ])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->perform(fn (TutorialRun $run): bool => self::west($run)->update(['is_active' => false]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::west($run)?->is_active === false),
            ],
            'فرایند و کارتابل' => self::enabled('Employee') ? self::workflowMissions($tenant, $manager) : null,
        ]);
    }

    /**
     * A job application through a two-step workflow: the manager claims,
     * notes and approves; you return it, she approves again, you reject.
     *
     * @return list<Mission>
     */
    private static function workflowMissions(string $tenant, string $manager): array
    {
        $myCartable = fn (TutorialRun $run): ?string => WorkflowCaseResource::getUrl('index', panel: 'admin', tenant: Tenant::primary());
        $managerCartable = fn (TutorialRun $run): ?string => ($east = self::east($run)) ? WorkflowCaseResource::getUrl('index', panel: 'admin', tenant: $east) : null;

        return [
            Mission::make('workflow', 'ساخت فرایند دو مرحله‌ای')
                ->steps([
                    'در «کارتابل → فرایندها» دکمه‌ی «ایجاد» را بزنید؛ نام «جذب کارشناس پشتیبانی» و «برای» را «درخواست همکاری» بگذارید.',
                    "مرحله‌ی «بررسی در شعبه»: {$tenant} «شعبه شرق»، مسئول «نقش»، نقش «{$manager}».",
                    'مرحله‌ی «تأیید نهایی»: '.$tenant.' «'.(Tenant::primary()?->name ?? 'ستاد').'»، مسئول «شخص»، شخص: خودتان؛ در «کارهای مجاز در این مرحله» «برگشت» و «رد» را بزنید.',
                    'ذخیره کنید و با «نمایش فرایند» نمودارش را ببینید.',
                    ...(self::enabled('Campus') ? ['اختیاری: در صفحه‌ی «نمایش فرایند» دکمه‌ی «نمای سه‌بعدی» را بزنید: هر مرحله یک پله است و هر '.config('sarasystem.tenant_label').' خط خودش را دارد؛ «پخش مسیر» یک پرونده را مرحله‌به‌مرحله از مسیر می‌گذراند.'] : []),
                ])
                ->url(fn (): string => WorkflowResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                ->fill(fn (): array => ['name' => 'جذب کارشناس پشتیبانی', 'subject_type' => (new JobApplication)->getMorphClass()])
                ->perform(fn (TutorialRun $run, User $owner): Workflow => Workflow::query()->create([
                    'subject_type' => (new JobApplication)->getMorphClass(),
                    'name' => 'جذب کارشناس پشتیبانی',
                    'is_active' => true,
                    'steps' => [
                        (new WorkflowStep('بررسی در شعبه', self::east($run)->getKey(), AssigneeType::Role, (int) self::roleIds(DefaultRoles::TENANT_MANAGER)[0], null, []))->toArray(),
                        (new WorkflowStep('تأیید نهایی', Tenant::primary()->getKey(), AssigneeType::User, null, $owner->getKey(), [StepAction::Return, StepAction::Reject]))->toArray(),
                    ],
                ]))
                ->doneWhen(fn (TutorialRun $run): bool => self::workflow($run) !== null),
            Mission::make('job-position', 'ساخت موقعیت شغلی')
                ->steps([
                    'در «همکاری با ما → موقعیت‌های شغلی» دکمه‌ی «ایجاد» را بزنید.',
                    'عنوان «کارشناس پشتیبانی» و فرایند «جذب کارشناس پشتیبانی» را بگذارید و ذخیره کنید.',
                ])
                ->url(fn (): string => JobPositionResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                ->fill(fn (TutorialRun $run): array => ['title' => 'کارشناس پشتیبانی', 'workflow_id' => self::workflow($run)?->getKey(), 'is_active' => true])
                ->perform(fn (TutorialRun $run): JobPosition => JobPosition::query()->create([
                    'tenant_id' => Tenant::primary()->getKey(),
                    'workflow_id' => self::workflow($run)->getKey(),
                    'title' => 'کارشناس پشتیبانی',
                    'is_active' => true,
                ]))
                ->doneWhen(fn (TutorialRun $run): bool => self::position($run) !== null),
            Mission::make('job-application', 'درخواست همکاری از سایت')
                ->actor('متقاضی (فرم عمومی سایت)')
                ->steps([
                    'فرم «همکاری با ما» سایت را باز کنید و شغل «کارشناس پشتیبانی» را انتخاب کنید.',
                    'نام «نیما شریفی» و کد ملی ۰۰۵۰۰۷۰۰۹۶ را بنویسید؛ موبایل خودتان را بنویسید تا کد تأیید پیامک شود.',
                    'بقیه‌ی فیلدهای لازم را پر کنید و بفرستید. پرونده‌اش به کارتابل '.$manager.' «شعبه شرق» می‌رود.',
                ])
                ->url(fn (): string => route('careers.create'), 'باز کردن فرم')
                ->perform(fn (TutorialRun $run): JobApplication => self::applyForJob(
                    self::position($run),
                    ['first_name' => 'نیما', 'last_name' => 'شریفی', 'national_code' => '0050070096', 'gender' => Gender::Male, 'mobile' => '09120000014'],
                ))
                ->doneWhen(fn (TutorialRun $run): bool => self::case($run) !== null),
            Mission::make('claim-and-approve', 'برداشتن، یادداشت و تأیید')
                ->actor($manager, fn (TutorialRun $run): ?User => self::manager($run))
                ->steps([
                    'با حساب مریم صادقی وارد شوید و «کارتابل من» را باز کنید.',
                    '«برداشتن کار» را بزنید تا پرونده فقط در کارتابل خودش بماند (اگر چند نفر این نقش را داشتند).',
                    'با «افزودن یادداشت» بنویسید «مصاحبه‌ی تلفنی انجام شد» و بعد «تأیید» را بزنید.',
                ])
                ->url($managerCartable)
                ->perform(fn (TutorialRun $run): mixed => self::east($run)->execute(function () use ($run): void {
                    $engine = app(WorkflowEngine::class);
                    $engine->claim(self::case($run), self::manager($run));
                    $engine->comment(self::case($run), self::manager($run), 'مصاحبه‌ی تلفنی انجام شد.');
                    $engine->approve(self::case($run), self::manager($run));
                }))
                ->doneWhen(fn (TutorialRun $run): bool => self::hasAction($run, CaseActionType::Commented) && self::hasAction($run, CaseActionType::Approved)),
            Mission::make('return', 'برگشت پرونده')
                ->steps([
                    'با «بازگشت به حساب خودم» برگردید؛ پرونده در «کارتابل من» شماست.',
                    '«برگشت» را بزنید، «برگشت به مرحله‌ی» «بررسی در شعبه» و «علت برگشت» («سوابق کاری را بپرسید») را بنویسید.',
                ])
                ->url($myCartable)
                ->perform(fn (TutorialRun $run, User $owner): mixed => Tenant::primary()->execute(fn () => app(WorkflowEngine::class)->returnTo(self::case($run), $owner, 0, 'سوابق کاری را بپرسید.')))
                ->doneWhen(fn (TutorialRun $run): bool => self::hasAction($run, CaseActionType::Returned)),
            Mission::make('approve-again', 'تأیید دوباره')
                ->actor($manager, fn (TutorialRun $run): ?User => self::manager($run))
                ->steps([
                    'با حساب مریم صادقی وارد شوید؛ پرونده با علت برگشت دوباره در کارتابلش است.',
                    'پرونده را باز کنید، علت برگشت را در سابقه‌ی آن ببینید و دوباره «تأیید» را بزنید.',
                ])
                ->url($managerCartable)
                ->perform(fn (TutorialRun $run): mixed => self::east($run)->execute(fn () => app(WorkflowEngine::class)->approve(self::case($run), self::manager($run))))
                ->doneWhen(fn (TutorialRun $run): bool => self::approvedAfterReturn($run)),
            Mission::make('reject', 'رد و پیگیری پرونده')
                ->steps([
                    'با «بازگشت به حساب خودم» برگردید و در «کارتابل من» «رد» را بزنید و «علت رد» را بنویسید.',
                    'در «کارتابل → همه‌ی پرونده‌ها» پرونده‌ی ردشده را باز کنید و «فلوچارت فرایند» را ببینید: مسیر واقعی پرونده، با برگشت و رد.',
                    'در «ماژول‌ها» هم می‌بینید هر بخش سامانه چه می‌کند و به چه بخشی نیاز دارد (در آموزش چیزی را خاموش نکنید: روی همه‌ی کاربران اثر دارد).',
                ])
                ->url($myCartable)
                ->perform(fn (TutorialRun $run, User $owner): mixed => Tenant::primary()->execute(fn () => app(WorkflowEngine::class)->reject(self::case($run), $owner, 'در این دوره نیرو نمی‌گیریم.')))
                ->doneWhen(fn (TutorialRun $run): bool => self::case($run)?->status === CaseStatus::Rejected),
        ];
    }

    /**
     * A new user with the roles in the tenant, as «ایجاد کاربر» makes them.
     */
    private static function makeUser(string $name, string $nationalCode, string $mobile, Tenant $tenant, string ...$roleNames): User
    {
        $user = User::query()->create(['name' => $name, 'national_code' => $nationalCode, 'mobile' => $mobile, 'password' => Str::password(32)]);

        return self::giveRoles($user, $tenant, ...$roleNames);
    }

    private static function giveRoles(User $user, Tenant $tenant, string ...$roleNames): User
    {
        $tenant->execute(fn () => $user->unsetRelation('roles')->assignRole(Role::query()->whereNull('tenant_id')->whereIn('name', $roleNames)->get()));

        return $user->unsetRelation('roles');
    }

    private static function east(TutorialRun $run): ?Tenant
    {
        return $run->made(Tenant::class)->oldest('id')->first();
    }

    private static function west(TutorialRun $run): ?Tenant
    {
        return $run->made(Tenant::class)->oldest('id')->skip(1)->first();
    }

    private static function usersUrl(?Tenant $tenant): ?string
    {
        return $tenant ? UserResource::getUrl('index', panel: 'admin', tenant: $tenant) : null;
    }

    /**
     * A user made in the run who holds all the roles in the tenant.
     *
     * @param  list<string>  $roleNames
     * @param  list<int>  $except  user ids
     */
    private static function userWithRoles(TutorialRun $run, ?Tenant $tenant, array $roleNames, array $except = []): ?User
    {
        $roleIds = array_map('intval', self::roleIds(...$roleNames));

        if ($tenant === null || count($roleIds) !== count($roleNames)) {
            return null;
        }

        return $run->made(User::class)->whereKeyNot($except)->oldest('id')->get()
            ->first(fn (User $user): bool => array_diff($roleIds, $user->roleIdsIn($tenant)) === []);
    }

    private static function manager(TutorialRun $run): ?User
    {
        return self::userWithRoles($run, self::east($run), [DefaultRoles::TENANT_MANAGER, Utils::getPanelUserRoleName()]);
    }

    private static function customRole(TutorialRun $run): ?Role
    {
        return $run->made(Role::class)->has('permissions')->oldest('id')->first();
    }

    /**
     * Hamid: staff in the first branch and (inBoth) the custom role in the second.
     */
    private static function hamid(TutorialRun $run, bool $inBoth = false): ?User
    {
        $except = array_filter([self::manager($run)?->getKey()]);
        $users = $run->made(User::class)->whereKeyNot($except)->oldest('id')->get();
        $staff = array_map('intval', self::roleIds(DefaultRoles::STAFF));
        $east = self::east($run);
        $west = self::west($run);
        $custom = self::customRole($run);

        return $users->first(fn (User $user): bool => $east !== null && array_intersect($staff, $user->roleIdsIn($east)) !== []
            && (! $inBoth || ($west !== null && $custom !== null && in_array($custom->getKey(), $user->roleIdsIn($west)))));
    }

    /**
     * A third person in the first branch, besides the manager and Hamid.
     */
    private static function staffAddedByManager(TutorialRun $run): bool
    {
        $except = array_values(array_filter([self::manager($run)?->getKey(), self::hamid($run)?->getKey()]));

        return count($except) === 2 && self::userWithRoles($run, self::east($run), [DefaultRoles::STAFF], $except) !== null;
    }

    private static function workflow(TutorialRun $run): ?Workflow
    {
        return $run->made(Workflow::class)
            ->where('subject_type', (new JobApplication)->getMorphClass())
            ->get()
            ->first(function (Workflow $workflow): bool {
                $steps = $workflow->stepList();

                return count($steps) >= 2
                    && $steps[0]->assigneeType === AssigneeType::Role
                    && collect($steps)->contains(fn (WorkflowStep $step): bool => $step->allows(StepAction::Return) && $step->allows(StepAction::Reject));
            });
    }

    private static function position(TutorialRun $run): ?JobPosition
    {
        $workflow = self::workflow($run);

        return $workflow ? $run->made(JobPosition::class)->where('is_active', true)->where('workflow_id', $workflow->getKey())->oldest('id')->first() : null;
    }

    private static function case(TutorialRun $run): ?WorkflowCase
    {
        $position = self::position($run);
        $application = $position ? $run->made(JobApplication::class)->where('job_position_id', $position->getKey())->oldest('id')->first() : null;

        return $application ? WorkflowCase::withoutTenantScope()->where('subject_type', $application->getMorphClass())->where('subject_id', $application->getKey())->latest('id')->first() : null;
    }

    private static function hasAction(TutorialRun $run, CaseActionType $type): bool
    {
        return (bool) self::case($run)?->actions()->where('type', $type)->exists();
    }

    private static function approvedAfterReturn(TutorialRun $run): bool
    {
        $case = self::case($run);
        $returned = $case?->actions()->where('type', CaseActionType::Returned)->max('id');

        return $returned !== null && $case->actions()->where('type', CaseActionType::Approved)->where('id', '>', $returned)->exists();
    }

    private static function roleLabel(string $name): string
    {
        return Role::query()->whereNull('tenant_id')->where('name', $name)->value('label') ?? $name;
    }
}
