# Tutorial scenarios: design

Designs of the step-by-step tutorial's scenarios («آموزش گام‌به‌گام», [ADR 0040](decisions/0040-step-by-step-tutorial.md)), to be built one at a time. Together they cover most of the system, so a client can learn it from the scenarios that fit their organization. Each section is the spec for one `Scenario` class in [Modules/Tutorial/app/Scenarios](../Modules/Tutorial/app/Scenarios). When a scenario is built, mark it here, keep this doc in step with the code, and log it in [progress.md](progress.md).

Screen names are the Persian labels as they are in the system today. Check them against the code when building a scenario: labels change.

## How the scenarios fit together

| # | Scenario | For | Missions (about) | Modules it needs | Status |
|---|---|---|---|---|---|
| 1 | «آموزشگاه زبان با یک شعبه» | language institutes; everyone's first scenario | 20 | Employee, Student, Class (+ Document, Contract) | **built** ([LanguageInstitute](../Modules/Tutorial/app/Scenarios/LanguageInstitute.php)) |
| 2 | «مدیریت سامانه و دسترسی‌ها» | every installer / head office | 13 | none (+ Employee for the workflow chapter) | **built** ([SystemAdministration](../Modules/Tutorial/app/Scenarios/SystemAdministration.php)) |
| 3 | «مدرسه: از پیش‌ثبت‌نام تا کارنامه» | school chains | 28 | Class, Employee, School, Student (+ Document, Exam) | **built** ([School](../Modules/Tutorial/app/Scenarios/School.php)) |
| 4 | «کارگزینی: مدارک و قراردادها» | every organization with staff | 18 | Contract, Document, Employee | **built** ([HumanResources](../Modules/Tutorial/app/Scenarios/HumanResources.php)) |
| 5 | «آموزش سازمانی کارکنان» | companies (TMS) | 17 | Employee, Class, Exam | planned |
| 6 | «اداره‌ی روزانه‌ی کلاس‌ها در چند شعبه» | institutes and schools with branches | 21 (20 without Exam) | Class, Employee, Student (+ Exam) | **built** ([BranchOperations](../Modules/Tutorial/app/Scenarios/BranchOperations.php)) |
| 7 | «وب‌سایت سازمان» | every organization with a public site | 10 | Site (+ Employee, Student for the form links) | planned |

**Order offered to a client:** 2 first (who can do what), then the one that matches the organization (1, 3 or 5), then 4, 6 and 7 as needed. Scenarios are independent of each other: each makes its own data from scratch, so any one can be run alone and reset alone.

**Coverage**: which scenario teaches which part of the system.

| Area | 1 | 2 | 3 | 4 | 5 | 6 | 7 |
|---|---|---|---|---|---|---|---|
| Tenants, primary tenant, deactivating | ● | ● | ● | | ● | ● | |
| Users, roles, permissions, role-assignment rules, access map | | ● | | | | | |
| Impersonation, SMS login, profile | ◐ | ● | ◐ | ◐ | ◐ | | |
| Module manager «ماژول‌ها» | | ● | | | | | |
| Workflows: designer, cartable, return / reject / edit / claim, «همه‌ی پرونده‌ها», flowchart | ◐ | ● | ◐ | ● | | | |
| Quick employee entry, Excel import | ◐ | | | | ● | | |
| Hiring «همکاری با ما» (positions, form, workflow) | ● | | | ● | | | ◐ |
| Customizable forms (built-in modes, added fields) | | | ● | ● | | ◐ | |
| Preregistration, programs offered per tenant, admission | ● | | ● | | | ● | ◐ |
| Guardians, login mobile, child view | | | ● | | | | |
| Documents: types, upload, check, expiry | ◐ | | ◐ | ● | | | |
| Contracts: types, merge tags, issue, e-signature, paper, amendment, renewal | ◐ | | | ● | | | |
| Terms, class roles, classes, members, capacity, sub-classes | ◐ | | ● | | ● | ● | |
| Subjects, schedules (once / weekly / monthly), exam sittings, holidays, clashes | ◐ | | ● | | ◐ | ● | |
| Sessions: move, substitute, cancel, make-up | | | | | | ● | |
| Attendance, absence SMS | ◐ | | ● | | ● | ● | |
| Teaching plans, session content, homework, grading | | | ● | | ◐ | | |
| School structure: stages, grades, majors, curriculum | | | ● | | | | |
| Score periods, score book, report cards, records, promotion | | | ● | | | | |
| Exams: banks, questions, exam, attempt, manual grading, results | | | ◐ | | ● | | |
| «کلاس‌های من» (learner, teacher) | ● | | ● | | ● | ◐ | |
| Site pages, page builder, widgets, menus | | | | | | | ● |

