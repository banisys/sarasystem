# Legacy LMS (reference project)

SaraSystem is a redesign of the existing **LMS** project. LMS always lives at:

```
/home/ali/Projects/LMS      (sibling of this repo: ../LMS)
```

Use it as the reference for existing behaviour, data model and business rules. **Do not modify LMS** unless the user explicitly asks; treat it as read-only source material.

## History
- **sarayedanesh** — the original system of the Saraye Danesh (سرای دانش) school chain, built on Laravel 6. LMS code comments call it "legacy sarayedanesh".
- **cafeerent** — an older Laravel 6 codebase (car rental / tourism) that sarayedanesh grew out of. Many dead tables and roles come from it (see "Dead weight" below).
- **LMS** — the current port to Laravel 13 + Filament 5. It runs on the **live production database** imported from the old system. Most tables have no migrations; the schema is defined by the dump.

## Stack (LMS)
- PHP 8.3, Laravel 13, Filament 5 (admin panel at `/cpadmin`, brand "سامانه مدیریت یادگیری"), `nwidart/laravel-modules`
- MySQL 8.4 (Sail containers `lms-mysql-1`, `lms-laravel.test-1`, `lms-phpmyadmin-1`)
- `ariaieboy/filament-jalali` (Jalali dates), `maatwebsite/excel`, `tymon/jwt-auth` (API)
- Tailwind 4 + Vite; Persian (`lang/fa`) and English (`lang/en`); admin is RTL
- Runs on Laravel Sail (Docker). The containers have **no internet access**, so the user installs packages manually.
- Native theme system (`View::addNamespace` per theme, `public/themes`), documented in `../LMS/docs/theme-system.md`
- Deployment notes: `../LMS/docs/deploy.md` (dump/import, broken legacy views `vw_payments*`)

## Key architectural concepts in LMS
- **Instance (multi-tenant):** the `instances` table holds about 34 instances. Each school branch is an instance (`marzdaran1`, `resalat3`, `hafez1`, `saadatabad2`, `tizhoshan`, `saratech`, …). There are also system instances (`www`, `cpadmin`, `api`, `admin`) and dead ones (`kish`, `turkey`, `dubai`, `tourism`, …). Most tables carry an `instance_id`.
- **Instance detection:** the `getInstanceObject` singleton detects the instance from the request (`Core\Http\Middleware\Loader`, `App\Http\Middleware\RefreshInstanceContext`). Admins switch instance, year and school from the topbar switchers. Data is then scoped by the current instance and the current academic year.
- **Access:** `BridgeHelper::getAccess()` checks roles (`roles`, `user_roles`), permissions (`permissions`, `role_permissions`) and per-record `model_permissions`. Admins can work only in instances they are allowed (`getAdminInstancesId()`).
- **Cartable (کارتابل):** a generic approval workflow. A record (leave, contract, …) enters a cartable tied to an **entity group**. The group's verifiers act on it in order, and every step is logged in `cartable_records`.
- **Blocks / widgets:** configurable front-end widgets (menu, slider, html, schema.org blocks, …) placed per route and per instance (`instance_route_blocks`, `blocks`). Each block has a `*Block` + `*BlockConfig` class pair.
- **Features / MetaGroups:** an EAV-style system for extra fields on users, courses, forms, etc.
- **Form builder:** dynamic forms (`forms`, `form_records`, `form_feature_value_relations`).
- **ViewModels:** a legacy request/response pattern (`*ViewModel`) carried over from the old system. New admin screens are Filament Resources instead.
- **Bridge:** cross-module helpers (`Modules/*/app/Bridge`, `BridgeHelper`) so that modules call each other through a facade.
- **Admin panel:** Filament discovers resources from every enabled module (`AdminPanelProvider::panelModules()`). The sidebar mirrors the old system (`LegacyNavigation`). There are panel-wide defaults: toggleable columns, LTR numeric inputs with thousand separators, and filter dropdowns.

