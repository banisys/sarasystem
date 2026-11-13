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
use App\Workflow\AssigneeType;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use Illuminate\Support\Collection;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Holidays\HolidayResource;
use Modules\Class\Filament\Resources\Terms\TermResource;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\Term;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Services\RegisterEmployee;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Services\ContextExams;
use Modules\Exam\Support\ExamContexts;
use Modules\Exam\Support\QuestionLibrary;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\Nationality;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Filament\Resources\Programs\ProgramResource;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Concerns\FindsEmployees;
use Nwidart\Modules\Facades\Module;

/**
 * «اداره‌ی روزانه‌ی کلاس‌ها در چند شعبه» (ADR 0040,
 * docs/tutorial-scenarios.md): programs offered in some branches, a class
 * with a capacity and a sub-class, weekly and monthly timetables, a branch
 * holiday, and the everyday changes: a substitute who takes attendance, a
 * moved session, a cancelled one with a make-up, a learner who leaves.
 */
class BranchOperations extends Scenario
{
    use FindsEmployees;

    private const string LEVEL_FIELD = 'tutorial_current_level';

    /** The first branch's officer, the second's, and the teachers. */
    private const array PEOPLE = [
        'officer-1' => ['مینا جعفری', '0120140160', '09120000041'],
        'officer-2' => ['کامران صدری', '0130150177', '09120000042'],
        'teacher' => ['بهاره امینی', '0140160183', '09120000043'],
        'substitute' => ['رضا توکلی', '0150170191', '09120000044'],
    ];

    /** The two learners: first name, last name, national code, mobile. */
    private const array LEARNERS = [
        ['نگار', 'رحمانی', '0160180201', '09120000045'],
        ['پویا', 'کریمی', '0170190218', '09120000046'],
    ];

    public function key(): string
    {
        return 'branch-operations';
    }

    public function title(): string
    {
        return 'اداره‌ی روزانه‌ی کلاس‌ها در چند '.config('sarasystem.tenant_label');
    }

    public function description(): string
    {
        $tenant = config('sarasystem.tenant_label');

        return "برنامه‌ی آموزشی در بعضی از {$tenant}‌ها، کلاس با ظرفیت و زیرکلاس، برنامه‌ی هفتگی و ماهانه و تداخل زمانی، تعطیلی یک {$tenant}، و تغییرهای روزمره: مدرس جایگزین، جابه‌جایی جلسه، لغو و جلسه‌ی جبرانی، و رفتن یک ".config('sarasystem.learner_label').'.';
    }

    public function requiredModules(): array
    {
        return ['Class', 'Employee', 'Student'];
    }

