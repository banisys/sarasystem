# Tutorial scenarios: design

Designs of the step-by-step tutorial's scenarios («آموزش گام‌به‌گام», [ADR 0040](decisions/0040-step-by-step-tutorial.md)), to be built one at a time. Together they cover most of the system, so a client can learn it from the scenarios that fit their organization. Each section is the spec for one `Scenario` class in [Modules/Tutorial/app/Scenarios](../Modules/Tutorial/app/Scenarios). When a scenario is built, mark it here, keep this doc in step with the code, and log it in [progress.md](progress.md).

Screen names are the Persian labels as they are in the system today. Check them against the code when building a scenario: labels change.

## How the scenarios fit together

| # | Scenario | For | Missions (about) | Modules it needs | Status |
|---|---|---|---|---|---|
| 1 | «آموزشگاه زبان با یک شعبه» | language institutes; everyone's first scenario | 20 | Employee, Student, Class (+ Document, Contract) | **built** ([LanguageInstitute](../Modules/Tutorial/app/Scenarios/LanguageInstitute.php)) |
| 2 | «مدیریت سامانه و دسترسی‌ها» | every installer / head office | 11 | none (core) | planned |
| 3 | «مدرسه: از پیش‌ثبت‌نام تا کارنامه» | school chains | 30 | Student, Class, School (+ Document) | planned |
| 4 | «کارگزینی: مدارک و قراردادها» | every organization with staff | 18 | Employee, Document, Contract | planned |
| 5 | «آموزش سازمانی کارکنان» | companies (TMS) | 17 | Employee, Class, Exam | planned |
| 6 | «اداره‌ی روزانه‌ی کلاس‌ها در چند شعبه» | institutes and schools with branches | 16 | Student, Class | planned |
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
| Quick employee entry, CSV import | ◐ | | | | ● | | |
| Hiring «همکاری با ما» (positions, form, workflow) | ● | | | ● | | | ◐ |
| Customizable forms (built-in modes, added fields) | | | ● | ● | | ◐ | |
| Preregistration, programs offered per tenant, admission | ● | | ● | | | ● | ◐ |
| Guardians, login mobile, child view | | | ● | | | | |
| Documents: types, upload, check, expiry | ◐ | | ◐ | ● | | | |
| Contracts: types, merge tags, issue, e-signature, paper, amendment, renewal | ◐ | | | ● | | | |
| Terms, class roles, classes, members, capacity, sub-classes | ◐ | | ● | | ● | ● | |
| Subjects, schedules (once / weekly / monthly), holidays, clashes | ◐ | | ● | | ◐ | ● | |
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
- **Modules:** a scenario lists the modules it needs, and its optional missions check `Module::isEnabled()` (ADR 0041). Engine change needed first: `Scenario::requiredModules()`, so the page hides a scenario whose modules are off (a known issue today).
- **Engine gaps, by the scenario that hits them:**
  - *Queued work is not recorded* (5: the CSV import, 3/6: absence SMS jobs). For imports, record the import's rows by the `imports` row made in the run, or run the import synchronously in tutorial mode.
  - *Time windows* (1, 3, 5, 6: attendance opens 15 minutes before a session; teachers' content and score deadlines). Sessions are scheduled a few minutes ahead; score periods are given dates around today.
  - *SMS codes* (2, 4: signing a contract, signing in with a code). With the `log` driver the step tells where to read the code (`storage/logs/sms.log`). A tutorial-only helper that shows the last code sent to a tutorial account would be friendlier.
  - *Scheduled tasks* (4: contract reminders). Not taught as waiting; the mission shows the list filter «پایان نزدیک» instead.
  - *Uploaded files stay on disk after a reset.* Accepted for now.
  - *Inertia pages have no auto-fill* (the public forms, «کلاس‌های من», `/exams`, `/contracts`): `perform()` covers them, and «پر کردن خودکار» for Vue forms is a later engine task.

---

## 1. «آموزشگاه زبان با یک شعبه» (built)

Implemented; see [LanguageInstitute](../Modules/Tutorial/app/Scenarios/LanguageInstitute.php) and ADR 0040 for its 20 missions: a branch and a registrar; hiring the instructor through «همکاری با ما» with documents and a contract; admitting a learner through preregistration; a term, a class, members, a session and attendance.

Possible additions later: the instructor signing her contract in `/contracts`, and the learner seeing the session in «کلاس‌های من».

---

## 2. «مدیریت سامانه و دسترسی‌ها»

**Who it is for:** the person who installs or runs the system for the organization (super admin, head-office IT).
**Goal:** the organization's structure, who can see and do what, and how to check it. Everything else builds on this.
**Modules:** none beyond the core.
**Actors:** خودتان (مدیر کل سیستم), «مدیر شعبه» (an account made in the scenario).

**Sample data:** branches «شعبه شرق» (`east`) and «شعبه غرب» (`west`); users «مریم صادقی» (branch manager) and «حمید نوری» (staff in both branches); a custom role «مسئول کتابخانه».

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. ساختار سازمان** |||||
| 1 | Create two branches | you | «شعبه‌ها» | 2 tenants made in the run |
| **B. کاربران و نقش‌ها** |||||
| 2 | Make the branch manager: user «مریم صادقی» with the default role «مدیر {tenant_label}» in «شعبه شرق» | you | «کاربران» (tenant «شعبه شرق») | the user holds `tenant_manager` there |
| 3 | Make a custom role «مسئول کتابخانه» with a few permissions | you | «نقش‌ها» | a role made in the run with ≥ 1 permission |
| 4 | Add «حمید نوری» to «شعبه شرق» with «کارمند», then add the same person to «شعبه غرب» with «مسئول کتابخانه» (one person, roles per tenant); then find him in «نقشه‌ی دسترسی», user × tenant view | you | «کاربران» in each tenant | the user holds roles in both tenants |
| **C. از نگاه دیگران** |||||
| 5 | Sign in as «مریم صادقی» and see that she only sees her branch, cannot give «مدیر ستاد» and cannot edit a super admin (rule of ADR 0003). She adds a «کارمند» of her own | branch manager | «کاربران» | a user made by her (role assignment with her as the actor) in «شعبه شرق» |
| 6 | Back to yourself; deactivate «شعبه غرب» and see it leave the tenant menu and the site | you | «شعبه‌ها» | the run's `west` tenant is inactive |
| **D. فرایندها و کارتابل** |||||
| 7 | A two-step workflow for job applications («درخواست همکاری»): step 1 role «مدیر شعبه» in «شعبه شرق», step 2 yourself; allow «برگشت» and «رد» in step 2 | you | «کارتابل → فرایندها» | workflow with 2 steps and the actions |
| 8 | Start a case (e.g. an application on `/careers`, through `perform()` when needed) | applicant | public form | a case of the workflow |
| 9 | As «مریم صادقی»: claim it, write a note, approve | branch manager | «کارتابل من» | the case past step 0 with a note |
| 10 | As yourself: return it to step 1 with a reason, then (as her) approve again | you / manager | «کارتابل من» | the case history has a «برگشت» |
| 11 | Follow the case in «همه‌ی پرونده‌ها» and its «فلوچارت فرایند»; reject it with a reason. Closing steps: «ماژول‌ها» lists what each module does and needs (read only: switching a module is not a tutorial row and affects real users) | you | «کارتابل → همه‌ی پرونده‌ها» | the case is rejected |

**Notes for building:**
- Read-only parts (the primary tenant and `/{slug}`, the access map, the module manager) are steps inside missions that create something, since a page visit is not a row.
- Mission 5 needs the branch manager to have panel access («دسترسی به پنل مدیریت»).
- If Employee is off, the workflow in 7–11 needs a subject; today every subject comes from a module (job application, preregistration). Then the chapter is left out (ADR 0041).

---

## 3. «مدرسه: از پیش‌ثبت‌نام تا کارنامه»

**Who it is for:** school chains (the first deployment, سرای دانش).
**Goal:** a school year end to end: structure, admission with parents, classes and weekly timetable, attendance with SMS to parents, homework, scores, report card, and the parent's view.
**Modules:** Student, Class, School (+ Document for the birth certificate, + Exam for the optional chapter).
**Actors:** خودتان, «معاون آموزشی» (education officer), «معلم», «دانش‌آموز», «ولی» (parent), applicant (public form).

**Sample data:** school «دبیرستان نمونه» (`nemooneh`); academic year «سال تحصیلی ۱۴۰۵-۱۴۰۶» (term over the current dates); stage «متوسطه اول» with grade «هفتم»; subjects «ریاضی», «علوم»; teacher «نرگس مرادی»; student «امیر حسینی» with father «محمد حسینی»; class «هفتم الف».

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. ساختار مدرسه** |||||
| 1 | Create the school | you | «شعبه‌ها» | tenant made |
| 2 | Education officer «زهرا کاظمی» by quick entry, role «کارشناس آموزش», panel access | you | «کارکنان → کارمندان» | employee with `education_officer` + panel |
| 3 | Teacher «نرگس مرادی» by quick entry with role «مدرس», no panel access | you | same | employee with `instructor` |
| 4 | Stage «متوسطه اول» (score scale: number out of 20) and grade «هفتم» | you | «مدرسه → دوره‌های تحصیلی», «پایه‌ها و برنامه‌ی درسی» | stage and grade made |
| 5 | Subjects «ریاضی» and «علوم», and grade 7's curriculum (coefficients, weekly hours) | you | «کلاس‌ها → درس‌ها», the grade's curriculum | curriculum rows for the grade |
| 6 | Academic year as a term, and «تعطیلات» (one holiday next week) | you | «کلاس‌ها → دوره‌های آموزشی», «تعطیلات» | term and holiday made |
| **B. پیش‌ثبت‌نام و پذیرش** |||||
| 7 | Admission workflow: step «بررسی مدارک» by the education officer in the school | you | «کارتابل → فرایندها» | workflow for preregistrations |
| 8 | Program «پایه هفتم» offered in the school; in «تنظیم فرم پیش‌ثبت‌نام» turn on father/mother fields and «موبایل ورود», and add a field «معدل سال قبل» | you | «پیش‌ثبت‌نام → برنامه‌های آموزشی» | program + form customization with parents' fields on |
| 9 | (Document on) document type «شناسنامه» asked as required in the program's «فیلدهای فرم» | you | «مدارک → انواع مدرک», program row | type required on the program's form |
| 10 | Preregister «امیر حسینی» with the father's details and «موبایل ورود: پدر», upload the birth certificate | applicant | `/preregister` | preregistration (+ document) made |
| 11 | As the education officer: check the document, edit a field with «ویرایش اطلاعات», approve with role «فراگیر» | education officer | «کارتابل من» | student made; father is a guardian |
| 12 | See the learner page tabs «اولیا» and «پرونده‌ی تحصیلی» | you | learner page | (merge into 11's steps) |
| **C. کلاس و برنامه‌ی هفتگی** |||||
| 13 | Class «هفتم الف» with grade «هفتم», capacity 30 | you | «کلاس‌ها → کلاس‌ها» (school) | classroom with grade (ClassroomExtensions) |
| 14 | Members: the student as «فراگیر» (an academic record is filled in), the teacher as «مدرس» | you | class tab «اعضا» | members + enrollment record |
| 15 | Teaching plan «ریاضی هفتم» with three topics | you | «کلاس‌ها → طرح‌های درس» | plan with topics |
| 16 | Weekly schedule for «ریاضی» with the teacher and the plan, from today for four weeks; see the holiday skipped and the plan's forecast | you | class tab «برنامه» | schedule with weekly sessions |
| **D. کلاس در جریان** |||||
| 17 | As the teacher: in «کلاس‌های من» take attendance of the first session, mark the student «غایب» (the father gets an SMS: see `storage/logs/sms.log`) | teacher | `/my-classes` | an absence in the run's session |
| 18 | As the teacher: record the session content (topic done, summary) and homework due in 3 days | teacher | session page | session content with homework |
| 19 | As the student (signed in with the father's mobile): hand in the homework with a file | student | `/my-classes` | submission made |
| 20 | As the teacher: grade it with feedback | teacher | session page | submission graded |
| 21 | As the father: see «کلاس‌های امیر»: the absence, homework and score | parent | user panel | (merge into 20's steps, or a read-only tick) |
| **E. نمره و کارنامه** |||||
| 22 | Score period «نوبت اول» for the year, covering today, teachers' closing date in a week | you | «مدرسه → دوره‌های نمره‌دهی» | score period made |
| 23 | As the teacher: enter the student's score in «دفتر نمره» | teacher | user panel «دفتر نمره» | score made |
| 24 | Publish the report card («انتشار کارنامه») | you | score period row | period published |
| 25 | As the student / father: see «کارنامه» and download the PDF | student | user panel «کارنامه» | (read-only; merge into 24) |
| 26 | Promote the record to next year («ارتقا به سال بعد») | you | «پرونده‌های تحصیلی» | a record for the next year made |
| **F. آزمون (optional, Exam on)** |||||
| 27 | Question bank «ریاضی هفتم» with 3 choice questions and 1 written question | you | exam screens | bank + 4 questions |
| 28 | Exam drawing the 4 questions for role «فراگیر», open now for an hour | you | «آزمون‌ها» | exam made |
| 29 | As the student: take it in `/exams` | student | user panel | attempt submitted |
| 30 | Grade the written answer in «نتایج آزمون‌ها» | you | results | attempt fully graded |

**Notes for building:**
- The longest scenario. Split it into two runs if testing shows it is tiring: «مدرسه ۱: ساختار و پذیرش» (A–C) and «مدرسه ۲: سال تحصیلی» (D–F, starting from scenario 1's data is not possible: each run makes its own). Better: keep one scenario and rely on «انجام خودکار» to skip ahead.
- The student signs in with the father's mobile (ADR 0028), so «ورود با حساب …» uses impersonation as usual.
- The score period's dates must cover the session; the teacher's attendance window must be open (schedule the first session minutes ahead in `perform()`).
- Read-only missions (12, 21, 25) are merged into the previous mission's steps unless the engine gets a «خواندم» tick.

---

## 4. «کارگزینی: مدارک و قراردادها»

**Who it is for:** HR in any organization.
**Goal:** the full life of a staff member's paperwork: a hiring workflow across tenants, documents with expiry, contract types with their own fields, electronic and paper signatures, amendments, renewals and the reminders.
**Modules:** Employee, Document, Contract.
**Actors:** خودتان, «مدیر شعبه» (interviewer in the branch), applicant, the new employee.

**Sample data:** branch «شعبه مرکزی»; position «کارشناس مالی»; applicant «لیلا فرهادی»; document types «کارت ملی» (no expiry), «گواهی سلامت» (with expiry); contract type «قرارداد کار معین» with added fields «حقوق ماهانه» and «ساعت کار هفتگی»; amendment type «الحاقیه افزایش حقوق».

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. آماده‌سازی** |||||
| 1 | Branch and its manager with panel access (quick entry) | you | «شعبه‌ها», «کارمندان» | tenant + manager |
| 2 | Document types «کارت ملی» and «گواهی سلامت» (with expiry) | you | «مدارک → انواع مدرک» | 2 types, one with `has_expiry` |
| 3 | Contract type «قرارداد کار معین»: text with merge tags (name, national code, start/end, amount in words); in «فیلدهای قرارداد» make the amount required and add «حقوق ماهانه», «ساعت کار هفتگی» | you | «قراردادها → انواع قرارداد» | type with a form customization and added fields |
| 4 | Amendment type «الحاقیه افزایش حقوق» («الحاقیه است») | you | same | type with `is_amendment` |
| **B. استخدام** |||||
| 5 | «تنظیم فرم همکاری با ما»: make «سوابق کاری» optional and add the field «سابقه‌ی نرم‌افزار حسابداری» | you | «همکاری با ما → موقعیت‌های شغلی» | whole-form customization |
| 6 | Hiring workflow: «بررسی مدارک» (you, check «کارت ملی»), «مصاحبه» (branch manager, in the branch; allow «برگشت»), «تنظیم قرارداد» (you, prepare the contract type), with «ویرایش اطلاعات» allowed in step 1 | you | «کارتابل → فرایندها» | workflow with the three steps and settings |
| 7 | Position «کارشناس مالی» with the workflow; in its «فیلدهای فرم» ask «کارت ملی» as required and «گواهی سلامت» as optional | you | positions | position + its customization |
| 8 | Apply as «لیلا فرهادی» with the documents | applicant | `/careers` | application + documents |
| 9 | Check «کارت ملی», correct a typo with «ویرایش اطلاعات», approve | you | «کارتابل من» | document approved, case at step 1, an edit in history |
| 10 | As the branch manager: interview, return to step 1 with a note (a missing document), then you approve again, then the manager approves | manager / you | «کارتابل من» | case history has a return, case at step 2 |
| 11 | Prepare the contract: dates, amount, monthly salary, hours; read «متن قرارداد» | you | «کارتابل من» | draft with the answers |
| 12 | Final approval: hire into «شعبه مرکزی» with role «کارمند» and panel access off; the contract is issued and an SMS is sent | you | «کارتابل من» | employee made, contract issued |
| **C. پرونده‌ی کارمند** |||||
| 13 | On the employee page, tab «مدارک»: upload «گواهی سلامت» with an expiry date next month, approve it | you | «کارکنان → کارمندان» → «مشاهده» | document approved with `expires_at` |
| 14 | As the employee: sign the contract in `/contracts` with the SMS code | employee | user panel | contract signed electronically |
| 15 | Amendment «الحاقیه افزایش حقوق» on the signed contract (numbered `…/1`), issue it, record a paper signature with a scan | you | «قراردادها → قراردادها» | amendment signed on paper |
| 16 | Renew the contract («تمدید»): a draft starting the day after it ends; issue it | you | same | renewal issued |
| 17 | A second contract ending within 30 days, and see the filter «پایان نزدیک» and the coloured end date (the reminder itself comes from the scheduler at 08:00) | you | same | a contract ending soon |
| 18 | Cancel the last one with a reason | you | same | cancelled contract |

**Notes for building:**
- Scenario 1 already does a lighter hiring; this one adds returning, editing, roles in another tenant, expiry, amendments, renewals and both signatures.
- Mission 14 needs the SMS code (see the engine gaps). `perform()` uses `Contracts::signElectronically()`.
- Mission 15's scan is a sample file, like the degree in scenario 1.

---

## 5. «آموزش سازمانی کارکنان»

**Who it is for:** companies running internal training (TMS).
**Goal:** departments as tenants, staff loaded in bulk, a training course with online sessions, attendance, an exam at the end with a pass score, and results.
**Modules:** Employee, Class, Exam.
**Actors:** خودتان, «مدیر آموزش» (training manager), «مدرس دوره», «کارمند (فراگیر)».

**Sample data:** departments «واحد فروش», «واحد فناوری»; course «آشنایی با امنیت اطلاعات»; trainer «کاوه رحیمی»; trainees from a CSV of 5 employees.

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. سازمان و کارکنان** |||||
| 1 | Two departments as tenants (the steps explain «شعبه» is renamed per installation: `tenant_label`) | you | «شعبه‌ها» | 2 tenants |
| 2 | Training manager by quick entry in the head office, role «کارشناس آموزش», panel access | you | «کارمندان» | employee with the role |
| 3 | Import 5 employees into «واحد فروش» from the example CSV, with default role «فراگیر» (download the example, fill it, upload; see a failed row and why) | you | «کارمندان» → «ورود از فایل» | 5 employees in the tenant (engine gap: queued) |
| 4 | Trainer «کاوه رحیمی» by quick entry, role «مدرس» | you | «کارمندان» | employee with `instructor` |
| **B. دوره‌ی آموزشی** |||||
| 5 | Term «دوره‌های آموزشی پاییز» (a training round) | training manager | «کلاس‌ها → دوره‌های آموزشی» | term |
| 6 | A class role «ناظر دوره» that takes attendance, limited to «کارشناس آموزش» | training manager | «کلاس‌ها → نقش‌های کلاس» | class role with the flags |
| 7 | Class «امنیت اطلاعات - گروه ۱» in «واحد فروش», mode «آنلاین» with a meeting link, capacity 10 | training manager | «کلاس‌ها» | online classroom |
| 8 | Add the 5 trainees («فراگیر»), the trainer and yourself as «ناظر دوره» | training manager | tab «اعضا» | members by role |
| 9 | Daily schedule for three days with the trainer | training manager | tab «برنامه» | 3 sessions |
| 10 | As the trainer: open the session in «کلاس‌های من», take attendance (one «با تأخیر» 10 minutes), record content and a file | trainer | `/my-classes` | attendance + content |
| **C. آزمون پایان دوره** |||||
| 11 | Question bank «امنیت اطلاعات» with 5 choice questions (one with negative marking) | training manager | exam screens | bank + 5 questions |
| 12 | Exam «آزمون پایان دوره»: 5 questions from the bank, for the class's trainees by role «فراگیر» in «واحد فروش», 20 minutes, one retake, results shown after the window | training manager | «آزمون‌ها» | exam with participants and rules |
| 13 | As a trainee: take the exam; leave one unanswered; submit | trainee | `/exams` | attempt submitted |
| 14 | As another trainee: start and let the time run out (or submit after the grace minutes) | trainee | `/exams` | timed-out attempt (or merge into 13) |
| 15 | Results: see scores and, as a trainee, the result once allowed | training manager | «نتایج آزمون‌ها» | attempts graded |
| **D. جمع‌بندی** |||||
| 16 | Close the course: set the trainees' leave date, deactivate the class | training manager | class | members left, class inactive |
| 17 | (Contract on, optional) A training-commitment contract «تعهدنامه‌ی آموزشی» for one trainee, signed on paper | you | employee page → «قراردادها» | contract signed |

**Notes for building:**
- Mission 3 is the engine's queue gap: the import runs on the queue, so its employees are not recorded. Decide before building (see the rules above).
- The time-out in mission 14 is closed lazily (no scheduler); `perform()` makes an attempt whose start is older than the duration.
- Department heads as tenant managers and per-department reports would be natural later missions; there are no reports yet.

---

## 6. «اداره‌ی روزانه‌ی کلاس‌ها در چند شعبه»

**Who it is for:** institutes and schools with several branches, after scenario 1 or 3.
**Goal:** the everyday changes: programs offered in some branches, monthly and weekly timetables, clashes, holidays, moving and cancelling sessions, substitutes, make-ups, sub-classes, capacity, learners leaving.
**Modules:** Student, Class.
**Actors:** خودتان, «کارشناس آموزش» of a branch, teachers.

**Sample data:** branches «شعبه پونک», «شعبه تجریش»; programs «مکالمه‌ی پیشرفته» (both) and «IELTS» (تجریش only); teachers «بهاره امینی», «رضا توکلی»; classes «Conversation B2», «IELTS Prep».

| # | Mission | Actor | Where | Done when |
|---|---|---|---|---|
| **A. شعبه‌ها و برنامه‌ها** |||||
| 1 | Two branches and an education officer in each (quick entry) | you | | 2 tenants + officers |
| 2 | Programs: «مکالمه‌ی پیشرفته» in both branches, «IELTS» only in «شعبه تجریش»; see `/preregister` offer each where it is given | you | «برنامه‌های آموزشی» | programs with availability |
| 3 | A preregistration form field for one program only («سطح فعلی زبان», single choice) in its «فیلدهای فرم» | you | program row | program customization |
| 4 | Two learners admitted (via `/preregister` and the cartable, or `perform()`), one per branch | applicant / officer | | 2 students |
| **B. برنامه‌ی کلاس‌ها** |||||
| 5 | Class «Conversation B2» in پونک, capacity 2, and its sub-class «Conversation B2 - گروه ب» (members come from the parent class) | officer | «کلاس‌ها» | class + child class |
| 6 | Try to add a third learner past capacity and read the refusal; add the two learners | officer | tab «اعضا» | 2 attendee members |
| 7 | Monthly schedule: «دومین دوشنبه‌ی هر ماه», for three months | officer | tab «برنامه» | monthly schedule |
| 8 | Weekly schedule Sat/Mon/Wed for the same teacher; try a time clashing with the teacher's class in the other branch and read the refusal; then a free time | officer | tab «برنامه» | weekly schedule saved |
| 9 | A branch holiday next week: the schedules rebuild without it | officer | «تعطیلات» | holiday + a session removed |
| **C. تغییرات روزمره** |||||
| 10 | Move a session to another day and room | officer | tab «جلسه‌ها» | moved session (`original_starts_at`) |
| 11 | Give a session a substitute teacher | officer | same | session with a substitute |
| 12 | Cancel a session with a reason, then add a make-up session | officer | same | cancelled + make-up session |
| 13 | As the substitute: take attendance of that session in «کلاس‌های من» | substitute | `/my-classes` | attendance by the substitute |
| 14 | See all of the branch's upcoming sessions in «کلاس‌ها → جلسه‌ها» and the absences per learner in «اعضا» | officer | | (merge into 13) |
| 15 | A learner leaves: set a leave date | officer | tab «اعضا» | member with `left_on` |
| 16 | Deactivate the program for new preregistrations | you | programs | program inactive |

**Notes for building:**
- Mission 8's refused clash is taught in the steps; the check only sees the saved schedule.
- Mission 16 changes a row made in the run (allowed).

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

1. Engine first: `Scenario::requiredModules()`, a read-only «خواندم» tick (optional), and a decision on queued imports.
2. Scenario 2 «مدیریت سامانه و دسترسی‌ها»: small, core only, needed by every client.
3. Scenario 3 «مدرسه»: the first real deployment.
4. Scenario 4 «کارگزینی».
5. Scenario 6, then 5, then 7.