## Modules
Sizes are approximate PHP file counts. "Live data" shows how much the module is actually used in production, as approximate row counts.

| Module | Size | What it covers | Live data |
|---|---|---|---|
| Core | ~890 | Platform infra: instances, menus, pages, blocks, themes, templates, form builder, features/meta groups, media, languages, currencies, locations, sitemap, cartable, entity groups, notifications, SMS | cartables 10k, notifications 30k, menus 1.4k |
| School | ~440 | **SIS core**: schools, academic years, grades, classrooms, students, classroom plans (teacher + course + class), weekly schedules, activities & activity sessions (online classes via Adobe Connect), attendance, homework, report cards, lesson scores, school services (books/packages sold to students), left students, pre-registration, advising times, question-design correction | students 11.6k, classrooms 2.7k, activity sessions 186k, lesson scores 31k, weekly schedules 11k, advising times 10k |
| User | ~325 | Users, roles/permissions, parents, addresses, contacts, education history, credit, **staff leave** (requests, groups, operators, status flow) | users 50k, user_roles 66k, leaves 22k, leave_status 47k |
| Contract | ~70 | Contracts for students (tuition), extracurricular programs, summer, special services, employees, teachers and service staff. Includes offers, invoices, tracking logs and pre-contracts. | contracts 16k, track logs 69k, invoices 3.5k |
| Sale | ~135 | Orders, payments, payment funds, share payments, cart, vouchers, affiliates, owners (revenue share) | orders 16k, order items 81k, payments 48k |
| Exam | ~65 | Online exams: banks, questions, attempts, answers, exam balance (score weighting). Live data is in the `schl_exam_*` tables; the newer `exam_*` tables are almost empty. | schl_exam_attempts 240k |
| Employee | ~35 | School employees and their roles per school/year, attendance, transfers | employee attendance 58k (+152k items) |
| Document | ~25 | Personnel/student documents with types (ID card, degree, photo, …) and conditions | 50k documents |
| Feedback | ~200 | Comments, likes, rates, complaints, custom forms, Q&A, departments, feedback tickets | comments 3.5k, tags 33k |
| Ticket | ~50 | Admin support tickets (messages, attachments, assignees) | ~10 tickets |
| Blog | ~60 | Blog posts & categories | 6.3k posts |
| Course | ~155 | **LMS-style courses**: categories, sections, activities, teachers, academies, enrollments, packages, video quizzes, progress | **almost empty** (4 courses) |
| Video | ~35 | Video library and usage | **empty** |
| Log | 2 | Model action log | — |

So in practice the system is mainly a **SIS + school operations** tool: students, classes, online sessions, exams, contracts/payments, staff HR. The LMS-style `Course` module is barely used.

## Domain facts (from live data)
- **Organization:** Saraye Danesh (سرای دانش) school chain, with about 21 schools. Each school is tied to an instance. Examples: پیش‌دبستان و دبستان مرزداران, دبیرستان دوره دوم رسالت, مدرسه مجازی سرای دانش, دبستان آنلاین, تیزهوشان, سراتک, and ستاد (head office).
- **Grades** follow the Iranian system in a tree: دبستان (پیش‌دبستان ۱–۲، اول–ششم) → متوسطه اول (هفتم–نهم) → متوسطه دوم (دهم–دوازدهم × تجربی/ریاضی/انسانی). There are also "مشترک" (shared/optional) grades.
- **Academic year** runs from about 1 Shahrivar to 31 Mordad (`schl_years`). Most data is year-scoped.
- **Contract types:** student, extracurricular (academic / non-academic), summer, special services, supporter, employee, full-time teacher, part-time teacher, service staff, general course.
- **Document types:** for staff hiring files (ID pages, national card, photo, degrees, military service, employment commitment, medical, …).
- **Roles (~78):** superadmin, admin, ceo, school_manager, deputy_education, deputy_executive, disciplinaryDeputy, teacher, supervisorTeacher, supporter, student, parent, psychologist, consultant, school_finance, school_informatics, main_office_* (head office: education, finance, informatics, manager), employee, janitor, building_manager, exam_manager, content_creator, studentRegisterOfficer, adobe_host, … plus dead car/tour/sale roles.

