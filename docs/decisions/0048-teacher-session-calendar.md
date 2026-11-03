# 0048 — A teacher's sessions on a calendar, from the employees list

- **Date:** 2026-11-03
- **Status:** accepted

## Context
The user asked for an action on «کارکنان → کارمندان» that opens a page like the class page's «جلسه‌ها» calendar (ADR 0047), but with the chosen employee's sessions. The employees list belongs to the Employee module and the sessions to the Class module. Each module must keep working when the other is off (ADR 0041).

## Options considered
- **A — The Employee module queries class sessions** — simple / Employee would depend on Class.
- **B — A core hook for actions on a staff member's row and page, filled by Class** — like `ModelRelationManagers` and `TenantResource::addRecordActionsUsing()` / one more hook.
- **C — A «جلسه‌ها» tab on the employee page (`ModelRelationManagers`)** — no new hook / the user asked for a page from the list, and a full-width calendar is cramped inside the employee page's tabs.

## Decision
**B.**
- Core [StaffRecordActions](../../app/Filament/Support/StaffRecordActions.php): modules register actions. The employees table (row actions) and the employee page (header) add them. The action's record is the employee row, and the person is its `user`.
- Class registers «جلسه‌های تدریس». It links to [TeacherSessions](../../Modules/Class/app/Filament/Resources/ClassSessions/Pages/TeacherSessions.php), a page of «کلاس‌ها → جلسه‌ها» at `class-sessions/teachers/{user}`.
  - Access: the sessions list's permission (`ViewAny:ClassSession`).
  - The person must belong to the current tenant, or have taught there before, otherwise the page returns 404.
  - Full width.
- The calendar is the same as the class tab's. The shared Livewire parts (view and date in the URL, date picker, details modal) moved to the trait [ShowsSessionCalendar](../../Modules/Class/app/Filament/Concerns/ShowsSessionCalendar.php). `SessionCalendar::forTeacher()` and `forClassroom()` share one layout for any query of sessions.
- Which sessions: those the person **actually teaches** in the current tenant (`ClassSession::taughtBy()`): as substitute, or as teacher when there is no substitute. A session handed to a substitute counts for the substitute, not for the original teacher. «کلاس‌های من» (ADR 0026) shows both of them.
- Each session shows its class (in the place of the teacher, who is the page's person) and its class's mode. The page has no «جلسه‌ی جبرانی / فوق‌العاده» button: sessions are added in a class.
- Figures:
  - The page's subheading has the number of sessions in the tenant.
  - Both calendars' legend shows «ساعت برگزارشده» of the period against the planned hours (all but the cancelled).

## Consequences
- Another staff-like module can show these actions by spreading `StaffRecordActions::make()`. A learner calendar would need its own query (class membership, not teaching), so learners don't get this action.
- The action is shown for every employee. For someone who never taught, the page says so instead of showing an empty calendar.
- Sessions taught in other tenants aren't shown; the admin sees only the current tenant's.
