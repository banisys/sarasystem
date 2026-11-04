# 0048 — A teacher's or a learner's sessions on a calendar, from the employees / learners list

- **Date:** 2026-11-03
- **Status:** accepted

## Context
The user asked for an action on «کارکنان → کارمندان» that opens a page like the class page's «جلسه‌ها» calendar (ADR 0047), but with the chosen employee's sessions, and then the same on the learners list. The employees list belongs to the Employee module, the learners list to Student, and the sessions to Class. Each module must keep working when the others are off (ADR 0041).

## Options considered
- **A — Employee / Student query class sessions** — simple / they would depend on Class.
- **B — A core hook for actions on a person's row and page (staff or learner), filled by Class** — like `ModelRelationManagers` and `TenantResource::addRecordActionsUsing()` / one more hook.
- **C — A «جلسه‌ها» tab on the employee / learner page (`ModelRelationManagers`)** — no new hook / the user asked for a page from the list, and a full-width calendar is cramped inside the page's tabs.

## Decision
**B.**
- Core [PersonRecordActions](../../app/Filament/Support/PersonRecordActions.php): modules register actions for a kind of person, `STAFF` or `LEARNER`. The employees and learners tables (row actions) and their pages (header) add the actions of their kind. The action's record is the employee / learner row, and the person is its `user`.
- Class registers «جلسه‌های تدریس» for staff and «جلسه‌های کلاس» for learners. They link to pages of «کلاس‌ها → جلسه‌ها»: [TeacherSessions](../../Modules/Class/app/Filament/Resources/ClassSessions/Pages/TeacherSessions.php) at `class-sessions/teachers/{user}` and [LearnerSessions](../../Modules/Class/app/Filament/Resources/ClassSessions/Pages/LearnerSessions.php) at `class-sessions/learners/{user}`. Both are built on [PersonSessions](../../Modules/Class/app/Filament/Resources/ClassSessions/Pages/PersonSessions.php).
  - Access: the sessions list's permission (`ViewAny:ClassSession`).
  - The person must belong to the current tenant, or have had sessions there before, otherwise the page returns 404.
  - Full width.
- The calendar is the same as the class tab's. The shared Livewire parts (view and date in the URL, date picker, details modal) moved to the trait [ShowsSessionCalendar](../../Modules/Class/app/Filament/Concerns/ShowsSessionCalendar.php). `SessionCalendar::forClassroom()`, `forTeacher()` and `forLearner()` share one layout for any query of sessions.
- A teacher's sessions are those the person **actually teaches** in the current tenant (`ClassSession::taughtBy()`): as substitute, or as teacher when there is no substitute. A session handed to a substitute counts for the substitute, not for the original teacher. «کلاس‌های من» (ADR 0026) shows both of them.
- A learner's sessions (`ClassSession::attendedBy()`) follow the attendance list (`AttendanceRegister::attendees()`): the sessions of classes where their class role is an attendee role, between their join and leave dates, plus any session where their attendance was taken. «کلاس‌های من» shows all sessions of all their classes, whatever the role or the dates.
- Each session shows its class and its class's mode. A teacher's calendar leaves out the teacher (the page's person). A learner's calendar keeps the teacher and adds the learner's own attendance on each session. Its legend counts their attendance for the period by status.
- These pages have no «جلسه‌ی جبرانی / فوق‌العاده» button: sessions are added in a class.
- Figures:
  - The page's subheading has the number of sessions in the tenant.
  - Every calendar's legend shows «ساعت برگزارشده» of the period against the planned hours (all but the cancelled).

## Consequences
- Another module with people's rows can show these actions by spreading `PersonRecordActions::for(...)` of the right kind.
- The actions are shown for every employee and learner. For someone with no sessions, the page says so instead of showing an empty calendar.
- Sessions in other tenants aren't shown; the admin sees only the current tenant's.
- A learner's month and year views show the sessions but not their attendance; it is only on the day and week cards (the details modal counts the whole class's attendance).
