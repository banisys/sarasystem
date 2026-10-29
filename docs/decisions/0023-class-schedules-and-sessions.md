# 0023 — Class schedules and sessions

- **Date:** 2026-10-29
- **Status:** accepted

## Context
This is step 2 of the classes plan ([ADR 0022](0022-classes.md)): when a class meets, and the sessions that come from it. Later steps attach attendance and session content to these sessions.

The legacy LMS spreads this over several tables:
- `schl_classroom_plans`: class + subject + teacher, with a day and a time.
- `schl_weekly_schedules`: the same days and times again; every week, odd weeks or even weeks.
- `schl_activity_sessions`: the sessions.

It builds sessions only for online (Adobe Connect) schedules, by deleting and rebuilding every future session when a schedule is saved. Sessions changed by hand are lost.

The user wants:
- Daily, weekly and monthly programs, and any of them for a class.
- A session for every meeting, in person or online, built when the time comes.

## Options considered
- **Repeat rules:**
  - Fixed weekly slots (LMS).
  - Full iCalendar RRULE: too much for the admin form.
  - **Our own small rule:** once / daily / weekly / monthly, «every N», weekdays, and for months a day of the month or the nth / last weekday. Weeks start on Saturday and months are Jalali months, which RRULE does not do.
- **When sessions are made:**
  - A daily command building the next N days.
  - **Building every session up to the schedule's last day when the schedule is saved.** Schedules always have an end (by default the class's), so there is nothing open-ended to extend. Planned sessions are visible at once, and clashes can be checked against real rows.
  - A holiday added or removed rebuilds the schedules it touches, so no command is needed.
- **Rebuilding:**
  - Delete and recreate (LMS).
  - **Match by date.** For each future day the schedule still has:
    - an untouched session is updated;
    - a session changed by hand (moved, cancelled, substitute, note, make-up / extra) is kept;
    - a day that is gone loses its untouched or cancelled session;
    - a missing day gets a new session.
  - Past sessions are never touched.
- **Clashes:** warn / **refuse**. A schedule, a hand-made session, a move or a substitute is refused when any of these is busy at the time:
  - the teacher, in any tenant (as the substitute, or as the teacher without a substitute);
  - the same class;
  - the same place in the tenant (compared as text, ignoring case).

  Cancelled sessions don't count.
- **Subjects:** a free text per schedule / **a list defined in the primary tenant** (like terms and class roles). The School module can add grades and coefficients to it later.
- **Teacher:** any user of the tenant / **a current class member whose class role is not an attendee role.** The substitute for one session can be anyone of the tenant.

## Decision
- **Tables:**
  - `class_subjects` (`Subject`): primary tenant.
  - `class_schedules` (`ClassSchedule`): the class, optional subject and teacher, and the rule:
    - `frequency`, `interval`, `weekdays` (Carbon day numbers), `month_day`, `month_week`
    - first and last day, start and end time, and an optional place instead of the class's.
  - `class_sessions` (`ClassSession`):
    - class, schedule (null for hand-made sessions and after the schedule is deleted), subject, teacher, substitute, start / end, place
    - kind: regular / make-up / extra
    - status: scheduled / held / cancelled (held is set by attendance in step 3)
    - cancel reason, `is_modified`, `original_starts_at` (the time before the first move), note.
- **Services:**
  - [Recurrence](../../Modules/Class/app/Services/Recurrence.php) gives a rule's days. A month day past the month's end falls on the last day.
  - [SessionPlanner](../../Modules/Class/app/Services/SessionPlanner.php) checks, saves and builds schedules (at most 1000 sessions each), skips the holidays that apply in the class's tenant, checks clashes, and moves, cancels, restores and substitutes sessions.
- **Holidays:** `Holiday` saved / deleted → `SessionPlanner::holidayChanged()`. The primary tenant's holidays rebuild every tenant's schedules in their range.
- **Admin:**
  - On the class page:
    - tab «برنامه»: add / edit / delete schedules, with a one-line summary per schedule;
    - tab «جلسه‌ها»: the class's sessions with row actions («جابه‌جایی»، «مدرس جایگزین»، «لغو جلسه»، «برگرداندن جلسه»، «حذف» for hand-made ones not started yet) and «جلسه‌ی جبرانی / فوق‌العاده».
  - Menu:
    - «کلاس‌ها → جلسه‌ها»: every session of the tenant, from today by default, filterable by dates, class, teacher, subject, status and kind;
    - «کلاس‌ها → درس‌ها»: primary tenant only.
  - Changing schedules and sessions needs `Update:Classroom` on the class.

## Consequences
- A schedule's sessions exist up to its end, so the sessions list and the clash check see the whole term. A long daily schedule makes many rows; the 1000-session limit keeps a single schedule in check.
- Editing a schedule never loses what someone did to a session by hand. A session moved to another day still counts for its original day, so the schedule doesn't add it again.
- A place clash is only as good as the place text: «کلاس ۱۰۱» and «کلاس 101» are different places. Rooms as records can come later if needed.
- A teacher clash is checked across tenants, so a teacher working in two branches can't be booked twice. In the local demo data one teacher works in two branches, so the demo seeder skips two clashing schedules with a warning.
- No time zone handling beyond the app's (Asia/Tehran).
