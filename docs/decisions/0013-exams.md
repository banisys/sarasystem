# 0013 — Exams

- **Date:** 2026-10-23
- **Status:** accepted

## Context
The user asked to port the LMS `Exam` module as a module that works **on its own, with no dependency on other modules**. LMS has two exam systems in that module:

- **`schl_exam_*`** (`Models/Exam`). All the live data is here, but it is not online exams: 0 questions and 0 answers, and the ~240k "attempts" are scores that teachers entered for class activities (score, color, absence; the ones whose activity still exists are of type `FILE_EXAM`). Plus `schl_exam_balances` (student "تراز" per balance type and grade). All of it hangs on `School` (activities, classes, grades, students, the desk and homework).
- **`exam_*`** (`Models/CourseExam`, `ExamAttemptService`). A self-contained online exam with banks, four-option / descriptive questions, exam settings and a take-the-exam flow. Almost empty (2 exams).

The user also chose **manual grading of written answers** and **specific participants per exam**.

## Options considered
- **What to port:** both parts vs **only the online exam (`exam_*`)**. The `schl_exam_*` part is a class-activity gradebook and needs the School module (activities, classes, students), so it can't stand alone. It will come with School.
- **Options of a choice question:** a table of answer rows (LMS `exam_answers`) vs **a jsonb list on the question** (`{key, text, is_correct}`). Options are edited only together with their question, and attempts copy them anyway. The jsonb list needs no tenant column of its own or a relationship repeater.
- **What an attempt stores:** question ids + a JSON of served answers (LMS `exam_question_users`), with scoring reading the live questions, vs **one row per served question with a copy of it** (`exam_attempt_answers`: body, options in the order shown, correct option, score). With the copy, editing or deleting a question later doesn't change past results, and grading works per row.
- **Who may take an exam:** any logged-in user (LMS) vs **participants: users chosen by name and/or roles** (holders of the role in the exam's tenant). The user chose specific participants. Roles keep the list short for a whole group (e.g. «دانش‌آموز»), without depending on a class concept.
- **Saving answers:** only on submit (LMS: a closed tab or a timeout loses everything, and the time-out closes the attempt with no answers) vs **autosave while answering** (`PUT /exams/{exam}/attempt`), with the time-out closing the attempt with what was saved.
- **Closing attempts whose time is over:** a scheduled command vs **lazily**: when the participant opens the exam, when answers arrive, and when staff open the results list or the attempt. The initial implementation had no scheduler; a scheduler now exists, but exam expiry remains lazy.
- **User-panel navigation:** the core layout (AppLayout.vue) must not know modules (ADR 0005). So the core got a generic hook, `UserPanel::navigationUsing()`, shared as the `navigation` Inertia prop, like `PublicSite::navigationUsing()` (ADR 0012).

## Decision
- Module `Modules/Exam`. Tables: `exam_question_banks`, `exam_questions` (`type` choice/descriptive, `options` jsonb, `score`, `is_active`), `exam_bank_question` (pivot), `exams`, `exam_sections` (bank + `question_count` + `sort`), `exam_participant_users`, `exam_participant_roles`, `exam_attempts`, `exam_attempt_answers`. Banks, questions, exams, attempts and attempt answers use `BelongsToTenant`; the pivot/section tables are reached only through their tenant-owned parents.
- **Exam settings** kept from LMS, renamed: total and passing score, duration, grace minutes (LMS "tolerance"), max attempts, retake policy (always / only if not passed), questions per page, random questions, random options, negative marking (every N wrong cancel one right), result visibility (hidden / pass-fail / score / score with answers). **New:** publish flag and an optional time window (`starts_at`, `ends_at`); an attempt's deadline is its start + duration, but never after `ends_at`.
- **Starting** ([ExamTaking](../../Modules/Exam/app/Services/ExamTaking.php)): a lock on the exam row (no double start); each section takes `question_count` active questions of its bank (shuffled if set), skipping questions an earlier section already took; every served question is copied into `exam_attempt_answers`.
- **Scoring:** choice: full score if right, 0 if unanswered, `-score/N` if wrong with negative marking. Written: empty → 0 on submit, otherwise ungraded (`score` null) until staff grade it. While anything is ungraded the attempt is «در انتظار تصحیح» with no score; then `score = total_score × max(0, raw) / max_raw`, rounded to 2 decimals. Written questions count in `max_raw` (LMS left them out, since it never graded them).
- **Retakes:** blocked when finished attempts reach `max_attempts`; with "only if not passed" also when the last attempt passed or is still waiting for grading.
- **Participant side** (Inertia, `/exams`, `/exams/{exam}`): list, intro + start, the running attempt (paged questions, countdown, autosave, submit; auto-submit at 0), last result as allowed by the result visibility. Someone else's or unpublished exam → 404.
- **Admin** (group «آزمون»): «آزمون‌ها», «نتایج آزمون‌ها» (filter by exam/status; attempt page with the answers and a score field for written answers only: `Update:ExamAttempt`), «سؤال‌ها», «بانک‌های سؤال». Shield permissions `*:Exam`, `*:ExamAttempt`, `*:Question`, `*:QuestionBank`. Participants, banks and roles chosen in forms are validated against the current tenant on the server.
- **Core change:** `ResolveCurrentTenant` now runs before `SubstituteBindings` (middleware priority in `bootstrap/app.php`), so a tenant-owned model in a user-panel route (`{exam}`) is bound inside the current tenant. Before, binding ran with no tenant and the fail-closed scope gave 404.
- **Not ported:** the whole `schl_exam_*` part (activity scores, exam balances: with School); `score_notebook_status` / `exam_impacted_value` (report-card coupling: with School); per-question `time` (never used); answer types `relational` / `all_answers` (an option's text can say «همه‌ی موارد»); serving only the correct answer + 3 wrong ones (all options are served); bank links to languages, videos and courses; the attempt logs tables (always empty).

## Consequences
- An exam is not tied to a class or course. When School/courses arrive, they can point to an exam (or add a participant source) without changing this module.
- Questions are plain text for now: no images, formulas or rich text. Adding them means a richer `body` and copying it into attempts the same way.
- An abandoned attempt stays «در حال آزمون» until someone opens the exam or the results; a scheduled tenant-aware expiry command remains future work. The existing closure service now processes bounded batches (ADR 0078).
- Seeing an attempt's answers needs `Update:ExamAttempt` (the attempt page is an edit page).
- The `exam_*` LMS data (2 test exams) is not migrated.

## Lifecycle hardening
See [ADR 0078](0078-exam-attempt-consistency.md) for transactional save/submit/finish, repeat-safe finalization and bounded question/attempt reads.

## Class sessions
[ADR 0079](0079-exams-in-class-sessions.md) adds independent copies for class sessions through an optional host contract. Their participants come from session-date membership; standalone exams keep named/role participants. Session staff can review and grade that session's answers through the user panel without global `Update:ExamAttempt` permission.

## Fill-in-the-blank questions

[ADR 0083](0083-fill-in-the-blank-questions.md) adds a single inline blank with automatic grading against accepted answers copied into the attempt.

## Model answers for written questions

A written question can include an optional model answer. The attempt copies it alongside the question body, so staff see the same reference even if the question is edited later. Staff still assign the score. Participants see the reference in results only when the exam shows answers and retakes are closed.

[ADR 0080](0080-question-categories.md) adds a category tree for classifying and filtering questions in the admin panel. Exam sections still draw from question banks.

[ADR 0082](0082-question-tags.md) adds a tenant-owned tag catalog and multiple tags per question, with tag filtering in the admin panel.
