# 0024 — Class attendance

- **Date:** 2026-10-29
- **Status:** accepted

## Context
This is step 3 of the classes plan ([ADR 0022](0022-classes.md)): the attendance of the sessions built in step 2 ([ADR 0023](0023-class-schedules-and-sessions.md)).

The legacy LMS keeps it in `schl_activity_session_attendance`:
- a yes/no `present` and an in-person / online `present_type`;
- staff and students in the same table (`user_role`);
- no lateness, no excused absence, no rule on who may record it or until when.

## Options considered
- **Statuses:** present / absent (LMS) / **present, late (with minutes), absent, excused**, each with an optional note. These four are what schools and institutes report on.
- **Who is on the list:** every current member / **members whose class role is an attendee role and who were in the class on the session's day** (joined on or before it, not left before it). Users who already have a record stay on the list after they leave, so past attendance can still be corrected.
- **Who takes it:**
  - only staff with `Update:Classroom`;
  - **the session's teacher or substitute, and current class members whose class role «حضور و غیاب می‌گیرد», plus whoever may edit the class.** This uses the class role flags from ADR 0022, and a substitute can take the attendance of the session they teach.
- **When:** any time / **from a few minutes before the start (15 by default) until the end of the third day after it, for teachers; whoever may edit the class, at any time.** Both numbers are in the module config (`CLASS_ATTENDANCE_OPENS_MINUTES_BEFORE`, `CLASS_ATTENDANCE_LOCKS_AFTER_DAYS`). Cancelled sessions take no attendance.
- **Teachers' own attendance:** a row for the teacher / **none: the session's teacher, or its substitute, is who taught it**. A session whose teacher doesn't come is cancelled, or gets a substitute.

## Decision
- **Table** `class_attendances` (`Attendance`): one row per session and user, holding:
  - the session's tenant and class (to count a member's absences);
  - status, late minutes (only for late), note;
  - who recorded it and when. Only a changed row gets a new recorder.
- **Service** [AttendanceRegister](../../Modules/Class/app/Services/AttendanceRegister.php):
  - `attendees()`;
  - `refusal()` / `canTake()`;
  - `record()`, which refuses users who are not on the list and marks the session «برگزارشده» (held). A held session is no longer rebuilt by its schedule.
- **Admin:**
  - Row action «حضور و غیاب» in a class's «جلسه‌ها» tab and in «کلاس‌ها → جلسه‌ها»: one row per attendee, filled with what was recorded, otherwise «حاضر».
  - The sessions lists show «حاضر X · غایب Y».
  - The «اعضا» tab shows each attendee's absences (and excused ones) and lateness in the class.
- **Demo:** the demo `teacher`, `supervisor` and `supporter` roles can view classes and the sessions list so they can take attendance.

## Consequences
- For now teachers take attendance in the admin panel. Seeing a class and the sessions list there lets them see every class of the tenant (read only), while the attendance action only appears on their own sessions. The user panel («کلاس‌های من», step 5) will give teachers and learners their own view.
- Nobody is told about an absence yet. SMS to the learner or a parent can be added on top of `record()` later.
- A member's counts are per class. There is no report across classes or terms yet.