    public function chapters(): array
    {
        $tenant = config('sarasystem.tenant_label');
        $tenants = config('sarasystem.tenant_plural_label');
        $learner = config('sarasystem.learner_label');
        $primary = Tenant::primary()?->name ?? 'ستاد';
        $officer = 'کارشناس آموزش شعبه پونک';
        $asOfficer = fn (TutorialRun $run): ?User => self::officer($run, 1);

        return [
            "{$tenants} و برنامه‌ها" => [
                Mission::make('branches', "دو {$tenant}")
                    ->steps(["در «{$tenants}» «شعبه پونک» (نشانی «pounak») و «شعبه تجریش» (نشانی «tajrish») را به همین ترتیب بسازید."])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => self::branch($run, 1) === null ? ['name' => 'شعبه پونک', 'slug' => 'pounak'] : ['name' => 'شعبه تجریش', 'slug' => 'tajrish'])
                    ->perform(function (): void {
                        Tenant::query()->create(['name' => 'شعبه پونک', 'slug' => 'pounak', 'is_active' => true]);
                        Tenant::query()->create(['name' => 'شعبه تجریش', 'slug' => 'tajrish', 'is_active' => true]);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::branch($run, 2) !== null),
                Mission::make('officers', "کارشناس آموزش هر {$tenant}")
                    ->steps([
                        "{$tenant} را «شعبه پونک» کنید و با «ثبت سریع کارمند»، «مینا جعفری» (کد ملی ۰۱۲۰۱۴۰۱۶۰) را با نقش «کارشناس آموزش» و دسترسی به پنل ثبت کنید.",
                        "در «شعبه تجریش» هم «کامران صدری» (کد ملی ۰۱۳۰۱۵۰۱۷۷) را همین‌طور ثبت کنید. کارهای کلاس هر {$tenant} با کارشناس همان {$tenant} است.",
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, 1, EmployeeResource::class))
                    ->fill(fn (TutorialRun $run): array => self::officer($run, 1) === null
                        ? self::quickEntry('officer-1', 'کارشناس آموزش', DefaultRoles::EDUCATION_OFFICER, true)
                        : self::quickEntry('officer-2', 'کارشناس آموزش', DefaultRoles::EDUCATION_OFFICER, true))
                    ->perform(function (TutorialRun $run, User $owner): void {
                        self::register($owner, self::branch($run, 1), 'officer-1', 'کارشناس آموزش', DefaultRoles::EDUCATION_OFFICER, true);
                        self::register($owner, self::branch($run, 2), 'officer-2', 'کارشناس آموزش', DefaultRoles::EDUCATION_OFFICER, true);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::officer($run, 2) !== null),
                Mission::make('teachers', 'مدرس‌ها')
                    ->steps([
                        "«بهاره امینی» (کد ملی ۰۱۴۰۱۶۰۱۸۳) را با نقش «مدرس» در هر دو {$tenant} ثبت کنید: در دومی همان کد ملی را بنویسید تا همان شخص پیدا شود.",
                        '«رضا توکلی» (کد ملی ۰۱۵۰۱۷۰۱۹۱) را با نقش «مدرس» فقط در «شعبه پونک» ثبت کنید؛ او جایگزین می‌شود.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, 1, EmployeeResource::class))
                    ->fill(fn (): array => self::quickEntry('teacher', 'مدرس زبان', DefaultRoles::INSTRUCTOR, false))
                    ->perform(function (TutorialRun $run, User $owner): void {
                        self::register($owner, self::branch($run, 1), 'teacher', 'مدرس زبان', DefaultRoles::INSTRUCTOR, false);
                        self::register($owner, self::branch($run, 2), 'teacher', 'مدرس زبان', DefaultRoles::INSTRUCTOR, false);
                        self::register($owner, self::branch($run, 1), 'substitute', 'مدرس زبان', DefaultRoles::INSTRUCTOR, false);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::teacher($run, 2) !== null && self::substitute($run) !== null),
                Mission::make('workflow', 'فرایند پذیرش')
                    ->steps([
                        "{$tenant} را به «{$primary}» برگردانید؛ در «کارتابل → فرایندها» فرایند «پذیرش زبان‌آموز» را برای «پیش‌ثبت‌نام» بسازید.",
                        "یک مرحله‌ی «پذیرش»: {$tenant} «{$primary}»، شخص: خودتان.",
                    ])
                    ->url(fn (): string => WorkflowResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => 'پذیرش زبان‌آموز', 'subject_type' => (new Preregistration)->getMorphClass()])
                    ->perform(fn (TutorialRun $run, User $owner): Workflow => Workflow::query()->create([
                        'subject_type' => (new Preregistration)->getMorphClass(),
                        'name' => 'پذیرش زبان‌آموز',
                        'is_active' => true,
                        'steps' => [(new WorkflowStep('پذیرش', Tenant::primary()->getKey(), AssigneeType::User, null, $owner->getKey(), []))->toArray()],
                    ]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::workflow($run) !== null),
                Mission::make('programs', "برنامه‌ها در بعضی {$tenants}")
                    ->steps([
                        'در «پیش‌ثبت‌نام → برنامه‌های آموزشی» «مکالمه‌ی پیشرفته» را با فرایند «پذیرش زبان‌آموز» در هر دو '.$tenant.' ارائه دهید.',
                        '«IELTS» را با همین فرایند فقط در «شعبه تجریش» ارائه دهید.',
                        "فرم «پیش‌ثبت‌نام» برای هر برنامه فقط {$tenants}ی را که آن را ارائه می‌دهند پیشنهاد می‌کند.",
                    ])
                    ->url(fn (): string => ProgramResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => ['title' => self::conversation($run) === null ? 'مکالمه‌ی پیشرفته' : 'IELTS', 'workflow_id' => self::workflow($run)?->getKey(), 'all_tenants' => false, 'is_active' => true])
                    ->perform(function (TutorialRun $run): void {
                        foreach ([['مکالمه‌ی پیشرفته', [1, 2]], ['IELTS', [2]]] as [$title, $branches]) {
                            $program = Program::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'workflow_id' => self::workflow($run)->getKey(), 'title' => $title, 'all_tenants' => false, 'is_active' => true]);
                            $program->availableInTenants()->attach(array_map(fn (int $branch): int => self::branch($run, $branch)->getKey(), $branches));
                        }
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::conversation($run) !== null && self::ielts($run) !== null),
                Mission::make('program-field', 'فیلد مخصوص یک برنامه')
                    ->steps([
                        'از منوی ردیف «مکالمه‌ی پیشرفته» «فیلدهای فرم» را بزنید.',
                        'فیلد تک‌انتخابی «سطح فعلی زبان» با گزینه‌های «B1»، «B2» و «C1» اضافه کنید؛ فقط فرم همین برنامه آن را می‌پرسد.',
                    ])
                    ->url(fn (): string => ProgramResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->perform(fn (TutorialRun $run): mixed => self::conversation($run)->formCustomizations()->create([
                        'tenant_id' => Tenant::primary()->getKey(),
                        'form' => PreregistrationForm::KEY,
                        'field_modes' => [],
                        'fields' => [['key' => self::LEVEL_FIELD, 'label' => 'سطح فعلی زبان', 'type' => 'select', 'required' => true, 'options' => ['B1', 'B2', 'C1'], 'help' => null, 'section' => null]],
                    ]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::conversationForm($run) !== null),
            ],
            "پذیرش {$learner}‌ها" => [
                Mission::make('preregistrations', 'دو پیش‌ثبت‌نام')
                    ->actor('متقاضی (فرم عمومی سایت)')
                    ->steps([
                        'فرم «پیش‌ثبت‌نام» را دو بار بفرستید، هر دو برای «مکالمه‌ی پیشرفته» و «شعبه پونک»:',
                        '«نگار رحمانی» (کد ملی ۰۱۶۰۱۸۰۲۰۱) و «پویا کریمی» (کد ملی ۰۱۷۰۱۹۰۲۱۸)، با «سطح فعلی زبان» B2. موبایل خودتان را برای کد تأیید بنویسید.',
                        'برنامه‌ی «IELTS» را انتخاب کنید و ببینید فقط «شعبه تجریش» پیشنهاد می‌شود.',
                    ])
                    ->url(fn (): string => route('preregister.create'), 'باز کردن فرم')
                    ->perform(fn (TutorialRun $run): mixed => Tenant::primary()->execute(function () use ($run): void {
                        $program = self::conversation($run);

                        foreach (self::LEARNERS as [$first, $last, $nationalCode, $mobile]) {
                            $preregistration = Preregistration::query()->create([
                                'tenant_id' => $program->tenant_id, 'program_id' => $program->getKey(), 'program_title' => $program->title,
                                'preferred_tenant_id' => self::branch($run, 1)->getKey(), 'status' => PreregistrationStatus::Submitted,
                                'first_name' => $first, 'last_name' => $last, 'nationality' => Nationality::Iranian, 'national_code' => $nationalCode,
                                'birth_date' => '2004-06-01', 'gender' => $first === 'نگار' ? Gender::Female : Gender::Male, 'mobile' => $mobile, 'city' => 'تهران',
                                'custom_fields' => [['key' => self::LEVEL_FIELD, 'label' => 'سطح فعلی زبان', 'type' => 'select', 'section' => null, 'value' => 'B2']],
                            ]);
                            app(WorkflowEngine::class)->start($program->workflow, $preregistration);
                        }
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::preregistrations($run)->count() >= 2),
                Mission::make('admissions', 'پذیرش هر دو')
                    ->steps([
                        'در «کارتابل من» هر دو پرونده را «تأیید» کنید: '.$tenant.' «شعبه پونک» و نقش «'.$learner.'».',
                    ])
                    ->url(fn (): string => WorkflowCaseResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => ['tenant_id' => self::branch($run, 1)?->getKey(), 'role_ids' => self::roleIds(DefaultRoles::LEARNER)])
                    ->perform(fn (TutorialRun $run, User $owner): mixed => Tenant::primary()->execute(function () use ($run, $owner): void {
                        foreach (self::preregistrations($run) as $preregistration) {
                            app(WorkflowEngine::class)->approve(self::caseOf($preregistration), $owner, data: [
                                'tenant_id' => self::branch($run, 1)->getKey(), 'role_ids' => self::roleIds(DefaultRoles::LEARNER), 'enrolled_at' => today()->toDateString(),
                            ]);
                        }
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::learners($run)->count() >= 2),
            ],
            'کلاس‌ها و برنامه‌ی آن‌ها' => [
                Mission::make('term', 'دوره‌ی آموزشی')
                    ->steps(['در «کلاس‌ها → دوره‌های آموزشی» «ترم زمستان» را از امروز تا سه ماه بعد بسازید.'])
                    ->url(fn (): string => TermResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['title' => 'ترم زمستان', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addMonths(3)->toDateString()])
                    ->perform(fn (): Term => Term::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'title' => 'ترم زمستان', 'starts_on' => today(), 'ends_on' => today()->addMonths(3), 'is_active' => true]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::term($run) !== null),
                Mission::make('classroom', 'کلاس با ظرفیت')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'با حساب «مینا جعفری» وارد شوید؛ در «کلاس‌ها → کلاس‌ها» «Conversation B2» را با «ترم زمستان» و ظرفیت ۱ بسازید.',
                        'در «اعضا» هر دو زبان‌آموز را با هم اضافه کنید و پیام ظرفیت را بخوانید؛ کلاس را ویرایش کنید، ظرفیت را ۲ کنید و دوباره اضافه کنید.',
                        '«بهاره امینی» را هم با نقش «مدرس» اضافه کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, 1, ClassroomResource::class))
                    ->fill(fn (TutorialRun $run): array => ['title' => 'Conversation B2', 'term_id' => self::term($run)?->getKey(), 'mode' => ClassMode::InPerson->value, 'capacity' => 1])
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(function () use ($run): void {
                        $classroom = Classroom::query()->create([
                            'tenant_id' => self::branch($run, 1)->getKey(), 'term_id' => self::term($run)->getKey(), 'title' => 'Conversation B2',
                            'mode' => ClassMode::InPerson, 'location' => 'اتاق ۱', 'capacity' => 2, 'is_active' => true,
                        ]);
                        $members = app(ClassMembers::class);
                        $members->add($classroom, self::classRole(attendee: true), self::learners($run)->pluck('user_id')->all());
                        $members->add($classroom, self::classRole(attendee: false), [self::teacher($run, 1)->getKey()]);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::conversationClass($run) !== null),
                Mission::make('sub-class', 'زیرکلاس')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'کلاس «Conversation B2 - گروه ب» را با «کلاس اصلی» «Conversation B2» بسازید.',
                        'در «اعضا» فقط «نگار رحمانی» را اضافه کنید: اعضای زیرکلاس از کلاس اصلی می‌آیند.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, 1, ClassroomResource::class))
                    ->fill(fn (TutorialRun $run): array => ['title' => 'Conversation B2 - گروه ب', 'parent_id' => self::conversationClass($run)?->getKey(), 'term_id' => self::term($run)?->getKey(), 'mode' => ClassMode::InPerson->value])
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(function () use ($run): void {
                        $parent = self::conversationClass($run);
                        $child = Classroom::query()->create([
                            'tenant_id' => $parent->tenant_id, 'term_id' => $parent->term_id, 'parent_id' => $parent->getKey(),
                            'title' => 'Conversation B2 - گروه ب', 'mode' => ClassMode::InPerson, 'is_active' => true,
                        ]);
                        app(ClassMembers::class)->add($child, self::classRole(attendee: true), [self::learners($run)->first()->user_id]);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::subClass($run) !== null),
                Mission::make('weekly', 'برنامه‌ی هفتگی')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'در «Conversation B2» از منوی ردیف کلاس «برنامه» ← «افزودن برنامه درسی»: تکرار «هفتگی»، سه روز در هفته (امروز، دو روز بعد و چهار روز بعد)، از امروز تا چهار هفته‌ی بعد.',
                        'برنامه برای تاریخ‌های گذشته هم جلسه می‌سازد؛ ویرایش دوباره، جلسه‌های گذشته‌ی موجود و اطلاعات ثبت‌شده‌ی آن‌ها را تغییر نمی‌دهد.',
                        'ساعت از ده دقیقه‌ی بعد به مدت یک ساعت، مدرس «بهاره امینی»؛ ذخیره کنید و با دکمه‌ی «جلسه‌ها» بالای صفحه‌ی برنامه، جلسه‌ها را در تقویم (نمای «هفتگی» یا «ماهانه») ببینید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classUrl($run))
                    ->perform(function (TutorialRun $run): void {
                        [$start, $end] = self::soon();

                        self::branch($run, 1)->execute(fn () => app(SessionPlanner::class)->saveSchedule(null, self::conversationClass($run), [
                            'frequency' => Frequency::Weekly,
                            'interval' => 1,
                            'weekdays' => self::weekdays([0, 2, 4]),
                            'starts_on' => today()->toDateString(),
                            'ends_on' => today()->addWeeks(4)->toDateString(),
                            'start_time' => $start->format('H:i'),
                            'end_time' => $end->format('H:i'),
                            'teacher_ids' => [self::teacher($run, 1)->getKey()],
                        ]));
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::weeklySchedule($run) !== null && self::sessions($run)->count() >= 3),
                Mission::make('exam-schedule', 'برنامهٔ امتحانی کلاس')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'در صفحهٔ «برنامه» کلاس Conversation B2، «افزودن برنامه امتحانی» را بزنید. برای شش روز بعد، ساعت ۱۰ تا ۱۱، یک جلسه ثبت کنید. هر ردیف فرم تاریخ، ساعت و مکان خودش را دارد. اگر به مراقب نیاز دارید، نام او را از میان کارکنان همهٔ شعبه‌ها جست‌وجو کنید.',
                        'از بالای صفحه «جلسه‌ها» را باز کنید و روی جلسه‌ای با نشان بنفش «امتحانی» بزنید. در نمای سالانه، لوزی بنفش روزهای دارای امتحان را مشخص می‌کند. صفحهٔ اختصاصی امتحان در زبانه‌ای تازه باز می‌شود و زمان، مکان، مراقبان و حضور و غیاب را نشان می‌دهد.',
                        'زیر عنوان جلسه، «چیدمان صندلی‌ها» را باز کنید. چیدمان خودکار را بزنید، جای دو نفر را عوض کنید یا بین آن‌ها یک صندلی خالی بگذارید و چیدمان را ذخیره کنید.',
                        ...(self::enabled('Campus') ? [
                            'اختیاری: زیر عنوان جلسه، «نمای سه‌بعدی» را بزنید. مراقبان جلوی اتاق‌اند و وضعیت حضور هر شرکت‌کننده روی صندلی‌اش دیده می‌شود. اگر چیدمان را ذخیره کرده باشید، صندلی‌ها مطابق همان چیدمان نمایش داده می‌شوند.',
                        ] : []),
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classUrl($run, 'schedules'))
                    ->perform(fn (TutorialRun $run) => self::branch($run, 1)->execute(
                        fn () => app(SessionPlanner::class)->addExamSessions(self::conversationClass($run), [[
                            'date' => today()->addDays(6)->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00',
                        ]]),
                    ))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(ClassSession::class)
                        ->where('classroom_id', self::conversationClass($run)?->getKey())
                        ->where('kind', SessionKind::Exam)
                        ->exists()),
                Mission::make('monthly', 'برنامه‌ی ماهانه و تداخل')
                    ->actor('کارشناس آموزش شعبه تجریش', fn (TutorialRun $run): ?User => self::officer($run, 2))
                    ->steps([
                        'با حساب «کامران صدری» وارد شوید؛ کلاس «IELTS Prep» را با «ترم زمستان» بسازید و «بهاره امینی» را «مدرس» آن کنید.',
                        'در «برنامه» تکرار «ماهانه» با «دومین» و روزِ فردا (روز هفته)، سه ماه، مدرس بهاره بسازید.',
                        'اول یکی از روزهای کلاس «Conversation B2» را بزنید: چون مدرس در هر '.$tenant.' فقط یک جا هست، تداخل رد می‌شود. بعد روز درست را بزنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, 2, ClassroomResource::class))
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 2)->execute(function () use ($run): void {
                        [$start, $end] = self::soon();
                        $classroom = Classroom::query()->create([
                            'tenant_id' => self::branch($run, 2)->getKey(), 'term_id' => self::term($run)->getKey(), 'title' => 'IELTS Prep', 'mode' => ClassMode::InPerson, 'is_active' => true,
                        ]);
                        app(ClassMembers::class)->add($classroom, self::classRole(attendee: false), [self::teacher($run, 2)->getKey()]);
                        app(SessionPlanner::class)->saveSchedule(null, $classroom, [
                            'frequency' => Frequency::Monthly,
                            'interval' => 1,
                            'weekdays' => self::weekdays([1]),
                            'month_week' => 2,
                            'starts_on' => today()->toDateString(),
                            'ends_on' => today()->addMonths(3)->toDateString(),
                            'start_time' => $start->format('H:i'),
                            'end_time' => $end->format('H:i'),
                            'teacher_ids' => [self::teacher($run, 2)->getKey()],
                        ]);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::monthlySchedule($run) !== null),
                Mission::make('holiday', "تعطیلی یک {$tenant}")
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'با حساب «مینا جعفری» در «کلاس‌ها → تعطیلات» تعطیلی «تعمیرات ساختمان» را برای هفته‌ی بعد، همین روز، بسازید.',
                        'تعطیلی این '.$tenant.' فقط کلاس‌های همین '.$tenant.' را جابه‌جا می‌کند: جلسه‌ی آن روز Conversation B2 از «جلسه‌ها» حذف می‌شود.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::branchUrl($run, 1, HolidayResource::class))
                    ->fill(fn (): array => ['title' => 'تعمیرات ساختمان', 'starts_on' => today()->addWeek()->toDateString(), 'ends_on' => today()->addWeek()->toDateString()])
                    ->perform(fn (TutorialRun $run): Holiday => Holiday::query()->create(['tenant_id' => self::branch($run, 1)->getKey(), 'title' => 'تعمیرات ساختمان', 'starts_on' => today()->addWeek(), 'ends_on' => today()->addWeek()]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::branchHoliday($run) !== null
                        && ! self::sessions($run)->contains(fn (ClassSession $session): bool => $session->starts_at->isSameDay(self::branchHoliday($run)->starts_on))),
            ],
            'تغییرهای روزمره' => [
                Mission::make('substitute', 'مدرس جایگزین')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'در «Conversation B2» از منوی ردیف کلاس «جلسه‌ها» را باز کنید و روی جلسه‌ی امروز در تقویم بزنید؛ در صفحه‌ی جلسه که در تب تازه باز می‌شود «سایر ← مدرس‌ها و جایگزین» را بزنید و کنار «بهاره امینی» در «جایگزین» «رضا توکلی» را انتخاب کنید.',
                        'یک جلسه می‌تواند چند مدرس داشته باشد (در «برنامه» یا همین‌جا با «افزودن مدرس»)؛ جایگزین فقط جای همان مدرس می‌آید و بقیه سر کلاس می‌مانند.',
                        'جایگزین باید عضو همین '.$tenant.' باشد و در آن ساعت جای دیگری درس نداشته باشد.',
                        'اختیاری: در «کارکنان ← کارمندان» با «جلسه‌های تدریس» در ردیف «رضا توکلی»، این جلسه را در تقویم تدریس او ببینید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classUrl($run))
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(fn () => app(SessionPlanner::class)->setSubstitute(self::sessions($run)->first(), self::teacher($run, 1)->getKey(), self::substitute($run)->getKey())))
                    ->doneWhen(fn (TutorialRun $run): bool => self::substituted($run) !== null),
                Mission::make('substitute-attendance', 'حضور و غیاب با جایگزین')
                    ->actor('مدرس جایگزین', fn (TutorialRun $run): ?User => self::substitute($run))
                    ->steps([
                        'با حساب «رضا توکلی» وارد شوید؛ جلسه‌ی امروز در «کلاس‌های من» او هست.',
                        'هر دو زبان‌آموز را «حاضر» بزنید (یکی را «با تأخیر» ده دقیقه) و ثبت کنید.',
                    ])
                    ->url(fn (): string => route('my-classes.index'))
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(fn () => app(AttendanceRegister::class)->record(
                        self::substituted($run),
                        self::learners($run)->values()->mapWithKeys(fn (Student $student, int $index): array => [
                            $student->user_id => $index === 0 ? ['status' => AttendanceStatus::Present] : ['status' => AttendanceStatus::Late, 'late_minutes' => 10],
                        ])->all(),
                        self::substitute($run),
                    )))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Attendance::class)->where('recorded_by_id', self::substitute($run)?->getKey())->exists()),
                ...(Module::isEnabled('Exam') ? [
                    Mission::make('session-exam', 'آزمون مستقل جلسه')
                        ->actor($officer, $asOfficer)
                        ->steps([
                            'در پنل کاربری شعبه پونک «بانک سؤال» را باز کنید و بانک «تمرین مکالمه» را بسازید. اگر می‌خواهید همکاران همین شعبه از بانک استفاده کنند، آن را عمومی کنید.',
                            'یک سؤال تشریحی فعال با متن «خودتان را به انگلیسی معرفی کنید.» و بارم ۴ به بانک اضافه کنید. سؤال هم تنظیم اشتراک جداگانه دارد؛ سؤال خصوصی در بانک عمومی برای همکاران نمایش داده نمی‌شود.',
                            'جلسهٔ امروز Conversation B2 را باز کنید و وارد «آزمون‌های جلسه» شوید. عنوان «تمرین مکالمه»، مدت ۱۰ دقیقه، نمرهٔ کل ۲۰ و نمرهٔ قبولی ۱۲ را وارد کنید. بانک خودتان را با تعداد یک سؤال انتخاب کنید و «ساخت پیش‌نویس آزمون» را بزنید.',
                            'اختیاری: پیش از ساخت پیش‌نویس، آیکن «انتخاب سؤال‌ها» را کنار بانک بزنید. سؤال تیک‌خورده را ببینید. اگر سؤال‌های دیگری به بانک اضافه کرده‌اید، تیک‌ها را تغییر دهید و «ثبت انتخاب» را بزنید تا تعداد سؤال هم به‌روز شود.',
                            'روی کارت پیش‌نویس، آیکن «پیش‌نمایش سؤال‌ها» را بزنید. اگر آزمون بیش از یک سؤال دارد، با کشیدن سؤال‌ها یا دکمه‌های بالا و پایین ترتیبشان را عوض کنید و «ذخیرهٔ ترتیب» را بزنید.',
                            '«انتشار آزمون» را بزنید. شرکت‌کنندگان از اعضای کلاس در روز جلسه تعیین می‌شوند. تا پیش از شروع اولین شرکت‌کننده، می‌توانید آزمونی را که ساخته‌اید حذف کنید و دوباره بسازید.',
                            'اختیاری: یکی از زبان‌آموزان در زمان جلسه وارد آزمون شود و پاسخ دهد. مدرس در «نتایج و تصحیح ← پاسخ‌ها و تصحیح» نمرهٔ پاسخ تشریحی را ثبت کند.',
                        ])
                        ->url(fn (TutorialRun $run): ?string => self::classUrl($run))
                        ->perform(function (TutorialRun $run): void {
                            self::branch($run, 1)->execute(function () use ($run): void {
                                $officer = self::officer($run, 1);
                                $bank = new QuestionBank(['title' => 'تمرین مکالمه']);
                                $bank->forceFill(['owner_id' => $officer->id])->save();
                                $question = new Question(['type' => 'descriptive', 'body' => 'خودتان را به انگلیسی معرفی کنید.', 'score' => 4, 'is_active' => true]);
                                $question->forceFill(['owner_id' => $officer->id])->save();
                                $question->banks()->attach($bank);
                                $context = app(ExamContexts::class)->resolve('class-session', self::substituted($run)->id, $officer);
                                $exam = app(ContextExams::class)->generate($context, [
                                    'title' => 'تمرین مکالمه', 'duration_minutes' => 10, 'total_score' => 20, 'passing_score' => 12,
                                    'banks' => [['bank_id' => $bank->id, 'question_count' => 1]],
                                ], $officer, app(QuestionLibrary::class));
                                $exam->update(['is_published' => true]);
                            });
                        })
                        ->doneWhen(fn (TutorialRun $run): bool => $run->made(Exam::class)->where('context_type', 'class-session')->where('context_id', self::substituted($run)?->id)->where('is_published', true)->exists()),
                ] : []),
                Mission::make('move', 'جابه‌جایی جلسه')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'با حساب «مینا جعفری»، جلسه‌ی دوم Conversation B2 را در تقویم باز کنید و با «سایر ← جابه‌جایی» آن را پنج روز بعد، همان ساعت، به «اتاق ۳» ببرید.',
                        'زمان اصلی جلسه در جزئیات آن می‌ماند و در تقویم نشانه‌ی جابه‌جایی دارد؛ ساختن دوباره‌ی برنامه جلسه‌ی جابه‌جاشده را تغییر نمی‌دهد.',
                        'اختیاری: با حساب مدیر کل، در صفحه‌ی جلسه آیکن «ورود با این کاربر» را کنار نام مدرس در «مدرس‌ها» یا کنار نام فراگیر در پنجره‌ی «حضور و غیاب» بزنید و تأیید کنید؛ با «بازگشت به حساب خودم» به همین جلسه برمی‌گردید.',
                        ...(self::enabled('Campus') ? [
                            'اختیاری: جلسه‌ی امروز را در تقویم باز کنید و «نمای سه‌بعدی» را بزنید: حضور و غیابی که مدرس جایگزین گرفت روی صندلی‌های کلاس دیده می‌شود؛ مدرس‌ها جلوی کلاس و بقیه‌ی کادر کلاس (ناظر، پشتیبان، …) ته کلاس ایستاده‌اند، هر نقش با رنگ خودش.',
                            'اختیاری: با حساب مدیر کل، در فهرست '.config('sarasystem.tenant_plural_label').' از عملیات ردیف شعبه «ساختمان سه‌بعدی» را باز کنید: هر کلاس یک اتاق در ساختمان شعبه است و اتاق کلاس در حال برگزاری زرد است؛ با کلیک روی اتاق جزئیات کلاس باز می‌شود.',
                            'اختیاری: در همان صفحه با دکمه‌ی «نقشه‌ی '.config('sarasystem.tenant_plural_label').'» هر دو شعبه را کنار هم ببینید و با کلیک روی ساختمان به آن بروید.',
                        ] : []),
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classUrl($run))
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(function () use ($run): void {
                        $session = self::sessions($run)->get(1);
                        app(SessionPlanner::class)->move($session, today()->addDays(5)->toDateString(), $session->starts_at->format('H:i'), $session->ends_at->format('H:i'), 'اتاق ۳');
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(ClassSession::class)->whereNotNull('original_starts_at')->exists()),
                Mission::make('cancel', 'لغو و جلسه‌ی جبرانی')
                    ->actor($officer, $asOfficer)
                    ->steps([
                        'جلسه‌ی سوم را در تقویم باز کنید، «سایر ← لغو جلسه» را بزنید و دلیل «بیماری مدرس» را بنویسید؛ در تقویم قرمز می‌شود.',
                        'با «جلسه‌ی جبرانی / فوق‌العاده» بالای تقویم یک جلسه‌ی جبرانی سه روز بعد، همان ساعت، با «بهاره امینی» بسازید.',
                        'اگر جلسه‌ای که هنوز برگزار نشده نباید در تقویم بماند، به جای لغو از «سایر ← حذف» استفاده می‌شود؛ این‌جا لازم نیست آن را بزنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classUrl($run))
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(function () use ($run): void {
                        $planner = app(SessionPlanner::class);
                        $session = self::sessions($run)->firstWhere(fn (ClassSession $session): bool => $session->original_starts_at === null && ! $session->hasSubstitute());
                        $planner->cancel($session, 'بیماری مدرس');
                        $planner->addSession(self::conversationClass($run), [
                            'date' => today()->addDays(3)->toDateString(),
                            'start_time' => $session->starts_at->format('H:i'),
                            'end_time' => $session->ends_at->format('H:i'),
                            'teacher_ids' => [self::teacher($run, 1)->getKey()],
                            'kind' => SessionKind::MakeUp,
                        ]);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(ClassSession::class)->where('status', SessionStatus::Cancelled)->exists()
                        && $run->made(ClassSession::class)->where('kind', SessionKind::MakeUp)->exists()),
                Mission::make('leave', "رفتن یک {$learner}")
                    ->actor($officer, $asOfficer)
                    ->steps([
                        '«پویا کریمی» از کلاس می‌رود: در «اعضا» ردیف او را ویرایش کنید و «تاریخ خروج» را امروز بگذارید.',
                        'او در فهرست با تاریخ خروج می‌ماند و از جلسه‌های بعد در حضور و غیاب نیست.',
                        "اختیاری: در فهرست {$learner}ان با «جلسه‌های کلاس» در ردیف «پویا کریمی»، جلسه‌های او را با حضور و غیابش در تقویم ببینید؛ جلسه‌های بعد از خروج در آن نیست.",
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classUrl($run))
                    ->perform(fn (TutorialRun $run): mixed => self::branch($run, 1)->execute(function () use ($run): void {
                        $member = self::conversationClass($run)->members()->where('user_id', self::learners($run)->last()->user_id)->firstOrFail();
                        app(ClassMembers::class)->update($member, $member->classRole, $member->joined_on, today());
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(ClassMember::class)->whereNotNull('left_on')->exists()),
                Mission::make('close-program', 'بستن پیش‌ثبت‌نام')
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید؛ در «برنامه‌های آموزشی» «مکالمه‌ی پیشرفته» را غیرفعال کنید.',
                        'دیگر در فرم «پیش‌ثبت‌نام» نیست؛ زبان‌آموزان و کلاس‌هایش سر جایشان می‌مانند.',
                    ])
                    ->url(fn (): string => ProgramResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->perform(fn (TutorialRun $run): bool => self::conversation($run, active: true)->update(['is_active' => false]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::conversation($run, active: false) !== null),
            ],
        ];
    }

    /**
     * Quick entry's values for one of the scenario's people.
     *
     * @return array<string, mixed>
     */
    private static function quickEntry(string $person, string $jobTitle, string $role, bool $panelAccess): array
    {
        [$name, $nationalCode, $mobile] = self::PEOPLE[$person];

        return ['national_code' => $nationalCode, 'name' => $name, 'mobile' => $mobile, 'job_title' => $jobTitle, 'role_ids' => self::roleIds($role), 'panel_access' => $panelAccess];
    }

    private static function register(User $owner, Tenant $tenant, string $person, string $jobTitle, string $role, bool $panelAccess): void
    {
        [$name, $nationalCode, $mobile] = self::PEOPLE[$person];

        app(RegisterEmployee::class)->register(
            $owner,
            $tenant,
            person: ['name' => $name, 'national_code' => $nationalCode, 'mobile' => $mobile],
            employment: ['job_title' => $jobTitle, 'employment_type' => EmploymentType::PartTime, 'hired_at' => today()],
            roleIds: self::roleIds($role),
            panelAccess: $panelAccess,
        );
    }

    /**
     * The weekdays this many days from today.
     *
     * @param  list<int>  $offsets
     * @return list<Weekday>
     */
    private static function weekdays(array $offsets): array
    {
        return array_map(fn (int $offset): Weekday => Weekday::from(today()->addDays($offset)->dayOfWeek), $offsets);
    }

    private static function classRole(bool $attendee): ClassRole
    {
        $roles = ClassRole::withoutTenantScope()->where('tenant_id', Tenant::primary()->getKey())->orderBy('sort')->get();
        $instructor = array_map('intval', self::roleIds(DefaultRoles::INSTRUCTOR));

        return $attendee
            ? $roles->firstOrFail('is_attendee', true)
            : ($roles->first(fn (ClassRole $role): bool => ! $role->is_attendee && array_intersect($instructor, $role->allowed_role_ids ?? []) !== [])
                ?? $roles->firstOrFail(fn (ClassRole $role): bool => ! $role->is_attendee && $role->takes_attendance));
    }

    /**
     * The first or second branch made in the run.
     */
    private static function branch(TutorialRun $run, int $number): ?Tenant
    {
        return $run->made(Tenant::class)->oldest('id')->skip($number - 1)->first();
    }

    /**
     * @param  class-string  $resource
     */
    private static function branchUrl(TutorialRun $run, int $number, string $resource): ?string
    {
        return ($branch = self::branch($run, $number)) ? $resource::getUrl('index', panel: 'admin', tenant: $branch) : null;
    }

    private static function officer(TutorialRun $run, int $branch): ?User
    {
        return self::employeeIn($run, self::branch($run, $branch), DefaultRoles::EDUCATION_OFFICER, withPanel: true);
    }

    /**
     * Bahareh, an instructor in the branch (in both, for the second).
     */
    private static function teacher(TutorialRun $run, int $branch): ?User
    {
        $first = self::employeeIn($run, self::branch($run, 1), DefaultRoles::INSTRUCTOR);

        if ($branch === 1 || $first === null) {
            return $first;
        }

        $second = self::branch($run, 2);

        return $second && in_array((int) self::roleIds(DefaultRoles::INSTRUCTOR)[0], $first->roleIdsIn($second), true) ? $first : null;
    }

    /**
     * Another instructor of the first branch.
     */
    private static function substitute(TutorialRun $run): ?User
    {
        $teacher = self::teacher($run, 1);
        $branch = self::branch($run, 1);
        $instructor = (int) self::roleIds(DefaultRoles::INSTRUCTOR)[0];

        return $teacher && $branch ? $run->made(User::class)->whereKeyNot($teacher->getKey())->get()
            ->first(fn (User $user): bool => in_array($instructor, $user->roleIdsIn($branch), true)) : null;
    }

    private static function workflow(TutorialRun $run): ?Workflow
    {
        return $run->made(Workflow::class)->where('subject_type', (new Preregistration)->getMorphClass())->get()
            ->first(fn (Workflow $workflow): bool => $workflow->stepList() !== []);
    }

    /**
     * The program offered in both branches.
     */
    private static function conversation(TutorialRun $run, ?bool $active = true): ?Program
    {
        $first = self::branch($run, 1);
        $second = self::branch($run, 2);

        return $first && $second ? $run->made(Program::class)->when($active !== null, fn ($query) => $query->where('is_active', $active))->with('availableInTenants')->oldest('id')->get()
            ->first(fn (Program $program): bool => $program->availableInTenants->contains($first) && $program->availableInTenants->contains($second)) : null;
    }

    /**
     * The program offered in the second branch only.
     */
    private static function ielts(TutorialRun $run): ?Program
    {
        $first = self::branch($run, 1);
        $second = self::branch($run, 2);

        return $first && $second ? $run->made(Program::class)->where('is_active', true)->where('all_tenants', false)->with('availableInTenants')->get()
            ->first(fn (Program $program): bool => $program->availableInTenants->contains($second) && ! $program->availableInTenants->contains($first)) : null;
    }

    private static function conversationForm(TutorialRun $run): ?FormCustomization
    {
        $program = self::conversation($run);

        return $program ? FormCustomization::withoutTenantScope()->where('owner_type', $program->getMorphClass())->where('owner_id', $program->getKey())->get()
            ->first(fn (FormCustomization $form): bool => collect($form->fields)->contains(fn (array $field): bool => $field['type'] === 'select')) : null;
    }

    /**
     * @return Collection<int, Preregistration>
     */
    private static function preregistrations(TutorialRun $run): Collection
    {
        $program = self::conversation($run);

        return $program ? $run->made(Preregistration::class)->where('program_id', $program->getKey())->oldest('id')->get() : collect();
    }

    /**
     * The learners admitted in the first branch, in the order they came.
     *
     * @return Collection<int, Student>
     */
    private static function learners(TutorialRun $run): Collection
    {
        $branch = self::branch($run, 1);

        return $branch ? $run->made(Student::class)->where('tenant_id', $branch->getKey())->oldest('id')->get() : collect();
    }

    private static function term(TutorialRun $run): ?Term
    {
        return $run->made(Term::class)->oldest('id')->first();
    }

    /**
     * The first branch's top-level class with both learners and a teacher.
     */
    private static function conversationClass(TutorialRun $run): ?Classroom
    {
        $branch = self::branch($run, 1);

        return $branch ? $run->made(Classroom::class)->where('tenant_id', $branch->getKey())->whereNull('parent_id')->oldest('id')->get()
            ->first(fn (Classroom $classroom): bool => $classroom->members()->withoutGlobalScopes()->attendees()->count() >= 2
                && $classroom->members()->withoutGlobalScopes()->where('user_id', self::teacher($run, 1)?->getKey())->exists()) : null;
    }

    private static function classUrl(TutorialRun $run, string $page = 'members'): ?string
    {
        $classroom = self::conversationClass($run);

        return $classroom ? ClassroomResource::getUrl($page, ['record' => $classroom], panel: 'admin', tenant: self::branch($run, 1)) : null;
    }

    private static function subClass(TutorialRun $run): ?Classroom
    {
        $parent = self::conversationClass($run);

        return $parent ? $run->made(Classroom::class)->where('parent_id', $parent->getKey())->get()
            ->first(fn (Classroom $classroom): bool => $classroom->members()->withoutGlobalScopes()->attendees()->exists()) : null;
    }

    private static function weeklySchedule(TutorialRun $run): ?ClassSchedule
    {
        $classroom = self::conversationClass($run);

        return $classroom ? $run->made(ClassSchedule::class)->where('classroom_id', $classroom->getKey())->where('frequency', Frequency::Weekly)->oldest('id')->get()
            ->first(fn (ClassSchedule $schedule): bool => count($schedule->weekdays ?? []) >= 2) : null;
    }

    /**
     * The weekly schedule's sessions, from the first.
     *
     * @return Collection<int, ClassSession>
     */
    private static function sessions(TutorialRun $run): Collection
    {
        $schedule = self::weeklySchedule($run);

        return $schedule ? $run->made(ClassSession::class)->where('schedule_id', $schedule->getKey())->orderByRaw('coalesce(original_starts_at, starts_at)')->get() : collect();
    }

    private static function monthlySchedule(TutorialRun $run): ?ClassSchedule
    {
        $branch = self::branch($run, 2);

        return $branch ? $run->made(ClassSchedule::class)->where('tenant_id', $branch->getKey())->where('frequency', Frequency::Monthly)->whereNotNull('month_week')->oldest('id')->first() : null;
    }

    private static function branchHoliday(TutorialRun $run): ?Holiday
    {
        $branch = self::branch($run, 1);

        return $branch ? $run->made(Holiday::class)->where('tenant_id', $branch->getKey())->oldest('id')->first() : null;
    }

    private static function substituted(TutorialRun $run): ?ClassSession
    {
        $substitute = self::substitute($run);

        return $substitute ? self::sessions($run)->first(fn (ClassSession $session): bool => $session->teachers->contains(
            fn (User $teacher): bool => $teacher->pivot->substitute_id === $substitute->getKey(),
        )) : null;
    }
}