## Login in LMS
Users log in through "connectors" (`user_register_key` rows): about 28k national code, about 33k `mobile_sms`, about 10k mobile and about 6k email. Many users have no email. This shaped [ADR 0004](decisions/0004-authentication.md).

## Known overlaps & oddities
- Two exam systems: `schl_exam_*` (live) and `exam_*` (new, nearly empty).
- Two course concepts: `Course` module (LMS courses, empty) and `School\Course` (`schl_courses`, school subjects; 556 rows).
- Two ticket systems: `Feedback` tickets (`tickets_*`) and the `Ticket` module (`admin_tickets`).
- Student contracts: a contract with `parent_id` is supplementary (تکمیلی), otherwise main (شهریه). The "main contract" excludes `last_state = 'cancelled'` and NULL.
- A student can belong to several main classes through `schl_students_classrooms`; `main_class_id` is only filled for a minority of rows.

## Dead weight (candidates to drop)
These tables or roles come from cafeerent or other abandoned features and are empty or unused:
- Car rental: `cars`, `car_*`, `bookings`, `booking_*`, roles `admincar*`, `operationcars`
- Tourism / CIP: `tours`, `tour_*`, `cip_*`, instances `kish`, `turkey`, `dubai`, `istanbul`, `tourism`, …
- `entity_*` (business directory), `kanoon_*`, `crm_*`, `seo_*`, `mobile_driver_tehran`, `monitoring_list_2023…`, `coupen*`, `work_folder*`
- Empty LMS features: `Video` module, most of `Course`, the `exam_*` tables, `affiliates`, `vouchers`, `cart`
- Legacy views `vw_payments*` (broken)

