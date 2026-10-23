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
| Grade | پایه | Node in the grade tree: stage (دبستان، متوسطه اول، متوسطه دوم) → grade (هفتم…), with a major for upper secondary (تجربی، ریاضی، انسانی). |
| Shared / optional | مشترک | Grades or classes shared across grades/majors; a student can have optional classes besides the main class. |
| Classroom | کلاس | A class of students in a school/grade/year (`schl_classrooms`). |
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
| National code | کد ملی | 10-digit Iranian national ID with a check digit; a login identifier (`users.national_code`, unique). |
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
| Cartable | کارتابل | Approval inbox/workflow: records waiting for a user's review, routed through entity groups and verifiers. |
| Entity group | گروه | Group of users used for routing approvals (verifiers) and access. |
| Block | بلوک / ویجت | A configurable widget placed in theme regions (header, sidebar, …) per route and instance. |
