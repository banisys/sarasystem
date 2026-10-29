# 0030 — Handing in homework and grading it

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Sessions carry homework (text, due date, materials) since [ADR 0025](0025-teaching-plans-and-session-content.md), but learners couldn't hand anything in. The user wants learners to hand in homework in «کلاس‌های من» ([ADR 0026](0026-my-classes.md)), and teachers to give a score and feedback.

The legacy LMS had `homework_students` (description, teacher's description, score, finished date) per homework; this is not ported.

## Options considered
- **What is handed in:** **an answer (text) and/or up to 5 files** (photos of a notebook, PDFs). At least one of them is needed.
- **Changing it:** once only / **until graded**. A learner fixes a mistake; once scored, the work is fixed.
- **Late work:** refuse after the due date / **take it and mark it «با تأخیر»** (submitted after the end of the due day). `CLASS_HOMEWORK_ACCEPTS_LATE=false` refuses instead.
- **Score scale:** fixed 0–20 / **per homework**: «نمره‌ی تکلیف از» on the session (1–100, default 20 from `CLASS_HOMEWORK_MAX_SCORE`), set with the homework. Schools score out of 20; institutes often out of 100. Decimal scores are allowed (two places).
- **Who grades, and when:** the same people who record the session's content: its teacher or substitute, members whose class role records content, and class editors. **No time limit**, since grading often comes days later.
- **Grading not-handed-in work:** only hand-ins get scores. «تحویل نداده» is shown next to the learners who didn't hand in; a zero for them can come later if wanted.
- **Files:** medialibrary on the private disk with signed 30-minute links, through the same route as session materials (`class.materials`, now also for hand-in files).

## Decision
- **Table** `class_homework_submissions` (`HomeworkSubmission`): session, class, learner, answer, `submitted_at`, score, feedback, who graded and when, files (collection `files`). One per session and learner. Plus `class_sessions.homework_max_score`.
- **Service** [HomeworkBook](../../Modules/Class/app/Services/HomeworkBook.php):
  - `submissionRefusal()` / `canSubmit()`: the session has homework and isn't cancelled; the user is one of its attendees (AttendanceRegister); the work is not graded; late work is accepted (or not, by config).
  - `submit()`: answer, new files, removed files.
  - `canGrade()` / `grade()`: score between 0 and the maximum. Grades are only for this session's hand-ins. An empty score and feedback take the grade back.
  - `maxScore()`.
- **User panel** (`Class::Session`):
  - The learner's «تکلیف شما»: hand in or update, with the time handed in, «با تأخیر», then the score and feedback.
  - A guardian sees the ward's, read only.
  - The grader's «تکالیف دریافتی»: every attendee with their answer and files or «تحویل نداده», and score and feedback fields.
  - Routes `my-classes.homework` and `my-classes.grades`.
  - The calendar card shows «تکلیف تحویل شد» or «نمره‌ی تکلیف: X از Y».
- **Admin panel:** the sessions lists get the column «تکلیف» («تحویل X · نمره Y») and the row action «تکالیف دریافتی» (answers, file links, «با تأخیر», score and feedback per hand-in). The content form gets «نمره‌ی تکلیف از».

## Consequences
- Homework scores are per session. There is no average per learner or class yet; a School module's report cards could use them.
- Learners and teachers are not notified of new hand-ins or grades; the calendar and session page show the state.
- A learner who joins the class after the session is not on that session's list, so they can't hand in its homework (same rule as attendance).
