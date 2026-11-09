# 0046 — Sample class data, deleted in one go

- **Date:** 2026-11-03
- **Status:** accepted

## Context
To show a client how the system works, the person handing it over wants a class that looks lived in: learners, a teacher, a supporter, a weekly timetable, past sessions with attendance, content, homework and grades. They want to choose how much of each thing to make in a form (most inputs optional), and to delete everything one run of the form made with one button. It belongs with «آموزش گام‌به‌گام» in one menu group, since both are for teaching the client.

## Options considered
- **Where:** a new module (independent, but would copy the tutorial's recording and deleting) or **the `Tutorial` module** (chosen): same purpose and audience (super admin), and it reuses the recording and deletion of ADR 0040.
- **Marking the rows:** a flag or a name prefix on every table (invasive, misses rows modules make on their own) or **recording every row made while the batch is built** (chosen), like tutorial mode, but tied to the batch in the process instead of to the session.
- **Making the rows:** only through the class services (attendance through `AttendanceRegister` sends absence SMS) or **services where they keep the rules for what is made now, direct writes for the history** (chosen).

## Decision
- «آموزش سامانه → داده‌ی نمونه» ([SampleBatchResource](../../Modules/Tutorial/app/Filament/Resources/SampleBatches/SampleBatchResource.php), super admin, while Class is on, tenant-independent). «آموزش گام‌به‌گام» moved into the same group.
- **A batch** (`tutorial_sample_batches`, [SampleBatch](../../Modules/Tutorial/app/Models/SampleBatch.php)) has a code `SMP-0001`, which is also the class's code and appears in the titles of the term, subjects and teaching plans it makes. The form's values are kept without the password. Every row made while it is built is noted in `tutorial_sample_records` by [SampleRecorder](../../Modules/Tutorial/app/Services/SampleRecorder.php) (same `eloquent.created: *` listener as the tutorial). Building turns tutorial recording off, so a batch's rows never belong to a tutorial run too.
- **The form** ([ClassSampleForm](../../Modules/Tutorial/app/Filament/Resources/SampleBatches/Schemas/ClassSampleForm.php)): tenant; class title, mode, capacity, term (or a new one), grade (School on), weeks past and ahead, number of classes (1–5) and «مدرس و کادر مشترک», sub-classes; how many people for each class role (default 10 attendees, 1 teacher), guardians per learner (0–2), a password for the accounts; subjects, sessions a week, start time, length, teaching plan and topics, cancelled sessions; attendance with absence / lateness rates, content, homework, hand-in rates and grading.
- **What it makes** ([ClassSampleGenerator](../../Modules/Tutorial/app/Services/ClassSampleGenerator.php)), in one transaction: accounts with Persian names, valid national codes and **no mobile** (nothing is ever sent to them), each with the system role its class role allows (learner / instructor first, else staff); learner rows (Student on) and employee rows (Employee on); father / mother as guardians; the class with members through `ClassMembers`, sub-classes sharing out the learners; a subject, plan and weekly schedule per subject through `SessionPlanner::saveSchedule` (clash checks), each subject at its own time of day; past and future sessions created by the planner ([ADR 0075](0075-sessions-on-past-dates.md)), completed sessions filled directly with attendance (marked as notified, so no SMS later), content following the plan's topics, homework, hand-ins and grades; cancelled sessions with a make-up through the planner. No holidays: a holiday would change real classes of the tenant.
- **Several classes** in one batch (added 2026-11-03) share the term, subjects and teaching plans; each has its own learners, room / link and code (`SMP-0001-1`, titles «… الف», «… ب»). With shared staff (default) the teacher, supporter, … made for the first class are put in every class, so one teacher's «کلاس‌های من» is full. Every class meets at its own time of the day (class after class, subject after subject, 15 minutes apart from the start time), so a shared teacher has no clash; a batch whose sessions would run past midnight is refused. Counts in the form are per class.
- **Deleting** a batch ([SampleBatches](../../Modules/Tutorial/app/Services/SampleBatches.php)) deletes its rows newest first without events, then role assignments of its users, then the batch: the shared [RecordedRowsEraser](../../Modules/Tutorial/app/Services/RecordedRowsEraser.php), also used by «شروع دوباره» now. All or nothing.

## Consequences
- Rows made later with the sample class (e.g. attendance taken by hand) are not recorded; most go with the class by cascade. A real row pointing at a sample row without cascade (a real class in the sample term) stops the deletion with a message.
- The term, subjects and plans are made in the primary tenant and appear in every tenant's choices until the batch is deleted.
- Accounts have no mobile, so they can't sign in with an SMS code; with the form's password, or by impersonation.
- A large batch (200 learners, 26 weeks back) is built in the web request; there is no queue.
- Exams, score books and report cards are not generated yet.
