# 0025 — Teaching plans and session content

- **Date:** 2026-10-29
- **Status:** accepted

## Context
This is step 4 of the classes plan ([ADR 0022](0022-classes.md)): what each session is meant to cover and what it did cover, with homework and materials. The user asked that the topics of each session be planned ahead and recorded.

The legacy LMS spreads this over several tables:
- `teaching_plans`: per course and grade, a list of session numbers.
- `course_headlines`: chapters with page ranges.
- `schl_classroom_plan_reports`: the current page of the book on a date.
- `homework` / `homework_students`.

It hands the plans out to future sessions once, in order (`ScheduleSessionsBuilder::assignTeachingPlans`), so a topic that is not finished doesn't move on, and rebuilding loses the assignment.

## Options considered
- **Where the plan lives:** per class / **once in the primary tenant**, chosen per schedule, like terms, class roles and subjects. A plan is a course outline that many classes follow. A plan can be tied to a subject so that schedules of that subject suggest it.
- **Topics over sessions:**
  - **Stored assignment** (LMS): a topic id on each future session. It must be redone whenever a session is cancelled, moved or added, or a topic is not finished.
  - **A forecast worked out when shown:** the topics the class has not finished, each repeated for the sessions it takes, laid in order over the sessions from today on. Only sessions of the schedules following the plan count, and only those that are not cancelled and have no content. Nothing goes stale, and an unfinished topic moves on by itself. The cost is a few queries per class, kept for the request (`PlanForecast` is scoped).
- **What «covered» means:** a topic is finished when a session records it as finished. Partial progress goes into the summary, and the topic stays in the forecast. Simple, and the teacher decides.
- **Homework:** text + due date + files on the session (this step) / submissions and scores per learner. Submissions belong to the learners' side and come with the user panel (step 5) or later.
- **Materials:** medialibrary on the session, on the private disk, with signed 30-minute links. This is the same as documents (ADR 0019): class files are not public.

## Decision
- **Tables:**
  - `class_teaching_plans` (`TeachingPlan`): primary tenant; title, optional subject, active.
  - `class_plan_topics` (`PlanTopic`): order, title, description, sessions it takes.
  - `class_schedules.teaching_plan_id`: the plan a schedule follows.
  - `class_sessions`: `covered_topic_ids`, `summary`, `homework`, `homework_due_on`, `content_recorded_by_id` / `_at`, and the medialibrary collection `materials`.
- **Services:**
  - [PlanForecast](../../Modules/Class/app/Services/PlanForecast.php): forecast per class, a schedule's progress («X از Y سرفصل»), the topics a session may record.
  - [SessionContentBook](../../Modules/Class/app/Services/SessionContentBook.php): who may record and until when, and recording it.
    - Who: the session's teacher or substitute, members whose class role records content, and class editors at any time; shared with attendance in [SessionAccess](../../Modules/Class/app/Services/SessionAccess.php).
    - When: teachers until the end of the 7th day after the session (`CLASS_CONTENT_LOCKS_AFTER_DAYS`).
    - Checks: at least a topic of the plan or a summary, and the due date not before the session.
    - It marks the session held. A session with content is not rebuilt by its schedule.
- **Admin:**
  - «کلاس‌ها → طرح‌های درس» (primary tenant only): the plan with its topics in a repeater.
  - The schedule form has «طرح درس» (the chosen subject's plans and general ones).
  - The «برنامه» tab shows the progress of each schedule's plan.
  - Session lists have a «مطالب» column: what was covered, or «برنامه: …» in grey.
  - Row action «مطالب جلسه»: topics finished (filled with the forecast), summary, homework, due date, files to add or remove.
  - Materials download at `/class-materials/{media}` (signed).

## Consequences
- Editing a plan's topics changes the forecast of every class following it at once. Topics a session already recorded keep their ids; a deleted topic simply drops out of the lists.
- Two schedules of a class following the same plan share its progress (e.g. a subject taught on two days).
- Learners and parents can't see content, homework or materials yet; the user panel (step 5) will show them, with the same signed links.
- No homework submissions or homework scores yet.