## Porting status
Code is never copied directly; see [conventions.md → Porting from LMS](conventions.md#porting-from-lms).

| LMS part | Status | Lives in SaraSystem at |
|---|---|---|
| Admin panel UI (font Shabnam, theme + glass dark mode, colors, initials avatar, sidebar menu search, table top scrollbar, persisted filters, panel-wide table/field defaults) | ported 2026-10-23 | `app/Providers/Filament/AdminPanelProvider.php`, `resources/css/filament/admin/`, `resources/views/filament/admin/`, `app/Filament/Support/InitialsAvatarProvider.php` |
| Core menus (`/cpadmin/core/menu`: root menus + item tree, `MenuBlock`) | ported 2026-10-23, redesigned ([ADR 0012](decisions/0012-site-menus.md)): menus made in a tenant's panel, one chosen per tenant in the tenants list and shown in the site header (no menu block); link types page / url / none; no languages, icons/images or route links | `Modules/Site` (`Menu`, `MenuItem`, `TenantMenu`, `Filament/Resources/SiteMenus`, `Filament/Actions/ChooseTenantMenuAction`), `resources/js/Components/Public/NavMenu.vue` |
| Exam: online exams (`exam_*`, `Models/CourseExam`, `ExamAttemptService`) | ported 2026-10-23, redesigned ([ADR 0013](decisions/0013-exams.md)): standalone module, participants (users/roles), manual grading of written answers, autosave, time window, copy of served questions per attempt; options as jsonb, no relational/all-answers types | `Modules/Exam` |
| Employee preregister form «همکاری با ما» (`School\Livewire\EmployeePreregisterForm`, `/school/employee-preregister`) | ported 2026-10-25, redesigned ([ADR 0014](decisions/0014-job-applications.md)): own application table instead of User + `users.info` + `schl_school_employees`, job positions instead of roles, no Kanoon section, optional work history, résumé on the private disk, honeypot + rate limit instead of captcha, primary tenant only | `Modules/Employee` |
| Core cartable (`cartable`, `cartable_records`, entity-group verifiers) and the employee preregister review | redesigned 2026-10-26 ([ADR 0016](decisions/0016-workflows.md)), not ported: workflows designed in the panel (steps to a role or person in a fixed tenant), cases with history, claim, bell notifications; hiring per job position ends by creating the user and an employee. No entity groups, no forwarding to a tenant chosen at run time; tests/interviews are notes for now | `app/Workflow`, `app/Models/Workflow*.php`, `Modules/Employee` (`JobApplicationSubject`, `HireApplicant`, `Employee`) |
| Student preregister form (`School\Livewire\PreregisterForm`, `/school/preregister`) and its review (`Preregisters/Support/PreregisterWorkflow`: forward / reject / unreject) | ported 2026-10-27, redesigned and generalized ([ADR 0018](decisions/0018-student-preregistration.md)): learners of any organization (label per installation), programs instead of stage/grade, own preregistration table instead of User + `users.school_preregister_info`, review as a designed workflow per program, admission creates the user, roles and a `students` row in the chosen tenant (no year/class yet). Only generic fields are built in; birth certificate, birth place, previous school and last average are added fields (as the local demo sets them up); Tehran district and Kanoon dropped; typed city; SMS-confirmed mobile + honeypot instead of captcha; existing national codes accepted; primary tenant only | `Modules/Student` |
| School classes (`schl_classrooms`, `schl_students_classrooms`, class `supporter_id` / `supervisor_id`, `schl_years`, `schl_holidays`) | redesigned 2026-10-29 ([ADR 0022](decisions/0022-classes.md)), not ported: a generic `Class` module. Generic terms instead of school years, class roles (any kind of person, with flags) instead of fixed columns, one-level sub-classes instead of main / optional / sub types, holidays per tenant + organization-wide, mode + meeting link instead of Adobe Connect. No grade, school gender, categories or `params`. LMS data is not migrated | `Modules/Class` |
| School class plans, weekly schedules and sessions (`schl_classroom_plans`, `schl_weekly_schedules`, `schl_activity_sessions`, `schl_sessions`, `ScheduleSessionsBuilder`) | redesigned 2026-10-29 ([ADR 0023](decisions/0023-class-schedules-and-sessions.md)), not ported: one schedule table (once / daily / weekly / monthly in Jalali months, every N weeks instead of odd / even weeks, optional subject and teacher), one session table for in-person and online classes, built up to the schedule's end on save; sessions changed by hand are kept (LMS deleted and rebuilt them); clash checks; make-up / extra sessions, substitute teacher. No Adobe Connect (meeting link of the class), no `Activity` wrapper, no teaching plans yet (step 4) | `Modules/Class` |
| Session attendance (`schl_activity_session_attendance`, `ActivitySessionAttendance` page) | redesigned 2026-10-29 ([ADR 0024](decisions/0024-class-attendance.md)), not ported: present / late (minutes) / absent / excused with a note instead of a yes/no + in-person/online type, attendees only (staff are the session's teacher / substitute), who may take it (teacher, substitute, roles that take attendance, class editors) and until when (opens 15 min before, locks 3 days after for teachers) | `Modules/Class` |
| Teaching plans, course headlines, plan reports, homework (`teaching_plans`, `course_headlines`, `schl_classroom_plan_reports`, `homework`, `homework_media`) | redesigned 2026-10-29 ([ADR 0025](decisions/0025-teaching-plans-and-session-content.md)), not ported: one teaching plan with ordered topics (each taking N sessions) instead of plans + headlines per course and grade; chosen per schedule; topics forecast over the coming sessions when shown instead of stored on sessions, so unfinished topics move on; content (topics finished, summary, homework + due date, materials) recorded on the session. No book pages / publication years, no per-learner homework submissions or scores (`homework_students`) yet | `Modules/Class` |
| School years, grades, courses with `score_factor` (`schl_years`, `schl_grades`, `schl_grades_schools`, `schl_courses`) and students' classes per year | redesigned 2026-10-29 ([ADR 0031](decisions/0031-school-structure.md)), not ported: a `School` module on top of Class and Student; academic years are Class terms; stages (with score scale) → grades + majors defined in the head office; curriculum per grade (and major) with coefficient and weekly hours instead of courses per grade and year; a class's grade; a pupil's grade per year («پرونده‌ی تحصیلی») filled in from their class, with promotion. Not taken (by the user's choice, may come later): exam balances, supporter reports to parents, left students, school services, advising times. Report cards and lesson scores come with part B | `Modules/School` |
| Report cards and lesson scores (`report_card` with fixed period types, `lesson_score`) | redesigned 2026-10-29 ([ADR 0032](decisions/0032-scores-and-report-cards.md)), not ported: score periods defined per academic year instead of fixed types; a score book for the subject's teacher (number out of 20 or descriptive level by stage) instead of typing report cards; report cards worked out (weighted average by coefficient, absences, homework average) instead of a typed average; published to learners and guardians with a PDF. No activity notebook, year-final formulas or resits yet | `Modules/School` |
| Employee preregister documents upload | redesigned 2026-10-28 ([ADR 0019](decisions/0019-documents.md)): a `Document` module for employees and learners, types with upload/check roles, applicants' uploads in the public forms, documents in workflow steps | `Modules/Document`, `app/Documents` |
| Contract (`contracts`, `contract_types`, `contract_templates` with `[placeholders]`, `ContractWorkflow` finance approval, employee contract form) | redesigned 2026-10-29 ([ADR 0020](decisions/0020-contracts.md)), not ported: generic contract types (head office) for employees and learners with a rich-editor text and merge tags, fields per type as a customizable form instead of hard-coded wage / promissory parameters, the text fixed with a hash when issued, electronic signing with an SMS code or a recorded paper signature, PDF with mPDF, contracts prepared in workflow steps. No school/year/grade columns, no `params` JSON, no own finance approval. LMS data is not migrated | `Modules/Contract`, `app/Contract`, `app/Events/ApplicantAccepted.php` |
| — not ported from Contract (wait for a finance module): installments (`contract_invoices`), cheques/guarantees, discounts («offers») with CEO approval, payments through `Sale` orders, debt follow-ups (`contract_track_logs`) and reminder commands, Excel exports. Not ported at all: unused warranties, trackers, check lists, categories, payment information; Kanoon handling. Word-to-HTML template import (`WordToHtmlConverter`) may come later | | |
| — not ported yet from the employee preregister: scientific/counseling test results as structured data | | |
| — not ported from Exam: `schl_exam_*` (activity scores entered by teachers, exam balances `schl_exam_balances`): they need School and come with it; `score_notebook_status` / `exam_impacted_value` (report card) | | |
| — not ported: year/school/instance topbar switchers (replaced by Filament tenancy), `LegacyNavigation`, blog editor CSS (comes with a Blog module) | | |

## How to inspect LMS
- Code: read files under `../LMS/Modules/<Module>/app/{Models,Filament,Http,Libraries,Bridge}`.
- Live DB (read-only; never write): `docker exec lms-mysql-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --default-character-set=utf8mb4 -uroot "$MYSQL_DATABASE" -e "SELECT ..."'`. Use `utf8mb4` or Persian text shows up as `???`.
- Model → table: `grep -rn "protected \$table" ../LMS/Modules/<Module>`
- Don't read or copy `../LMS/.env`, `pass.txt` or credentials into this repo.

## Mapping to SaraSystem domains
| Domain | LMS sources |
|---|---|
| SIS (students) | School (students, classes, grades, years, report cards, scores, attendance), User (parents), Contract (student contracts), Document |
| LMS (learning) | School activities/sessions/homework/online classes, Exam (`schl_exam_*`), Course & Video (empty, to be redesigned) |
| TMS (training management) | Classroom plans & weekly schedules, teaching plans, teachers/supporters, Employee, extracurricular/summer contracts — *exact TMS scope to be defined* |
| Shared / platform | Core (instances, access, cartable, notifications, forms), User, Sale (payments), Feedback, Ticket, Blog, Log |
