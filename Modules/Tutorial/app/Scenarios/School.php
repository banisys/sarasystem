<?php

namespace Modules\Tutorial\Scenarios;

use App\Authorization\DefaultRoles;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\FormCustomization;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Subjects\SubjectResource;
use Modules\Class\Filament\Resources\TeachingPlans\TeachingPlanResource;
use Modules\Class\Filament\Resources\Terms\TermResource;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\Subject;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Models\Term;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\HomeworkBook;
use Modules\Class\Services\SessionContentBook;
use Modules\Class\Services\SessionPlanner;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Enums\FileKind;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\Documents;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Services\RegisterEmployee;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Filament\Resources\ExamAttempts\ExamAttemptResource;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Filament\Resources\QuestionBanks\QuestionBankResource;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Services\ExamTaking;
use Modules\School\Enums\ScoreScale;
use Modules\School\Filament\Resources\Enrollments\EnrollmentResource;
use Modules\School\Filament\Resources\ScorePeriods\ScorePeriodResource;
use Modules\School\Filament\Resources\Stages\StageResource;
use Modules\School\Models\ClassroomGrade;
use Modules\School\Models\Curriculum;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Score;
use Modules\School\Models\ScorePeriod;
use Modules\School\Models\Stage;
use Modules\School\Services\Enrollments;
use Modules\School\Services\ScoreBook;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\LoginMobileSource;
use Modules\Student\Enums\Nationality;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Filament\Resources\Programs\ProgramResource;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Concerns\FindsEmployees;

/**
 * «مدرسه: از پیش‌ثبت‌نام تا کارنامه» (ADR 0040, docs/tutorial-scenarios.md):
 * a school year end to end. The structure (stages, grades, curriculum,
 * year, holidays), admission with the father as guardian, a class with a
 * weekly timetable and a teaching plan, attendance with the absence SMS,
 * homework, scores, the report card and promotion; with Exam on, an exam.
 */
class School extends Scenario
{
    use FindsEmployees;

    private const string SAMPLE_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    /** The added field of the preregistration form. */
    private const string AVERAGE_FIELD = 'tutorial_last_average';

    public function key(): string
    {
        return 'school';
    }

    public function title(): string
    {
        return 'مدرسه: از پیش‌ثبت‌نام تا کارنامه';
    }

    public function description(): string
    {
        return 'یک سال تحصیلی از اول تا آخر: دوره و پایه و برنامه‌ی درسی، پیش‌ثبت‌نام با مشخصات پدر و پذیرش، کلاس با برنامه‌ی هفتگی و طرح درس، حضور و غیاب با پیامک غیبت به ولی، تکلیف و نمره‌ی آن، دفتر نمره، کارنامه و ارتقا به سال بعد'
            .(self::enabled('Exam') ? '، و یک آزمون آنلاین.' : '.');
    }

    public function requiredModules(): array
    {
        return ['Class', 'Employee', 'School', 'Student'];
    }