● taught in depth, ◐ touched.

Not covered by any scenario yet: the step-by-step tutorial itself, the module manager beyond switching one module, and what has no screen yet (finance, tickets).

## Building a scenario: rules and known gaps

The engine's rules ([ADR 0040](decisions/0040-step-by-step-tutorial.md)) shape what a mission can be. Keep to them, or extend the engine first:

- **A mission only creates.** Its done check looks at rows made in the run (`TutorialRun::made()`). A step that only changes an existing row (approve, publish, sign) is checked through a row made in the run whose state changed (a case's `current_step`, a document's `status`, a contract's `signed_at`). Changes to rows that existed before the run are not undone on reset, so never ask the person to edit pre-existing data.
- **Every mission gets `fill()` where it has a form and `perform()`**, so «پر کردن خودکار» and «انجام خودکار همه‌ی گام‌ها» keep working (`Scenario::canAutoComplete()` needs all of them). `perform()` goes through the same services as the screens, never raw inserts.
- **Sample data is fixed and named in the steps** (people, codes, titles), with valid national codes, and fake mobiles `0912000xxxx` so no real person gets an SMS.
- **Each scenario gets a test** that walks its missions in order (like `test_missions_open_one_after_another_as_the_rows_appear`) and one that runs `autoComplete` and then `reset`.
- **Modules:** a scenario lists the modules it can't do without in `Scenario::requiredModules()` (the page doesn't offer it while one is off), and its optional missions check `Scenario::enabled()` (ADR 0041).
- **Engine gaps, by the scenario that hits them:**
  - *Queued work is not recorded* (5: the Excel import, 3/6: absence SMS jobs). For imports, record the import's rows by the `imports` row made in the run, or run the import synchronously in tutorial mode.
  - *Time windows* (1, 3, 5, 6: attendance opens 15 minutes before a session; teachers' content and score deadlines). Sessions are scheduled a few minutes ahead; score periods are given dates around today.
  - *SMS codes* (2, 4: signing a contract, signing in with a code). With the `log` driver the step tells where to read the code (`storage/logs/sms.log`). A tutorial-only helper that shows the last code sent to a tutorial account would be friendlier.
  - *Scheduled tasks* (4: contract reminders). Not taught as waiting; the mission shows the list filter «پایان نزدیک» instead.
  - *Uploaded files stay on disk after a reset.* Accepted for now.
  - *Inertia pages have no auto-fill* (the public forms, «کلاس‌های من», `/profile/exams`, `/profile/contracts`): `perform()` covers them, and «پر کردن خودکار» for Vue forms is a later engine task.

---

## 1. «آموزشگاه زبان با یک شعبه» (built)

Implemented; see [LanguageInstitute](../Modules/Tutorial/app/Scenarios/LanguageInstitute.php) and ADR 0040 for its 20 missions: a branch and a registrar; hiring the instructor through «همکاری با ما» with documents and a contract; admitting a learner through preregistration; a term, a class, members, a session and attendance.

Possible additions later: the instructor signing her contract in `/profile/contracts`, and the learner seeing the session in «کلاس‌های من».

---

## 2. «مدیریت سامانه و دسترسی‌ها» (built)

Implemented in [SystemAdministration](../Modules/Tutorial/app/Scenarios/SystemAdministration.php), offered first.

**Who it is for:** the person who installs or runs the system for the organization (super admin, head-office IT).
**Goal:** the organization's structure, who can see and do what, and how a case moves through a workflow. Everything else builds on this.
**Modules:** none required. The workflow chapter needs a subject, and today every subject comes from a module, so it uses job applications and is left out while Employee is off (ADR 0041).
**Actors:** خودتان (مدیر کل سیستم), «مدیر {tenant_label}» (مریم صادقی, made in the scenario), the applicant (public form).

**Sample data:** branches «شعبه شرق» (`east`), «شعبه غرب» (`west`), told apart by the order they were made; users «مریم صادقی» (0020040067), «حمید نوری» (0030050073), «سمیرا عباسی» (0040060081); role «مسئول کتابخانه» (`librarian`); workflow «جذب کارشناس پشتیبانی», position «کارشناس پشتیبانی», applicant «نیما شریفی» (0050070096).

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. ساختار سازمان** |||||
| 1 | Two branches; the steps explain home pages and the primary tenant | you | «شعبه‌ها» | 2 tenants made |
| **B. کاربران و نقش‌ها** |||||
| 2 | «مریم صادقی» with «مدیر {tenant_label}» and panel access in «شعبه شرق» | you | «کاربران» (شعبه شرق) | a user with `tenant_manager` + `panel_user` there |
| 3 | Role «مسئول کتابخانه» with a few permissions | you | «نقش‌ها» | a role made in the run with ≥ 1 permission |
| 4 | «حمید نوری» as «کارمند» in «شعبه شرق», then «افزودن کاربر موجود» in «شعبه غرب» with the new role; see him in «نقشه‌ی دسترسی» | you | «کاربران» in both | staff in the first branch and the new role in the second |
| **C. از نگاه مدیر** |||||
| 5 | As the manager: see what she may not give (ADR 0003) and add «سمیرا عباسی» as «کارمند» | manager | «کاربران» | a third user with `staff` in the first branch |
| 6 | Back to yourself; deactivate «شعبه غرب» | you | «شعبه‌ها» | the second branch is inactive |
| **D. فرایند و کارتابل** (Employee on) |||||
| 7 | Workflow for job applications: «بررسی در شعبه» (role «مدیر {tenant_label}» in «شعبه شرق»), «تأیید نهایی» (yourself, «برگشت» and «رد» allowed) | you | «کارتابل → فرایندها» | first step by role, a step allowing return and reject |
| 8 | Job position «کارشناس پشتیبانی» with the workflow | you | «موقعیت‌های شغلی» | active position with it |
| 9 | Apply as «نیما شریفی» | applicant | `/careers` | the application's case exists |
| 10 | As the manager: «برداشتن کار», «افزودن یادداشت», «تأیید» | manager | «کارتابل من» (شعبه شرق) | the case has a note and an approval |
| 11 | As yourself: «برگشت» to the first step with a reason | you | «کارتابل من» | the case has a return |
| 12 | As the manager: approve again | manager | «کارتابل من» | an approval after the return |
| 13 | As yourself: «رد» with a reason; follow it in «همه‌ی پرونده‌ها» and «فلوچارت فرایند»; the steps mention «ماژول‌ها» (read only) | you | «کارتابل من», «همه‌ی پرونده‌ها» | the case is rejected |

**Notes:**
- Read-only parts (home pages, the access map, the module manager) are steps inside missions that create something, since a page visit is not a row.
- Mission 5 can't check that the manager made the user (role assignments record no actor); it checks a third staff member in the branch.
- `perform()` makes users and roles as the screens do (roles per tenant through the permission team).

---

## 3. «مدرسه: از پیش‌ثبت‌نام تا کارنامه» (built)

Implemented in [School](../Modules/Tutorial/app/Scenarios/School.php).

**Who it is for:** school chains (the first deployment, سرای دانش).
**Goal:** a school year end to end: structure, admission with the father as guardian, a class with a weekly timetable and a teaching plan, attendance with the absence SMS, homework, scores, the report card, promotion, and an exam.
**Modules:** requires Class, Employee, School, Student. Document adds the birth certificate to the admission; Exam adds the last chapter.
**Actors:** خودتان, «معاون آموزشی» (زهرا کاظمی, `education_officer`), «معلم» (نرگس مرادی, `instructor`), «دانش‌آموز» (امیر حسینی, signs in with his father's mobile), applicant (public form).

**Sample data:** school «دبیرستان نمونه» (`nemooneh`); stage «متوسطه اول» (numeric) with grades «هفتم», «هشتم»; subjects «ریاضی» (×4), «علوم» (×3); term «سال تحصیلی ۱۴۰۵-۱۴۰۶» from today for nine months and a holiday a week from today; father «محمد حسینی»; class «هفتم الف»; plan «ریاضی هفتم» (3 topics); score period «نوبت اول»; exam «آزمونک ریاضی».

| # | Mission | Actor | Done when |
|---|---|---|---|
| **A. ساختار مدرسه** ||||
| 1 | The school | you | tenant made |
| 2 | Education officer by quick entry, panel access | you | employee with `education_officer` + panel in the school |
| 3 | Teacher by quick entry, no panel | you | employee with `instructor` |
| 4 | Stage «متوسطه اول» and grades «هفتم», «هشتم» (head office) | you | a grade of the run's stage that has a next grade |
| 5 | Subjects and grade 7's curriculum | you | ≥ 2 curriculum rows for the grade |
| 6 | School year and a holiday next week | you | term and holiday made |
| **B. پیش‌ثبت‌نام و پذیرش** ||||
| 7 | Admission workflow (step by role «کارشناس آموزش» in the school); with Document on, the type «شناسنامه» checked in the step | you | workflow (+ type) |
| 8 | Program «پایه هفتم» in the school; its «فیلدهای فرم»: father and «موبایل ورود» required, added field «معدل سال قبل», (Document) «شناسنامه» required | you | the program's own form setup asks for the father (and the document) |
| 9 | Preregister «امیر حسینی» with the father's details, login mobile «پدر», (the birth certificate) | applicant | preregistration with `father` (+ upload) |
| 10 | As the officer: (check the document,) approve as «فراگیر»; the father becomes a guardian | officer | student + guardianship made |
| **C. کلاس و برنامه‌ی هفتگی** ||||
| 11 | Class «هفتم الف» with grade «هفتم» | you | classroom with the grade |
| 12 | Members: student and teacher; the academic record is filled in | you | enrollment for the class + teacher member |
| 13 | Teaching plan «ریاضی هفتم» (head office) | you | plan with ≥ 2 topics |
| 14 | Weekly schedule: math, teacher, plan, today's weekday, four weeks, starting in ten minutes; next week's holiday is skipped | you | weekly schedule with a session |
| **D. کلاس در جریان** ||||
| 15 | Attendance: the student absent; the father gets the SMS | teacher | an absence |
| 16 | Session content (first topic) and homework due in 3 days | teacher | the first session has homework |
| 17 | Hand in the homework | student | submission |
| 18 | Grade it; the steps show the father's «کلاس‌های امیر» | teacher | submission scored |
| **E. نمره و کارنامه** ||||
| 19 | Score period «نوبت اول», entry closes in a week | you | period for the year |
| 20 | Score 18 in «دفتر نمره» | teacher | score made |
| 21 | «انتشار کارنامه»; the steps show «کارنامه» and its PDF | you | period published |
| 22 | Next year's term and «ارتقا به سال بعد» | you | a record in another term |
| **F. آزمون آنلاین** (Exam on) ||||
| 23 | Bank «ریاضی هفتم»: 3 choice + 1 written questions | you | bank with a written question |
| 24 | Create «ریاضی» and hover or focus its row to reveal «افزودن زیردسته» beside the title. Add «اعداد صحیح», assign a question to it with the tree selector, and filter by the parent | you | bank has a question assigned to a subcategory |
| 25 | Create the «مرور» tag, assign it to a bank question, and filter the questions by it | you | bank has a question tagged «مرور» |
| 26 | Exam: 4 questions from the bank, role «فراگیر», now for an hour | you | published exam drawing from the bank |
| 27 | Take it | student | attempt submitted |
| 28 | Grade the written answer | you | attempt completed |

**Differences from the first design:** the parents' fields are turned on in the program's «فیلدهای فرم» rather than the whole «تنظیم فرم پیش‌ثبت‌نام» (a whole-form setup may already exist and would change real data); the document type is made in the workflow mission; the read-only missions (learner page tabs, the parent's view, the report card view) are steps of the missions before them.

**Notes:**
- The first session starts ten minutes after the schedule is saved, so the teacher can take attendance and record content at once (it fails in the last minutes before midnight, like scenario 1).
- `Grade::next()` follows the head office's grades in order; the scenario's «هشتم» comes right after its «هفتم».

---

## 4. «کارگزینی: مدارک و قراردادها» (built)

Implemented in [HumanResources](../Modules/Tutorial/app/Scenarios/HumanResources.php).

**Who it is for:** HR in any organization.
**Goal:** a staff member's paperwork from application to renewal: a hiring workflow across tenants with an edit and a return, documents with expiry, a contract type with its own fields, electronic and paper signatures, an amendment, a renewal, a contract ending soon and a cancellation.
**Modules:** requires Contract, Document, Employee.
**Actors:** خودتان, «مدیر {tenant_label}» (بهرام قاسمی, 0100120105, the interviewer in the branch), applicant, the new employee (لیلا فرهادی, 0110130103).

**Sample data:** branch «شعبه مرکزی» (`markazi`); document types «کارت ملی», «گواهی سلامت» (with expiry); contract type «قرارداد کار معین» (electronic; amount required; added fields «حقوق ماهانه», «ساعت کار هفتگی»); amendment type «الحاقیه افزایش حقوق» (paper); workflow «استخدام کارشناس مالی»; position «کارشناس مالی».

| # | Mission | Actor | Done when |
|---|---|---|---|
| **A. آماده‌سازی** ||||
| 1 | Branch and its manager (quick entry, panel access) | you | manager employee with `tenant_manager` + panel |
| 2 | Document types, one with expiry | you | both types |
| 3 | Contract type and its «فیلدهای قرارداد» | you | type whose form requires the amount and has added fields |
| 4 | Amendment type, paper signature | you | amendment type signed on paper |
| **B. استخدام** ||||
| 5 | Workflow: «بررسی مدارک» (you; checks «کارت ملی»; «ویرایش اطلاعات» allowed), «مصاحبه» (role «مدیر {tenant_label}» in the branch; «برگشت»), «تنظیم قرارداد» (you; prepares the type) | you | all three settings |
| 6 | Position with its «فیلدهای فرم»: résumé optional, an added field, «کارت ملی» required, «گواهی سلامت» optional | you | position whose form requires the ID card |
| 7 | Apply with the ID card | applicant | upload + case |
| 8 | Check the document, «ویرایش اطلاعات» (last name), approve | you | an edit in the history, case past step 1 |
| 9 | As the manager: «برگشت» with a reason | manager | a return |
| 10 | You approve again with a note; the manager approves the interview | you / manager | case at the contract step after the return |
| 11 | «تنظیم قرارداد» with the amount and the added fields | you | draft with an amount |
| 12 | Hire into the branch as «کارمند»; the contract is issued and an SMS sent | you | employee from the application, contract issued |
| **C. پرونده‌ی کارمند** ||||
| 13 | Employee page → «مدارک»: «گواهی سلامت» expiring next month, approved | you | approved document with an expiry |
| 14 | Sign the contract in `/profile/contracts` with the SMS code | employee | signed electronically |
| 15 | «الحاقیه» (numbered `…/1`), «صدور», «ثبت امضای کاغذی» | you | amendment signed on paper |
| 16 | «تمدید» and «صدور» | you | renewal issued |
| 17 | A second contract ending in 20 days, issued; the «پایان نزدیک» filter | you | issued contract ending within 30 days |
| 18 | «لغو قرارداد» with a reason | you | it is cancelled |

**Differences from the first design:** the form changes are on the position's «فیلدهای فرم», not the whole «تنظیم فرم همکاری با ما» (a whole-form setup may already exist, and resetting would not restore it); the branch and its manager are one mission; the return and the second approvals are three missions.

**Notes:**
- The reminders themselves run from the scheduler at 08:00; the scenario shows the list's «پایان نزدیک» instead of waiting.
- Signing needs the SMS code (`storage/logs/sms.log` with the `log` driver); `perform()` calls `Contracts::signElectronically()`.

---

## 5. «آموزش سازمانی کارکنان»

**Who it is for:** companies running internal training (TMS).
**Goal:** departments as tenants, staff loaded in bulk, a training course with online sessions, attendance, an exam at the end with a pass score, and results.
**Modules:** Employee, Class, Exam.
**Actors:** خودتان, «مدیر آموزش» (training manager), «مدرس دوره», «کارمند (فراگیر)».

**Sample data:** departments «واحد فروش», «واحد فناوری»; course «آشنایی با امنیت اطلاعات»; trainer «کاوه رحیمی»; trainees from an Excel file of 5 employees.

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. سازمان و کارکنان** |||||
| 1 | Two departments as tenants (the steps explain «شعبه» is renamed per installation: `tenant_label`) | you | «شعبه‌ها» | 2 tenants |
| 2 | Training manager by quick entry in the head office, role «کارشناس آموزش», panel access | you | «کارمندان» | employee with the role |
| 3 | Import 5 employees into «واحد فروش» from the Excel template, with role «فراگیر» picked per row (download it, fill it, upload; see a failed row and why) | you | «کارمندان» → «ورود از فایل» | 5 employees in the tenant (engine gap: queued) |
| 4 | Trainer «کاوه رحیمی» by quick entry, role «مدرس» | you | «کارمندان» | employee with `instructor` |
| **B. دوره‌ی آموزشی** |||||
| 5 | Term «دوره‌های آموزشی پاییز» (a training round) | training manager | «کلاس‌ها → دوره‌های آموزشی» | term |
| 6 | A class role «ناظر دوره» that takes attendance, limited to «کارشناس آموزش» | training manager | «کلاس‌ها → نقش‌های کلاس» | class role with the flags |
| 7 | Class «امنیت اطلاعات - گروه ۱» in «واحد فروش», mode «آنلاین» with «بستر کلاس آنلاین» «لینک» and a meeting link (ADR 0073), capacity 10 | training manager | «کلاس‌ها» | online classroom |
| 8 | Add the 5 trainees («فراگیر»), the trainer and yourself as «ناظر دوره» | training manager | tab «اعضا» | members by role |
| 9 | Daily schedule for three days with the trainer | training manager | «برنامهٔ درسی» page | 3 sessions |
| 10 | As the trainer: open the session in «کلاس‌های من», take attendance (one «با تأخیر» 10 minutes), record content and a file | trainer | `/profile/my-classes` | attendance + content |
| **C. آزمون پایان دوره** |||||
| 11 | Question bank «امنیت اطلاعات» with 5 choice questions (one with negative marking) | training manager | exam screens | bank + 5 questions |
| 12 | Exam «آزمون پایان دوره»: 5 questions from the bank, for the class's trainees by role «فراگیر» in «واحد فروش», 20 minutes, one retake, results shown after the window | training manager | «آزمون‌ها» | exam with participants and rules |
| 13 | As a trainee: take the exam; leave one unanswered; submit | trainee | `/profile/exams` | attempt submitted |
| 14 | As another trainee: start and let the time run out (or submit after the grace minutes) | trainee | `/profile/exams` | timed-out attempt (or merge into 13) |
| 15 | Results: see scores and, as a trainee, the result once allowed | training manager | «نتایج آزمون‌ها» | attempts graded |
| **D. جمع‌بندی** |||||
| 16 | Close the course: set the trainees' leave date, deactivate the class | training manager | class | members left, class inactive |
| 17 | (Contract on, optional) A training-commitment contract «تعهدنامه‌ی آموزشی» for one trainee, signed on paper | you | employee page → «قراردادها» | contract signed |

**Notes for building:**
- Mission 3 is the engine's queue gap: the import runs on the queue, so its employees are not recorded. Decide before building (see the rules above).
- The time-out in mission 14 is closed lazily (no scheduler); `perform()` makes an attempt whose start is older than the duration.
- Department heads as tenant managers and per-department reports would be natural later missions; there are no reports yet.

---

## 6. «اداره‌ی روزانه‌ی کلاس‌ها در چند شعبه» (built)

Implemented in [BranchOperations](../Modules/Tutorial/app/Scenarios/BranchOperations.php).

**Who it is for:** institutes and schools with several branches, after scenario 1 or 3.
**Goal:** the everyday work of a branch: programs offered in some branches, a class with a capacity and a sub-class, weekly and monthly timetables and clashes, a branch holiday, a substitute who takes attendance, a moved session, a cancellation with a make-up, a learner who leaves.
**Modules:** requires Class, Employee, Student; Exam adds the session-exam mission.
**Actors:** خودتان, «کارشناس آموزش شعبه پونک» (مینا جعفری, does the branch's class work), «کارشناس آموزش شعبه تجریش» (کامران صدری), «مدرس جایگزین» (رضا توکلی), applicant.

**Sample data:** branches «شعبه پونک» (`pounak`), «شعبه تجریش» (`tajrish`), told apart by order; teacher «بهاره امینی» (instructor in both); programs «مکالمه‌ی پیشرفته» (both branches, with the select field «سطح فعلی زبان») and «IELTS» (تجریش only); learners «نگار رحمانی», «پویا کریمی»; term «ترم زمستان»; classes «Conversation B2» (+ sub-class «گروه ب») and «IELTS Prep».

| # | Mission | Actor | Done when |
|---|---|---|---|
| **A. شعبه‌ها و برنامه‌ها** ||||
| 1 | Two branches | you | 2 tenants |
| 2 | An education officer in each (quick entry, panel) | you | `education_officer` + panel in both |
| 3 | Bahareh instructor in both branches (same national code), Reza in the first | you | roles as said |
| 4 | Admission workflow, one step for yourself | you | workflow for preregistrations |
| 5 | «مکالمه‌ی پیشرفته» in both branches, «IELTS» in تجریش only | you | both availabilities |
| 6 | The program's own select field | you | program form setup with a select field |
| **B. پذیرش** ||||
| 7 | Two preregistrations for پونک; see IELTS offer only تجریش | applicant | 2 preregistrations |
| 8 | Admit both into پونک | you | 2 learners in the branch |
| **C. کلاس‌ها و برنامه‌ی آن‌ها** ||||
| 9 | Term «ترم زمستان» | you | term |
| 10 | «Conversation B2» with capacity 1, the capacity refusal, capacity 2, both learners and the teacher | officer 1 | class with 2 attendees and the teacher |
| 11 | Sub-class with one learner from the parent | officer 1 | sub-class with an attendee |
| 12 | Weekly schedule: today, +2 and +4 days, four weeks, starting in ten minutes | officer 1 | weekly schedule with ≥ 3 sessions |
| 12a | Open «برنامهٔ امتحانی» from the class row, add a sitting six days from today, 10:00 to 11:00, find its edit action and open its details | officer 1 | exam session for Conversation B2 |
| 13 | «IELTS Prep» in تجریش, monthly «دومین» (tomorrow's weekday) for three months; the teacher clash is shown first | officer 2 | monthly schedule with a week of month |
| 14 | Branch holiday a week from today; that day's session goes | officer 1 | branch holiday, no session on it |
| **D. تغییرهای روزمره** ||||
| 15 | «مدرس جایگزین» on today's session | officer 1 | a substituted session |
| 16 | The substitute takes attendance (one late 10 minutes) | substitute | attendance recorded by him |
| 16a (Exam on) | Create a personal «تمرین مکالمه» bank in the user panel, add a written question and generate and publish an exam for today’s session; optional learner entry and teacher grading | officer 1 | a published exam made in the run points to the substituted session |
| 17 | «جابه‌جایی» of the second session to five days from today, «اتاق ۳» | officer 1 | a moved session |
| 18 | «لغو جلسه» of the third with a reason, and a make-up in three days | officer 1 | cancelled + make-up session |
| 19 | A learner leaves («تاریخ خروج» today) | officer 1 | member with a leave date |
| 20 | Deactivate «مکالمه‌ی پیشرفته» | you | inactive program |

**Differences from the first design:** the admission step is yours (not each branch's officer), so one workflow serves both branches; the two classes are in different branches so the teacher clash crosses branches; the substitute takes today's session, the only one whose attendance is open.

**Notes:**
- The days are chosen apart (B2 on today, +2, +4; IELTS on +1; make-up on +3; moved session on +5) so the same teacher never clashes, whatever the weekday.
- Mission 13's refused clash and mission 10's refused capacity are taught in the steps; the checks see the saved result.

---

## 7. «وب‌سایت سازمان»

**Who it is for:** anyone publishing the organization's public site.
**Goal:** branch home pages, pages and widgets, menus, and linking the public forms.
**Modules:** Site (+ Employee, Student for links to `/careers` and `/preregister`).
**Actors:** خودتان, a branch's «مدیر شعبه» (editing their own branch's site).

**Sample data:** branch «شعبه سعادت‌آباد»; pages «درباره‌ی ما» (`/about-us`), «تماس با ما» (`/contact`).

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| 1 | Branch «شعبه سعادت‌آباد»; open its home page `/{slug}` (made with the tenant) | you | «شعبه‌ها» | tenant + home page |
| 2 | Page «درباره‌ی ما» at `/about-us`, published | you | «وب‌سایت → صفحه‌ها» | page made |
| 3 | In «صفحه‌ساز» add an HTML widget with a title and text, and a second one hidden («نمایش در سایت» off) | you | page row → «صفحه‌ساز» | page content with 2 widgets, one hidden |
| 4 | Edit the branch home page: an HTML widget with an embedded map (https iframe) | you | home page → «صفحه‌ساز» | home page content changed (row made in the run: allowed) |
| 5 | Page «تماس با ما», unpublished; see it is not public | you | «صفحه‌ها» | unpublished page |
| 6 | Menu «منوی اصلی» with items: «درباره‌ی ما» (site page), «همکاری با ما» (`/careers`), «پیش‌ثبت‌نام» (`/preregister`), a submenu with an external link | you | «وب‌سایت → منوها» → items | menu with ≥ 4 items, one nested |
| 7 | Reorder by drag-and-drop | you | menu items | (merge into 6) |
| 8 | Choose the menu for the branch («منوی سایت» on the tenant row) and for the head office | you | «شعبه‌ها» | tenant menu set |
| 9 | As the branch manager: edit their own branch's page (they can't see other branches' pages) | manager | «صفحه‌ها» | page edited by the manager |
| 10 | Open the public site, follow the menu | — | public site | (merge into 9) |

**Notes for building:**
- The site hides other people's tutorial rows already (`HiddenRecords`), so the tutorial's pages and branch don't reach real visitors. The menu on the head office (mission 8) changes a pre-existing row: either leave it out, or record the previous value and restore it on reset (engine change).
- Site pages are per tenant; the manager in mission 9 needs `Update:Page` permissions there.

---

## Implementation order (suggested)

1. ~~`Scenario::requiredModules()`~~ and ~~scenario 2~~ (done). Still open in the engine: a read-only «خواندم» tick (optional) and a decision on queued imports (before scenario 5).
2. ~~Scenario 3 «مدرسه»~~ (done).
3. ~~Scenario 4 «کارگزینی»~~ (done).
4. ~~Scenario 6~~ (done), then 5, then 7.
