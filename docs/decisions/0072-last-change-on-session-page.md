# 0072 — Who last changed each part of a session

- **Date:** 2026-11-08
- **Status:** accepted

## Context
Since [ADR 0070](0070-class-staff-without-limits.md) every staff member of a class can change a session's attendance and content at any time, so the admin panel needs to show who did. The user asked for the session's page (`class-sessions/{id}`) to name, for each part, the last person who changed it, and said no history is needed.

## Options considered
- **A history table** (every change, with before / after): more than was asked, and grows with every save.
- **Only the last change (chosen):** a person and a time per part, overwritten each time.

## Decision
Each section of the page has a line «… : name · Jalali date ساعت HH:MM» as its description, hidden until someone has changed that part:
- **«مشخصات جلسه»:** «آخرین تغییر زمان، وضعیت یا مدرس‌ها». Comes from the new `class_sessions.changed_by_id` / `changed_at`, set by `SessionPlanner` when a session is moved, cancelled, restored or its teachers / substitutes are saved. The planner takes the signed-in user (`auth()`), so its callers didn't change.
- **«مطالب و تکلیف»:** «آخرین ثبت مطالب» from the existing `content_recorded_by_id` / `_at`, and «آخرین نمره‌دهی» from the latest `graded_by_id` / `graded_at` of its hand-ins.
- **«حضور و غیاب»:** «آخرین تغییر» from the latest `recorded_by_id` / `recorded_at` of its rows. A save that changes no row doesn't count, since unchanged rows are not written.

The same lines show wherever these sections are shown, e.g. the session's «جزئیات» modal.

## Consequences
- Nothing older than the last change is kept, so who cancelled a session is lost once someone restores it.
- A change made outside a request (queue, console) records no person; the line then says «نامشخص».
- Learners' hand-ins are not counted as changes to the session.
