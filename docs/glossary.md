# Glossary

Domain terms used in this project. Include the Persian term when the UI or users use it.

## Domains
| Term | Persian | Meaning |
|---|---|---|
| LMS | سامانه مدیریت یادگیری | Learning Management System: courses, content, exams, learner progress. Also the name of the legacy project at `../LMS`. |
| TMS | سامانه مدیریت آموزش | Training Management System: planning and running training programs, trainers, schedules, enrollments. |
| SIS | سامانه اطلاعات دانش‌آموزی | Student Information System: students, classes, attendance, grades, report cards, parents. |

## Organization & school structure
| Term | Persian | Meaning |
|---|---|---|
| Saraye Danesh | سرای دانش | The school chain that uses the system; "sarayedanesh" is also the name of the original Laravel 6 system. |
| Head office | ستاد | Central office over all schools (roles `main_office_*`). |
| Tenant | مدرسه / شعبه / واحد | One organizational unit of the installation: a school, a branch or a department ([ADR 0002](decisions/0002-tenancy-model.md)). The UI label is configurable per installation. Head office is a tenant too. Replaces LMS's instance + school. |
| Instance | | (legacy LMS) A site in LMS's multi-site setup. Each school branch is one instance; most data has `instance_id`. Maps to Tenant. |
| School | مدرسه | (legacy LMS) `schl_schools`, tied to an instance. In SaraSystem a school is simply a Tenant. |
| Academic year | سال تحصیلی | `schl_years`, from about 1 Shahrivar to 31 Mordad. Most operational data is year-scoped. |
| Grade | پایه | Node in the grade tree: stage (دبستان، متوسطه اول، متوسطه دوم) → grade (هفتم…), with a major for upper secondary (تجربی، ریاضی، انسانی). (legacy LMS; school-only, so not built into SaraSystem's learners: a school offers grades as programs, ADR 0018.) |
| Shared / optional | مشترک | Grades or classes shared across grades/majors; a student can have optional classes besides the main class. |
| Classroom | کلاس | (legacy LMS) A class of students in a school/grade/year (`schl_classrooms`). In SaraSystem a class is generic; see Classes below. |
| Classroom plan | برنامه کلاس | A course (subject) taught in a classroom by a teacher (+ supporter), possibly shared with other classrooms. |
| Weekly schedule | برنامه هفتگی | Recurring time slots of classroom plans. |
| Activity / activity session | فعالیت / جلسه | Teaching activity and its individual sessions (often online classes via Adobe Connect), with attendance and homework. |
| Supporter | پشتیبان | Staff member who follows up on students for a course/class. |
| Left student | دانش‌آموز انصرافی | A student who left the school mid-year. |
| Advising time | وقت مشاوره | Booked counselling/advising slots. |
| Report card | کارنامه | Term/year result of a student; lesson scores feed it. |
| Exam balance | تراز آزمون | Normalized/weighted exam score per balance type. |

## Finance & admin
| Term | Persian | Meaning |
|---|---|---|
| Main contract | قرارداد اصلی / شهریه | Student tuition contract (no `parent_id`). |
| Supplementary contract | قرارداد تکمیلی | Extra contract attached to a main contract (`parent_id` set). |
| School service | خدمات مدرسه | Items/services sold to students (books, packages), requested via service requests. |
| Leave | مرخصی | Staff leave request, approved through leave groups/operators. |
| National code | کد ملی | 10-digit Iranian national ID with a check digit; a login identifier (`users.national_code`, unique; up to 12 characters so a foreign national's کد اتباع fits). |
| Login identifier | شناسه‌ی ورود | Mobile, national code or email, typed in one field on `/login` (ADR 0004). |
| OTP / login code | کد یکبارمصرف | 6-digit SMS code for logging in without a password. |
| Staff / panel user | کارمند | A user holding the `panel_user` role in a tenant; only staff (and super admins) can enter the admin panel, and only for their tenants. |
| Super admin | مدیر کل سیستم | Holder of the global `super_admin` role (in any tenant): installation-wide, bypasses all permission checks, manages tenants and role definitions. |
| Primary tenant | شعبه‌ی اصلی | The one tenant whose public home page is the site root `/` (ADR 0009); chosen by a super admin. |
| Site page | صفحه | A public page of one tenant's site at a path such as `/about-us` (`Site` module, ADR 0010); the page at `/` is the tenant's home page, which every tenant has. Its content will be made of widgets. |
| Widget | ویجت | A building block of a site page's content (HTML, …), placed in order with the page builder (ADR 0011). Not the same as a Filament dashboard widget. |
| Site menu | منو | A named tree of links of one tenant's site (to its pages, to any address, or a title over a submenu), made in «وب‌سایت → منوها» and chosen per tenant in the tenants list («منوی سایت»), shown in that tenant's site header (ADR 0012). |
| Page builder | صفحه‌ساز | The admin screen where a site page's widgets are added and ordered (`pages/{id}/builder`, ADR 0011). |
| Impersonation | ورود با حساب کاربر | A super admin temporarily logged in as another user, with a way back to their own account (ADR 0008). |
| Cartable | کارتابل | Approval inbox: records waiting for a user's review. In LMS routed through entity groups and verifiers; in SaraSystem «کارتابل من» lists the workflow cases whose current step is the user's (ADR 0016). |
| Workflow | فرایند | An approval process designed in the admin panel: ordered steps, each sent to a role or a person in a tenant, with the actions allowed there (ADR 0016). Hiring uses one per job position. |
| Customizable form | تنظیم فرم / فیلدهای فرم | A form each organization sets up for itself (ADR 0017): built-in fields required / optional / not asked («پرسیده نشود»), plus **added fields** («فیلدهای اضافه») of its own; for the whole form and per record (e.g. per job position). |
| Workflow case | پرونده | One record (e.g. a job application) going through a workflow; it has a current step, an assignee and a history. |
| Claim | برداشتن کار | Taking a case so it leaves the cartable of the step role's other holders; release gives it back. |
| Employee | کارمند | A person employed in a tenant (`employees`): personnel code, employment type, status, dates; created by the last approval of a hiring workflow. Not the same as "staff" (panel access). |
| Entity group | گروه | Group of users used for routing approvals (verifiers) and access. |
| Block | بلوک / ویجت | A configurable widget placed in theme regions (header, sidebar, …) per route and instance. |

## Exams (`Exam` module, [ADR 0013](decisions/0013-exams.md))
| Term | Persian | Meaning |
|---|---|---|
| Question bank | بانک سؤال | A named set of questions of one tenant. A question can be in several banks. |
| Choice / written question | چندگزینه‌ای / تشریحی | A choice question has options with exactly one correct and is graded automatically; a written (descriptive) answer is graded by staff. |
| Exam section | بخش آزمون | One row of an exam's «سؤال‌ها»: take N active questions from one bank when an attempt starts. |
| Participant | شرکت‌کننده | Who may take an exam: users chosen by name, or holders of a chosen role in the exam's tenant. |
| Attempt | شرکت در آزمون | One sitting of an exam by a user. It keeps a copy of the questions as they were served; its score is set once every answer is graded. |
| Grace time | زمان ارفاق | Minutes after an attempt's deadline during which answers are still accepted (slow connections). After it the attempt closes with its saved answers. |
| Negative marking | نمره‌ی منفی | Every N wrong choice answers cancel one right one (a wrong answer costs 1/N of its question's score). |

## Hiring and admission
| Term | Persian | Meaning |
|---|---|---|
| Job application | درخواست همکاری / متقاضی همکاری | What someone sends through the public «همکاری با ما» form (`JobApplication`, [ADR 0014](decisions/0014-job-applications.md)). Open until it is rejected or the applicant is hired; one open application per national code. LMS called it «پیش ثبت نام کارمند» (employee preregister). |
| Job position | موقعیت شغلی | A job offered in the «همکاری با ما» form (`JobPosition`). In LMS these were the school admin roles. |
| Foreign national code | کد اتباع | 12-digit identifier of a foreign national, used instead of the کد ملی. |
| Learner | فراگیر (دانش‌آموز / دانشجو / هنرآموز / کارآموز) | A person or role put into classes so that education happens: a pupil, a university student, an institute's trainee or an employee in training (`Student` module, `students`, [ADR 0018](decisions/0018-student-preregistration.md)). One row per tenant: learner code, status, dates. Called as the installation says (`LEARNER_LABEL`). |
| Program | برنامه‌ی آموزشی | What people preregister for, defined by the organization: «پایه هفتم», «کارشناسی کامپیوتر», «زبان انگلیسی - مقدماتی», «دوره‌ی بدو خدمت» (`Program`). Each has an optional admission workflow and its own form setup. |
| Preregistration | پیش‌ثبت‌نام / متقاضی ثبت‌نام | What someone sends through the public «پیش‌ثبت‌نام» form for a program (`Preregistration`). Open until it is rejected or the applicant is admitted; one open preregistration per national code. |
| Admission | پذیرش | The workflow a preregistration goes through; its last approval creates the user and the learner row in the chosen tenant. |
| Document type | نوع مدرک | A kind of document people have (کارت ملی، شناسنامه، مدرک تحصیلی…), defined once in the head office with who it is for, who uploads and who checks it, and for whom it is required ([ADR 0019](decisions/0019-documents.md)). |
| Document | مدرک | One person's files of one document type, pending / approved / rejected, maybe with an expiry date. Attached to the applicant record, then to the employee / learner. |
| Document holder | دارنده‌ی مدرک | A kind of person who has documents (employee, learner), described by its module to the core (`DocumentHolder`). |
| Contract type | نوع قرارداد | A kind of contract (قرارداد کارمند تمام‌وقت، قرارداد شهریه…), defined once in the head office: for whom, its text with merge tags, its fields and how it is signed ([ADR 0020](decisions/0020-contracts.md)). |
| Contract | قرارداد | One person's contract of a type: a draft (پیش‌نویس) while being prepared, issued (صادر شده / در انتظار امضا) once its text is fixed and numbered, then signed (امضا شده) or cancelled (لغو شده). In LMS a student's main contract was the tuition contract. |
| Amendment | الحاقیه | A contract added to a signed contract to change some of its terms, made with a contract type marked «الحاقیه است»; numbered after its main contract, e.g. `K-12/1` ([ADR 0021](decisions/0021-contract-amendments-renewals-reminders.md)). Not the same as LMS's supplementary contract (قرارداد تکمیلی), which was a second tuition contract. |
| Renewal | تمدید | A new contract for the period after a signed contract, its terms filled from it; at most one per contract unless cancelled (ADR 0021). |
| Contract party | طرف قرارداد | A kind of person who signs contracts (employee, learner), described by its module to the core (`ContractParty`). |
| Merge tag | برچسب | A placeholder in a contract text, e.g. «نام و نام خانوادگی», replaced by the contract's value when it is issued; blank values print as dots to fill in by hand. |
| Electronic signature | امضای الکترونیکی | Accepting an issued contract in the user panel with an SMS code sent to one's own mobile; time, mobile and IP are kept. Not a certificate-based digital signature. |
| Step extension | تنظیمات اضافه‌ی مرحله | Work a module adds to workflow steps, e.g. documents to upload or check, or contracts to prepare, before the step can be approved (`StepExtension`, ADR 0019, ADR 0020). |

## Classes (`Class` module, [ADR 0022](decisions/0022-classes.md))
| Term | Persian | Meaning |
|---|---|---|
| Class | کلاس | A group of people of one tenant who meet on a schedule: «هفتم الف», «زبان انگلیسی - مقدماتی ۲», «دوره‌ی ایمنی کارگاه» (`Classroom`). It is in person, online (a meeting link) or blended, and may have a capacity. |
| Sub-class | زیرکلاس | A class under another class (one level), e.g. a lab group; its attendees come from the parent class. |
| Term | دوره‌ی آموزشی | A teaching period of the organization (سال تحصیلی، ترم، دوره), defined in the head office; classes run in one. A school's academic year is a term. |
| Class role | نقش کلاس | What a person is in a class (فراگیر، مدرس، ناظر، پشتیبان…), separate from system roles; its flags say whether its members are attendees, take attendance or record session content. |
| Attendee | شرکت‌کننده | A class member whose class role is marked «شرکت‌کننده است»: attendance is taken for them and they fill the capacity. |
| Class member | عضو کلاس | A user in a class with a class role, from a join date to a leave date (`ClassMember`). |
| Holiday | تعطیلی | Days without classes; the head office's are the whole organization's, a tenant's are its own. |
| Subject | درس | What a schedule teaches (ریاضی، Speaking، ایمنی کار), defined in the head office; optional ([ADR 0023](decisions/0023-class-schedules-and-sessions.md)). |
| Schedule | برنامه‌ی کلاس | When a class meets: once, daily, weekly or monthly (Jalali months), every N, between two dates, at a time, optionally with a subject, a teacher and a place (`ClassSchedule`). Replaces LMS's classroom plan + weekly schedule. |
| Session | جلسه | One meeting of a class (`ClassSession`), built from a schedule or added by hand; scheduled, held or cancelled. Replaces LMS's activity session. |
| Make-up / extra session | جلسه‌ی جبرانی / فوق‌العاده | A session added by hand: to make up a cancelled one, or in addition to the schedule. |
| Substitute teacher | مدرس جایگزین | Who teaches one session instead of its teacher. |
| Clash | تداخل | Two sessions at overlapping times with the same teacher (in any tenant), the same class or the same place (in the tenant); refused. |
| Attendance | حضور و غیاب | Whether each attendee was at a session: present (حاضر), late (تأخیر, with minutes), absent (غایب) or excused (غیبت موجه) ([ADR 0024](decisions/0024-class-attendance.md)). Taken by the session's teacher or substitute and members whose class role takes attendance; taking it marks the session held (برگزارشده). |
| Teaching plan | طرح درس | A course outline: topics in order, each taking some sessions (`TeachingPlan`), defined in the head office; a schedule follows one ([ADR 0025](decisions/0025-teaching-plans-and-session-content.md)). |
| Topic | سرفصل | One item of a teaching plan (`PlanTopic`); finished when a session records it. Unfinished topics are forecast over the coming sessions. |
| Session content | مطالب جلسه | What a session covered: topics finished, a summary, homework (تکلیف) with a due date, and materials (files). |