    public function chapters(): array
    {
        $tenant = config('sarasystem.tenant_label');
        $tenants = config('sarasystem.tenant_plural_label');
        $learner = config('sarasystem.learner_label');
        $primary = Tenant::primary()?->name ?? 'ستاد';
        $documents = self::enabled('Document');

        return array_filter([
            'ساختار مدرسه' => [
                Mission::make('school', 'ساخت مدرسه')
                    ->steps([
                        "از منوی «{$tenants}» دکمه‌ی «ایجاد» را بزنید.",
                        'نام را «دبیرستان نمونه» و نشانی را «nemooneh» بگذارید و ذخیره کنید.',
                    ])
                    ->url(fn (): string => TenantResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => 'دبیرستان نمونه', 'slug' => 'nemooneh'])
                    ->perform(fn (): Tenant => Tenant::query()->create(['name' => 'دبیرستان نمونه', 'slug' => 'nemooneh', 'is_active' => true]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::school($run) !== null),
                Mission::make('officer', 'ثبت معاون آموزشی')
                    ->steps([
                        "از منوی بالای پنل، {$tenant} را «دبیرستان نمونه» کنید.",
                        'در «کارکنان → کارمندان» با «ثبت سریع کارمند»، «زهرا کاظمی» با کد ملی ۰۰۶۰۰۸۰۱۰۸ و عنوان شغلی «معاون آموزشی» را ثبت کنید.',
                        'نقش «کارشناس آموزش» را بزنید و «دسترسی به پنل مدیریت» را روشن کنید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, EmployeeResource::class))
                    ->fill(fn (): array => [
                        'national_code' => '0060080108', 'name' => 'زهرا کاظمی', 'mobile' => '09120000021', 'job_title' => 'معاون آموزشی',
                        'role_ids' => self::roleIds(DefaultRoles::EDUCATION_OFFICER), 'panel_access' => true,
                    ])
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::registerEmployee($run, $owner, 'زهرا کاظمی', '0060080108', '09120000021', 'معاون آموزشی', DefaultRoles::EDUCATION_OFFICER, panelAccess: true))
                    ->doneWhen(fn (TutorialRun $run): bool => self::officer($run) !== null),
                Mission::make('teacher', 'ثبت معلم')
                    ->steps([
                        'باز با «ثبت سریع کارمند»، «نرگس مرادی» با کد ملی ۰۰۷۰۰۹۰۱۱۴ و عنوان شغلی «معلم ریاضی» را ثبت کنید.',
                        'نقش «مدرس» را بزنید و «دسترسی به پنل مدیریت» را خاموش بگذارید: معلم از «کلاس‌های من» و «دفتر نمره» کار می‌کند.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, EmployeeResource::class))
                    ->fill(fn (): array => [
                        'national_code' => '0070090114', 'name' => 'نرگس مرادی', 'mobile' => '09120000022', 'job_title' => 'معلم ریاضی',
                        'role_ids' => self::roleIds(DefaultRoles::INSTRUCTOR), 'panel_access' => false,
                    ])
                    ->perform(fn (TutorialRun $run, User $owner): mixed => self::registerEmployee($run, $owner, 'نرگس مرادی', '0070090114', '09120000022', 'معلم ریاضی', DefaultRoles::INSTRUCTOR, panelAccess: false))
                    ->doneWhen(fn (TutorialRun $run): bool => self::teacher($run) !== null),
                Mission::make('grades', 'دوره‌ی تحصیلی و پایه‌ها')
                    ->steps([
                        "{$tenant} را به «{$primary}» برگردانید: ساختار مدرسه را ستاد برای همه‌ی مدرسه‌ها تعریف می‌کند.",
                        'در «مدرسه → دوره‌های تحصیلی» دوره‌ی «متوسطه اول» را با مقیاس نمره‌ی «عددی (از ۲۰)» بسازید.',
                        'در «مدرسه → پایه‌ها و برنامه‌ی درسی» پایه‌های «هفتم» و «هشتم» را در همین دوره، به همین ترتیب، بسازید.',
                    ])
                    ->url(fn (): string => StageResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['title' => 'متوسطه اول', 'scale' => ScoreScale::Numeric->value])
                    ->perform(function (): void {
                        $stage = Stage::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'title' => 'متوسطه اول', 'scale' => ScoreScale::Numeric, 'sort' => 1]);

                        foreach (['هفتم', 'هشتم'] as $index => $title) {
                            Grade::query()->create(['tenant_id' => $stage->tenant_id, 'stage_id' => $stage->getKey(), 'title' => $title, 'has_majors' => false, 'sort' => $index + 1]);
                        }
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::seventh($run) !== null && self::seventh($run)->next() !== null),
                Mission::make('curriculum', 'درس‌ها و برنامه‌ی درسی')
                    ->steps([
                        'در «کلاس‌ها → درس‌ها» درس‌های «ریاضی» و «علوم» را بسازید.',
                        'در «پایه‌ها و برنامه‌ی درسی» پایه‌ی «هفتم» را باز کنید و در «برنامه‌ی درسی» ریاضی (ضریب ۴، ۴ ساعت در هفته) و علوم (ضریب ۳، ۳ ساعت) را اضافه کنید.',
                        'کارنامه همین درس‌ها و ضریب‌ها را برای معدل به کار می‌برد.',
                    ])
                    ->url(fn (): string => SubjectResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->perform(function (TutorialRun $run): void {
                        foreach ([['ریاضی', 4, 4], ['علوم', 3, 3]] as $index => [$title, $coefficient, $hours]) {
                            $subject = Subject::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'title' => $title, 'is_active' => true]);
                            Curriculum::query()->create(['grade_id' => self::seventh($run)->getKey(), 'subject_id' => $subject->getKey(), 'coefficient' => $coefficient, 'weekly_hours' => $hours, 'sort' => $index + 1]);
                        }
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::math($run) !== null
                        && $run->made(Curriculum::class)->where('grade_id', self::seventh($run)->getKey())->count() >= 2),
                Mission::make('year', 'سال تحصیلی و تعطیلات')
                    ->steps([
                        'در «کلاس‌ها → دوره‌های آموزشی» دوره‌ی «سال تحصیلی ۱۴۰۵-۱۴۰۶» را از امروز تا نه ماه بعد بسازید.',
                        'در «کلاس‌ها → تعطیلات» (در «'.$primary.'»، که برای همه‌ی '.$tenants.' است) تعطیلی «تعطیل رسمی» را برای هفته‌ی بعد، همین روز، بسازید.',
                        'برنامه‌ی کلاس‌ها روزهای تعطیل را خودش رد می‌کند.',
                    ])
                    ->url(fn (): string => TermResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['title' => 'سال تحصیلی ۱۴۰۵-۱۴۰۶', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addMonths(9)->toDateString()])
                    ->perform(function (): void {
                        Term::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'title' => 'سال تحصیلی ۱۴۰۵-۱۴۰۶', 'starts_on' => today(), 'ends_on' => today()->addMonths(9), 'is_active' => true]);
                        Holiday::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'title' => 'تعطیل رسمی', 'starts_on' => today()->addWeek(), 'ends_on' => today()->addWeek()]);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::year($run) !== null && $run->made(Holiday::class)->exists()),
            ],
            'پیش‌ثبت‌نام و پذیرش' => array_values(array_filter([
                Mission::make('workflow', 'ساخت فرایند پذیرش')
                    ->steps(array_values(array_filter([
                        'در «کارتابل → فرایندها» دکمه‌ی «ایجاد» را بزنید؛ نام «پذیرش دانش‌آموز» و «برای» را «پیش‌ثبت‌نام» بگذارید.',
                        "یک مرحله‌ی «بررسی پرونده» بسازید: {$tenant} «دبیرستان نمونه»، مسئول «نقش»، نقش «کارشناس آموزش».",
                        $documents ? 'پیش از آن در «مدارک → انواع مدرک» نوع «شناسنامه» را بسازید و در همین مرحله، در «مدارکی که در این مرحله تأیید می‌شوند»، «شناسنامه» را بزنید.' : null,
                    ])))
                    ->url(fn (): string => WorkflowResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (): array => ['name' => 'پذیرش دانش‌آموز', 'subject_type' => (new Preregistration)->getMorphClass()])
                    ->perform(function (TutorialRun $run) use ($documents): void {
                        $type = $documents ? DocumentType::query()->create([
                            'tenant_id' => Tenant::primary()->getKey(), 'title' => 'شناسنامه', 'file_kind' => FileKind::ImageOrPdf, 'max_files' => 1, 'max_kilobytes' => 5120, 'is_active' => true,
                        ]) : null;

                        Workflow::query()->create([
                            'subject_type' => (new Preregistration)->getMorphClass(),
                            'name' => 'پذیرش دانش‌آموز',
                            'is_active' => true,
                            'steps' => [(new WorkflowStep(
                                'بررسی پرونده',
                                self::school($run)->getKey(),
                                AssigneeType::Role,
                                (int) self::roleIds(DefaultRoles::EDUCATION_OFFICER)[0],
                                null,
                                [],
                                $type ? ['documents' => ['upload' => [], 'verify' => [(string) $type->getKey()]]] : [],
                            ))->toArray()],
                        ]);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::admissionWorkflow($run) !== null && (! self::enabled('Document') || self::birthCertificate($run) !== null)),
                Mission::make('program', 'برنامه‌ی «پایه هفتم» و فرم آن')
                    ->steps(array_values(array_filter([
                        'در «پیش‌ثبت‌نام → برنامه‌های آموزشی» برنامه‌ی «پایه هفتم» را با فرایند «پذیرش دانش‌آموز» بسازید و فقط در «دبیرستان نمونه» ارائه دهید.',
                        'از منوی ردیف برنامه «فیلدهای فرم» را بزنید: «مشخصات پدر» و «موبایل ورود» را «الزامی» کنید.',
                        'یک فیلد تازه‌ی عددی «معدل سال قبل» اضافه کنید.',
                        $documents ? 'در بخش «مدارک»، «شناسنامه» را «الزامی» کنید و ذخیره کنید.' : 'ذخیره کنید.',
                    ])))
                    ->url(fn (): string => ProgramResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => ['title' => 'پایه هفتم', 'workflow_id' => self::admissionWorkflow($run)?->getKey(), 'all_tenants' => false, 'is_active' => true])
                    ->perform(function (TutorialRun $run): void {
                        $program = Program::query()->create([
                            'tenant_id' => Tenant::primary()->getKey(), 'workflow_id' => self::admissionWorkflow($run)->getKey(), 'title' => 'پایه هفتم', 'all_tenants' => false, 'is_active' => true,
                        ]);
                        $program->availableInTenants()->attach(self::school($run));
                        $modes = ['father' => 'required', 'login_mobile' => 'required'];

                        if ($type = self::birthCertificate($run)) {
                            $modes['document_'.$type->getKey()] = 'required';
                        }

                        $program->formCustomizations()->create([
                            'tenant_id' => $program->tenant_id,
                            'form' => PreregistrationForm::KEY,
                            'field_modes' => $modes,
                            'fields' => [['key' => self::AVERAGE_FIELD, 'label' => 'معدل سال قبل', 'type' => 'number', 'required' => false, 'options' => [], 'help' => null, 'section' => null]],
                        ]);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::programForm($run) !== null),
                Mission::make('preregistration', 'پیش‌ثبت‌نام از سایت')
                    ->actor('متقاضی (فرم عمومی سایت)')
                    ->steps(array_values(array_filter([
                        'فرم «پیش‌ثبت‌نام» سایت را باز کنید و برنامه‌ی «پایه هفتم» را انتخاب کنید.',
                        'نام «امیر حسینی» و کد ملی ۰۰۸۰۱۰۰۱۲۰ را بنویسید؛ موبایل خودتان را برای کد تأیید بنویسید.',
                        'مشخصات پدر: «محمد حسینی»، کد ملی ۰۰۹۰۱۱۰۱۳۷ و یک موبایل؛ «موبایل ورود» را «پدر» بگذارید و معدل سال قبل را بنویسید.',
                        $documents ? 'برای «شناسنامه» یک تصویر یا PDF بارگذاری کنید و فرم را بفرستید.' : 'فرم را بفرستید.',
                    ])))
                    ->url(fn (): string => route('preregister.create'), 'باز کردن فرم')
                    ->perform(fn (TutorialRun $run): mixed => Tenant::primary()->execute(function () use ($run): void {
                        $program = self::programForm($run)->owner;
                        $preregistration = Preregistration::query()->create([
                            'tenant_id' => $program->tenant_id,
                            'program_id' => $program->getKey(),
                            'program_title' => $program->title,
                            'preferred_tenant_id' => self::school($run)->getKey(),
                            'status' => PreregistrationStatus::Submitted,
                            'first_name' => 'امیر',
                            'last_name' => 'حسینی',
                            'father_name' => 'محمد',
                            'nationality' => Nationality::Iranian,
                            'national_code' => '0080100120',
                            'birth_date' => '2013-04-10',
                            'gender' => Gender::Male,
                            'mobile' => '09120000023',
                            'father' => ['name' => 'محمد حسینی', 'national_code' => '0090110137', 'mobile' => '09120000024'],
                            'login_mobile_from' => LoginMobileSource::Father,
                            'city' => 'تهران',
                            'custom_fields' => [['key' => self::AVERAGE_FIELD, 'label' => 'معدل سال قبل', 'type' => 'number', 'section' => null, 'value' => 18.75]],
                        ]);

                        if ($type = self::birthCertificate($run)) {
                            $path = 'tutorial/'.Str::random(16).'/birth-certificate.pdf';
                            Storage::disk('local')->put($path, self::SAMPLE_PDF);
                            app(Documents::class)->upload($preregistration, $type, [$path], null, null);
                        }

                        app(WorkflowEngine::class)->start($program->workflow, $preregistration);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => ($preregistration = self::preregistration($run)) !== null && filled($preregistration->father)
                        && (! self::enabled('Document') || self::birthCertificateUpload($run) !== null)),
                Mission::make('admission', 'بررسی و پذیرش')
                    ->actor('معاون آموزشی', fn (TutorialRun $run): ?User => self::officer($run))
                    ->steps(array_values(array_filter([
                        'با دکمه‌ی «ورود با حساب …» با حساب معاون آموزشی وارد شوید و «کارتابل من» را باز کنید.',
                        $documents ? '«بررسی مدارک» را بزنید و «شناسنامه» را تأیید کنید.' : null,
                        "«تأیید» را بزنید، {$tenant} «دبیرستان نمونه» و نقش «{$learner}» را بگذارید و تأیید کنید.",
                        'پدر خودش ولیِ دانش‌آموز می‌شود؛ در صفحه‌ی دانش‌آموز زبانه‌ی «اولیا» را ببینید. دانش‌آموز با موبایل پدر وارد می‌شود.',
                    ])))
                    ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, WorkflowCaseResource::class))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(function () use ($run): void {
                        if ($document = self::birthCertificateUpload($run)) {
                            app(Documents::class)->approve($document, self::officer($run));
                        }

                        app(WorkflowEngine::class)->approve(self::caseOf(self::preregistration($run)), self::officer($run), data: [
                            'tenant_id' => self::school($run)->getKey(),
                            'role_ids' => self::roleIds(DefaultRoles::LEARNER),
                            'enrolled_at' => today()->toDateString(),
                        ]);
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::student($run) !== null && $run->made(Guardianship::class)->exists()),
            ])),
            'کلاس و برنامه‌ی هفتگی' => [
                Mission::make('classroom', 'ساخت کلاس')
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید و '.$tenant.' را «دبیرستان نمونه» کنید.',
                        'در «کلاس‌ها → کلاس‌ها» کلاس «هفتم الف» را با دوره‌ی «سال تحصیلی ۱۴۰۵-۱۴۰۶»، پایه‌ی «هفتم»، نوع «حضوری» و ظرفیت ۳۰ بسازید.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, ClassroomResource::class))
                    ->fill(fn (TutorialRun $run): array => ['title' => 'هفتم الف', 'term_id' => self::year($run)?->getKey(), 'mode' => ClassMode::InPerson->value, 'capacity' => 30, 'school_grade_id' => self::seventh($run)?->getKey()])
                    ->perform(function (TutorialRun $run): void {
                        $classroom = Classroom::query()->create([
                            'tenant_id' => self::school($run)->getKey(), 'term_id' => self::year($run)->getKey(), 'title' => 'هفتم الف', 'mode' => ClassMode::InPerson, 'capacity' => 30, 'is_active' => true,
                        ]);
                        app(Enrollments::class)->setClassroomGrade($classroom, self::seventh($run)->getKey(), null);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::classroom($run) !== null),
                Mission::make('members', 'اعضای کلاس')
                    ->steps([
                        'کلاس «هفتم الف» را باز کنید و در زبانه‌ی «اعضا»، «امیر حسینی» را با نقش «'.$learner.'» و «نرگس مرادی» را با نقش «مدرس» اضافه کنید.',
                        'با پیوستن به کلاسِ پایه‌دار، «پرونده‌ی تحصیلی» دانش‌آموز برای این سال پر می‌شود (منوی «مدرسه → پرونده‌های تحصیلی»).',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classroomUrl($run))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(function () use ($run): void {
                        $roles = ClassRole::withoutTenantScope()->where('tenant_id', Tenant::primary()->getKey())->orderBy('sort')->get();
                        $instructorRoleIds = array_map('intval', self::roleIds(DefaultRoles::INSTRUCTOR));
                        $members = app(ClassMembers::class);

                        $members->add(self::classroom($run), $roles->firstOrFail('is_attendee', true), [self::student($run)->user_id]);
                        $members->add(
                            self::classroom($run),
                            $roles->first(fn (ClassRole $role): bool => ! $role->is_attendee && array_intersect($instructorRoleIds, $role->allowed_role_ids ?? []) !== [])
                                ?? $roles->firstOrFail(fn (ClassRole $role): bool => ! $role->is_attendee && $role->takes_attendance),
                            [self::teacher($run)->getKey()],
                        );
                    }))
                    ->doneWhen(fn (TutorialRun $run): bool => self::enrollment($run) !== null
                        && $run->made(ClassMember::class)->where('user_id', self::teacher($run)?->getKey())->exists()),
                Mission::make('plan', 'طرح درس')
                    ->steps([
                        $tenant.' را به «'.$primary.'» برگردانید و در «کلاس‌ها → طرح‌های درس» طرح «ریاضی هفتم» را برای درس «ریاضی» بسازید.',
                        'سه سرفصل به ترتیب: «اعداد صحیح»، «کسرها»، «معادله» (هر کدام یک جلسه).',
                    ])
                    ->url(fn (): string => TeachingPlanResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => ['title' => 'ریاضی هفتم', 'subject_id' => self::math($run)?->getKey()])
                    ->perform(function (TutorialRun $run): void {
                        $plan = TeachingPlan::query()->create(['tenant_id' => Tenant::primary()->getKey(), 'subject_id' => self::math($run)->getKey(), 'title' => 'ریاضی هفتم', 'is_active' => true]);

                        foreach (['اعداد صحیح', 'کسرها', 'معادله'] as $index => $title) {
                            $plan->topics()->create(['sort' => $index + 1, 'title' => $title, 'sessions' => 1]);
                        }
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::plan($run) !== null),
                Mission::make('schedule', 'برنامه‌ی هفتگی')
                    ->steps([
                        $tenant.' را «دبیرستان نمونه» کنید؛ در کلاس «هفتم الف» زبانه‌ی «برنامه» و «افزودن برنامه» را بزنید.',
                        'درس «ریاضی»، مدرس «نرگس مرادی»، طرح درس «ریاضی هفتم»، تکرار «هفتگی» در روز امروز، از امروز تا چهار هفته‌ی بعد، از ده دقیقه‌ی بعد به مدت یک ساعت.',
                        'در «جلسه‌ها» ببینید جلسه‌ی هفته‌ی بعد (تعطیل) ساخته نشده و هر جلسه سرفصلِ پیش‌بینی‌شده‌اش را دارد.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::classroomUrl($run))
                    ->perform(function (TutorialRun $run): void {
                        [$start, $end] = self::soon();

                        self::school($run)->execute(fn () => app(SessionPlanner::class)->saveSchedule(null, self::classroom($run), [
                            'frequency' => Frequency::Weekly,
                            'interval' => 1,
                            'weekdays' => [Weekday::from($start->dayOfWeek)],
                            'starts_on' => $start->toDateString(),
                            'ends_on' => $start->copy()->addWeeks(4)->toDateString(),
                            'start_time' => $start->format('H:i'),
                            'end_time' => $end->format('H:i'),
                            'subject_id' => self::math($run)->getKey(),
                            'teacher_id' => self::teacher($run)->getKey(),
                            'teaching_plan_id' => self::plan($run)->getKey(),
                        ]));
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::schedule($run) !== null && self::firstSession($run) !== null),
            ],
            'کلاس در جریان' => [
                Mission::make('attendance', 'حضور و غیاب و پیامک غیبت')
                    ->actor('معلم', fn (TutorialRun $run): ?User => self::teacher($run))
                    ->steps([
                        'با حساب معلم وارد شوید و در «کلاس‌های من» جلسه‌ی امروز را باز کنید.',
                        '«امیر حسینی» را «غایب» بزنید و ثبت کنید.',
                        'به ولی یک پیامک غیبت فرستاده می‌شود (در نصب آزمایشی در storage/logs/sms.log).',
                    ])
                    ->url(fn (): string => route('my-classes.index'))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(fn () => app(AttendanceRegister::class)->record(
                        self::firstSession($run),
                        [self::student($run)->user_id => ['status' => AttendanceStatus::Absent]],
                        self::teacher($run),
                    )))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Attendance::class)->where('status', AttendanceStatus::Absent)->exists()),
                Mission::make('content', 'مطالب جلسه و تکلیف')
                    ->actor('معلم', fn (TutorialRun $run): ?User => self::teacher($run))
                    ->steps([
                        'در همان جلسه «مطالب جلسه» را باز کنید: سرفصل «اعداد صحیح» را انجام‌شده بزنید و خلاصه‌ای بنویسید.',
                        'تکلیف «تمرین‌های صفحه‌ی ۱۲» با مهلت سه روز بعد بگذارید و ثبت کنید.',
                    ])
                    ->url(fn (): string => route('my-classes.index'))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(fn () => app(SessionContentBook::class)->record(self::firstSession($run), [
                        'covered_topic_ids' => [self::plan($run)->topics()->orderBy('sort')->value('id')],
                        'summary' => 'جمع و تفریق اعداد صحیح.',
                        'homework' => 'تمرین‌های صفحه‌ی ۱۲',
                        'homework_due_on' => today()->addDays(3)->toDateString(),
                    ], self::teacher($run))))
                    ->doneWhen(fn (TutorialRun $run): bool => filled(self::firstSession($run)?->homework)),
                Mission::make('hand-in', 'تحویل تکلیف')
                    ->actor('دانش‌آموز', fn (TutorialRun $run): ?User => self::student($run)?->user)
                    ->steps([
                        'با حساب «امیر حسینی» وارد شوید (او با موبایل پدرش وارد می‌شود).',
                        'در «کلاس‌های من» جلسه‌ی امروز را باز کنید و پاسخ تکلیف را بنویسید (یا فایلی بارگذاری کنید) و بفرستید.',
                    ])
                    ->url(fn (): string => route('my-classes.index'))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(fn () => app(HomeworkBook::class)->submit(self::firstSession($run), self::student($run)->user, 'پاسخ تمرین‌ها: ۱) ۵- ۲) ۳')))
                    ->doneWhen(fn (TutorialRun $run): bool => self::submission($run) !== null),
                Mission::make('homework-grade', 'نمره‌ی تکلیف')
                    ->actor('معلم', fn (TutorialRun $run): ?User => self::teacher($run))
                    ->steps([
                        'با حساب معلم وارد شوید؛ در جلسه‌ی امروز تکلیف امیر را ببینید و نمره (مثلاً ۱۸ از ۲۰) و بازخورد بدهید.',
                        'ولی هم در پنل کاربری خود «کلاس‌های امیر» را دارد: غیبت، تکلیف و نمره‌اش را می‌بیند.',
                    ])
                    ->url(fn (): string => route('my-classes.index'))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(fn () => app(HomeworkBook::class)->grade(
                        self::firstSession($run),
                        [self::submission($run)->getKey() => ['score' => 18, 'feedback' => 'خوب بود؛ تمرین ۳ را دوباره ببین.']],
                        self::teacher($run),
                    )))
                    ->doneWhen(fn (TutorialRun $run): bool => self::submission($run)?->score !== null),
            ],
            'نمره و کارنامه' => [
                Mission::make('score-period', 'دوره‌ی نمره‌دهی')
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید.',
                        'در «مدرسه → دوره‌های نمره‌دهی» دوره‌ی «نوبت اول» را برای «سال تحصیلی ۱۴۰۵-۱۴۰۶» از امروز تا دو ماه بعد بسازید؛ مهلت ثبت نمره‌ی معلم‌ها یک هفته‌ی بعد.',
                    ])
                    ->url(fn (): string => ScorePeriodResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->fill(fn (TutorialRun $run): array => [
                        'term_id' => self::year($run)?->getKey(), 'title' => 'نوبت اول', 'starts_on' => today()->toDateString(),
                        'ends_on' => today()->addMonths(2)->toDateString(), 'entry_closes_on' => today()->addWeek()->toDateString(),
                    ])
                    ->perform(fn (TutorialRun $run): ScorePeriod => ScorePeriod::query()->create([
                        'tenant_id' => Tenant::primary()->getKey(), 'term_id' => self::year($run)->getKey(), 'title' => 'نوبت اول',
                        'starts_on' => today(), 'ends_on' => today()->addMonths(2), 'entry_closes_on' => today()->addWeek(), 'is_published' => false,
                    ]))
                    ->doneWhen(fn (TutorialRun $run): bool => self::period($run) !== null),
                Mission::make('score-book', 'دفتر نمره')
                    ->actor('معلم', fn (TutorialRun $run): ?User => self::teacher($run))
                    ->steps([
                        'با حساب معلم وارد شوید و «دفتر نمره» را باز کنید.',
                        'کلاس «هفتم الف»، درس «ریاضی» و دوره‌ی «نوبت اول» را انتخاب کنید و برای امیر نمره‌ی ۱۸ بنویسید و ثبت کنید.',
                    ])
                    ->url(fn (): string => route('school.scores.index'))
                    ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(fn () => app(ScoreBook::class)->record(
                        self::classroom($run),
                        self::period($run),
                        self::math($run)->getKey(),
                        [self::student($run)->user_id => ['score' => 18]],
                        self::teacher($run),
                    )))
                    ->doneWhen(fn (TutorialRun $run): bool => $run->made(Score::class)->exists()),
                Mission::make('report-card', 'انتشار کارنامه')
                    ->steps([
                        'با «بازگشت به حساب خودم» برگردید و در «دوره‌های نمره‌دهی» برای «نوبت اول» «انتشار کارنامه» را بزنید.',
                        'حالا امیر و پدرش در پنل کاربری «کارنامه» را می‌بینند و PDF آن را می‌گیرند: درس‌ها با ضریب، معدل، غیبت و میانگین تکالیف.',
                    ])
                    ->url(fn (): string => ScorePeriodResource::getUrl('index', panel: 'admin', tenant: Tenant::primary()))
                    ->perform(fn (TutorialRun $run): bool => self::period($run)->update(['is_published' => true]))
                    ->doneWhen(fn (TutorialRun $run): bool => (bool) self::period($run)?->is_published),
                Mission::make('promotion', 'ارتقا به سال بعد')
                    ->steps([
                        'در «کلاس‌ها → دوره‌های آموزشی» سال بعد، «سال تحصیلی ۱۴۰۶-۱۴۰۷»، را بسازید.',
                        $tenant.' را «دبیرستان نمونه» کنید؛ در «مدرسه → پرونده‌های تحصیلی» پرونده‌ی امیر را انتخاب کنید و «ارتقا به سال بعد» را با همین سال بزنید.',
                        'پرونده‌ی تازه پایه‌ی «هشتم» را دارد؛ کلاسش بعداً انتخاب می‌شود.',
                    ])
                    ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, EnrollmentResource::class))
                    ->perform(function (TutorialRun $run): void {
                        $next = Term::query()->create([
                            'tenant_id' => Tenant::primary()->getKey(), 'title' => 'سال تحصیلی ۱۴۰۶-۱۴۰۷',
                            'starts_on' => self::year($run)->ends_on->copy()->addDay(), 'ends_on' => self::year($run)->ends_on->copy()->addYear(), 'is_active' => true,
                        ]);
                        app(Enrollments::class)->promote(collect([self::enrollment($run)]), $next);
                    })
                    ->doneWhen(fn (TutorialRun $run): bool => self::enrollment($run) !== null
                        && Enrollment::withoutTenantScope()->where('user_id', self::enrollment($run)->user_id)->where('term_id', '!=', self::year($run)->getKey())->exists()),
            ],
            'آزمون آنلاین' => self::enabled('Exam') ? self::examMissions($tenant, $learner) : null,
        ]);
    }

    /**
     * @return list<Mission>
     */
    private static function examMissions(string $tenant, string $learner): array
    {
        return [
            Mission::make('question-bank', 'بانک سؤال')
                ->steps([
                    $tenant.' را «دبیرستان نمونه» کنید؛ در «آزمون → بانک‌های سؤال» بانک «ریاضی هفتم» را بسازید.',
                    'در «آزمون → سؤال‌ها» سه سؤال چهارگزینه‌ای (با یک گزینه‌ی درست) و یک سؤال تشریحی، هر کدام با بارم یک، بسازید و برای هر کدام بانک «ریاضی هفتم» را بزنید.',
                ])
                ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, QuestionBankResource::class))
                ->fill(fn (): array => ['title' => 'ریاضی هفتم'])
                ->perform(function (TutorialRun $run): void {
                    $tenantId = self::school($run)->getKey();
                    $bank = QuestionBank::query()->create(['tenant_id' => $tenantId, 'title' => 'ریاضی هفتم']);
                    $choice = fn (string $body, array $options, int $correct): Question => Question::query()->create([
                        'tenant_id' => $tenantId, 'type' => QuestionType::Choice, 'body' => $body, 'score' => 1, 'is_active' => true,
                        'options' => array_map(fn (string $text, int $index): array => ['text' => $text, 'is_correct' => $index === $correct], $options, array_keys($options)),
                    ]);

                    $bank->questions()->attach([
                        $choice('حاصل ۳- + ۵ چند است؟', ['۲', '۸', '۲-', '۸-'], 0)->getKey(),
                        $choice('کدام عدد صحیح منفی است؟', ['۰', '۷', '۴-', '۱'], 2)->getKey(),
                        $choice('نصف ۱۲ چند است؟', ['۴', '۶', '۸', '۲۴'], 1)->getKey(),
                        Question::query()->create(['tenant_id' => $tenantId, 'type' => QuestionType::Descriptive, 'body' => 'معادله‌ی x + 4 = 9 را حل کنید.', 'score' => 1, 'is_active' => true])->getKey(),
                    ]);
                })
                ->doneWhen(fn (TutorialRun $run): bool => self::bank($run) !== null),
            Mission::make('exam', 'ساخت آزمون')
                ->steps([
                    'در «آزمون → آزمون‌ها» آزمون «آزمونک ریاضی» را بسازید: از امروز تا یک ساعت بعد، ۱۵ دقیقه، از ۲۰ نمره، نمره‌ی قبولی ۱۰.',
                    'در بخش سؤال‌ها ۴ سؤال از بانک «ریاضی هفتم» بکشید؛ شرکت‌کننده‌ها: نقش «'.$learner.'»؛ آزمون را منتشر کنید.',
                ])
                ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, ExamResource::class))
                ->fill(fn (): array => ['title' => 'آزمونک ریاضی', 'duration_minutes' => 15, 'total_score' => 20, 'passing_score' => 10])
                ->perform(function (TutorialRun $run): void {
                    $exam = Exam::query()->create([
                        'tenant_id' => self::school($run)->getKey(), 'title' => 'آزمونک ریاضی', 'is_published' => true,
                        'starts_at' => now(), 'ends_at' => now()->addHour(), 'duration_minutes' => 15, 'grace_minutes' => 2,
                        'total_score' => 20, 'passing_score' => 10, 'max_attempts' => 1, 'retake_policy' => RetakePolicy::Always,
                        'questions_per_page' => 4, 'result_visibility' => ResultVisibility::ScoreWithAnswers,
                    ]);
                    $exam->sections()->create(['question_bank_id' => self::bank($run)->getKey(), 'question_count' => 4, 'sort' => 1]);
                    $exam->participantRoles()->attach(self::roleIds(DefaultRoles::LEARNER));
                })
                ->doneWhen(fn (TutorialRun $run): bool => self::exam($run) !== null),
            Mission::make('take-exam', 'شرکت در آزمون')
                ->actor('دانش‌آموز', fn (TutorialRun $run): ?User => self::student($run)?->user)
                ->steps([
                    'با حساب «امیر حسینی» وارد شوید و از «آزمون‌ها» در پنل کاربری، «آزمونک ریاضی» را شروع کنید.',
                    'به سؤال‌ها پاسخ دهید (پاسخ‌ها خودکار ذخیره می‌شوند) و آزمون را تمام کنید.',
                ])
                ->url(fn (): string => route('exams.index'))
                ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(function () use ($run): void {
                    $taking = app(ExamTaking::class);
                    $attempt = $taking->start(self::exam($run), self::student($run)->user);
                    $taking->submit($attempt, $attempt->answers()->get()->mapWithKeys(fn (AttemptAnswer $answer): array => [
                        $answer->getKey() => $answer->isChoice() ? $answer->correct_option : 'x = 5',
                    ])->all());
                }))
                ->doneWhen(fn (TutorialRun $run): bool => self::attempt($run)?->submitted_at !== null),
            Mission::make('grade-exam', 'تصحیح پاسخ تشریحی')
                ->steps([
                    'با «بازگشت به حساب خودم» برگردید؛ در «آزمون → نتایج آزمون‌ها» شرکت امیر را باز کنید.',
                    'به پاسخ تشریحی نمره بدهید و ذخیره کنید؛ نمره‌ی کل حساب می‌شود و امیر نتیجه را می‌بیند.',
                ])
                ->url(fn (TutorialRun $run): ?string => self::schoolUrl($run, ExamAttemptResource::class))
                ->perform(fn (TutorialRun $run): mixed => self::school($run)->execute(function () use ($run): void {
                    $attempt = self::attempt($run);
                    $attempt->answers()->get()->reject->isChoice()->each(fn (AttemptAnswer $answer): bool => $answer->update(['score' => $answer->max_score, 'graded_at' => now()]));
                    $attempt->recalculate();
                }))
                ->doneWhen(fn (TutorialRun $run): bool => self::attempt($run)?->status === AttemptStatus::Completed),
        ];
    }

    /**
     * Ten minutes from now for an hour, within today: the teacher may take
     * attendance from a little before a session starts.
     *
     * @return array{CarbonInterface, CarbonInterface}
     */
    private static function soon(): array
    {
        $start = now()->addMinutes(10)->startOfMinute();

        return [$start, $start->copy()->addHour()->min($start->copy()->setTime(23, 59))];
    }

    private static function registerEmployee(TutorialRun $run, User $owner, string $name, string $nationalCode, string $mobile, string $jobTitle, string $role, bool $panelAccess): mixed
    {
        return app(RegisterEmployee::class)->register(
            $owner,
            self::school($run),
            person: ['name' => $name, 'national_code' => $nationalCode, 'mobile' => $mobile],
            employment: ['job_title' => $jobTitle, 'employment_type' => EmploymentType::FullTime, 'hired_at' => today()],
            roleIds: self::roleIds($role),
            panelAccess: $panelAccess,
        );
    }

    private static function school(TutorialRun $run): ?Tenant
    {
        return $run->made(Tenant::class)->oldest('id')->first();
    }

    /**
     * @param  class-string  $resource
     */
    private static function schoolUrl(TutorialRun $run, string $resource): ?string
    {
        return ($school = self::school($run)) ? $resource::getUrl('index', panel: 'admin', tenant: $school) : null;
    }

    private static function officer(TutorialRun $run): ?User
    {
        return self::employeeIn($run, self::school($run), DefaultRoles::EDUCATION_OFFICER, withPanel: true);
    }

    private static function teacher(TutorialRun $run): ?User
    {
        return self::employeeIn($run, self::school($run), DefaultRoles::INSTRUCTOR);
    }

    /**
     * The first grade of a stage made in the run, with a numeric scale.
     */
    private static function seventh(TutorialRun $run): ?Grade
    {
        return $run->made(Grade::class)->whereIn('stage_id', $run->made(Stage::class)->select('id'))->orderBy('sort')->orderBy('id')->first();
    }

    private static function math(TutorialRun $run): ?Subject
    {
        $grade = self::seventh($run);

        return $grade ? $run->made(Subject::class)->whereIn('id', Curriculum::query()->where('grade_id', $grade->getKey())->select('subject_id'))->oldest('id')->first() : null;
    }

    private static function year(TutorialRun $run): ?Term
    {
        return $run->made(Term::class)->oldest('id')->first();
    }

    private static function admissionWorkflow(TutorialRun $run): ?Workflow
    {
        return $run->made(Workflow::class)->where('subject_type', (new Preregistration)->getMorphClass())->get()
            ->first(fn (Workflow $workflow): bool => $workflow->stepList() !== []);
    }

    private static function birthCertificate(TutorialRun $run): ?DocumentType
    {
        return self::enabled('Document') ? $run->made(DocumentType::class)->where('is_active', true)->oldest('id')->first() : null;
    }

    /**
     * The program's own form setup, asking for the father (and, with
     * documents, the birth certificate).
     */
    private static function programForm(TutorialRun $run): ?FormCustomization
    {
        $workflow = self::admissionWorkflow($run);
        $type = self::birthCertificate($run);
        $program = $workflow ? $run->made(Program::class)->where('is_active', true)->where('workflow_id', $workflow->getKey())->oldest('id')->first() : null;

        return $program ? FormCustomization::withoutTenantScope()->where('owner_type', $program->getMorphClass())->where('owner_id', $program->getKey())
            ->where('form', PreregistrationForm::KEY)->get()
            ->first(fn (FormCustomization $form): bool => in_array($form->field_modes['father'] ?? null, ['required', 'optional'], true)
                && ($type === null || ($form->field_modes['document_'.$type->getKey()] ?? null) === 'required')) : null;
    }

    private static function preregistration(TutorialRun $run): ?Preregistration
    {
        return $run->made(Preregistration::class)->oldest('id')->first();
    }

    private static function birthCertificateUpload(TutorialRun $run): ?Document
    {
        $preregistration = self::preregistration($run);
        $type = self::birthCertificate($run);

        return $preregistration && $type ? $run->made(Document::class)->where('document_type_id', $type->getKey())
            ->where('documentable_type', $preregistration->getMorphClass())->where('documentable_id', $preregistration->getKey())
            ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Approved])->first() : null;
    }

    private static function student(TutorialRun $run): ?Student
    {
        $school = self::school($run);

        return $school ? $run->made(Student::class)->where('tenant_id', $school->getKey())->with('user')->oldest('id')->first() : null;
    }

    private static function classroom(TutorialRun $run): ?Classroom
    {
        $school = self::school($run);
        $grade = self::seventh($run);

        return $school && $grade ? $run->made(Classroom::class)->where('tenant_id', $school->getKey())
            ->whereIn('id', ClassroomGrade::withoutTenantScope()->where('grade_id', $grade->getKey())->select('classroom_id'))->oldest('id')->first() : null;
    }

    private static function classroomUrl(TutorialRun $run): ?string
    {
        $classroom = self::classroom($run);

        return $classroom ? ClassroomResource::getUrl('view', ['record' => $classroom], panel: 'admin', tenant: self::school($run)) : null;
    }

    private static function enrollment(TutorialRun $run): ?Enrollment
    {
        $student = self::student($run);
        $classroom = self::classroom($run);

        return $student && $classroom ? Enrollment::withoutTenantScope()->where('user_id', $student->user_id)->where('classroom_id', $classroom->getKey())->first() : null;
    }

    private static function plan(TutorialRun $run): ?TeachingPlan
    {
        $math = self::math($run);

        return $math ? $run->made(TeachingPlan::class)->where('subject_id', $math->getKey())->has('topics', '>=', 2)->oldest('id')->first() : null;
    }

    private static function schedule(TutorialRun $run): ?ClassSchedule
    {
        $classroom = self::classroom($run);

        return $classroom ? $run->made(ClassSchedule::class)->where('classroom_id', $classroom->getKey())
            ->where('frequency', Frequency::Weekly)->where('subject_id', self::math($run)?->getKey())
            ->where('teacher_id', self::teacher($run)?->getKey())->oldest('id')->first() : null;
    }

    private static function firstSession(TutorialRun $run): ?ClassSession
    {
        $schedule = self::schedule($run);

        return $schedule ? $run->made(ClassSession::class)->where('schedule_id', $schedule->getKey())->oldest('starts_at')->first() : null;
    }

    private static function submission(TutorialRun $run): ?HomeworkSubmission
    {
        $session = self::firstSession($run);

        return $session ? $run->made(HomeworkSubmission::class)->where('session_id', $session->getKey())->first() : null;
    }

    private static function period(TutorialRun $run): ?ScorePeriod
    {
        $year = self::year($run);

        return $year ? $run->made(ScorePeriod::class)->where('term_id', $year->getKey())->oldest('id')->first() : null;
    }

    private static function bank(TutorialRun $run): ?QuestionBank
    {
        return $run->made(QuestionBank::class)->get()
            ->first(fn (QuestionBank $bank): bool => $bank->questions()->withoutGlobalScopes()->count() >= 2
                && $bank->questions()->withoutGlobalScopes()->where('type', QuestionType::Descriptive)->exists());
    }

    private static function exam(TutorialRun $run): ?Exam
    {
        $bank = self::bank($run);

        return $bank ? $run->made(Exam::class)->where('is_published', true)
            ->whereHas('sections', fn ($sections) => $sections->where('question_bank_id', $bank->getKey()))->oldest('id')->first() : null;
    }

    private static function attempt(TutorialRun $run): ?ExamAttempt
    {
        $exam = self::exam($run);

        return $exam ? $run->made(ExamAttempt::class)->where('exam_id', $exam->getKey())->oldest('id')->first() : null;
    }
}
