# 0066 — Deleting sessions that haven't been held

- **Date:** 2026-11-07
- **Status:** accepted

## Context
«حذف» was offered only for hand-made sessions that hadn't started ([ADR 0023](0023-class-schedules-and-sessions.md)). A session built from a schedule could only be cancelled, which leaves it on the calendar. Users want to remove any session that hasn't been held.

A schedule adds every planned day that has no session, so a deleted session would come back the next time the schedule is edited or a holiday touching it changes.

## Options considered
- **What may be deleted:**
  - **Everything not marked held, past ones too:** also clears old unrecorded sessions, but a past session may really have taken place, with the teacher still allowed to record it.
  - **Not started, not held, no content:** chosen. It covers upcoming and cancelled future sessions and destroys no record. Attendance can be taken from 15 minutes before the start and marks the session held, so the start time alone is not enough.
- **Keeping it from coming back:**
  - **Soft deletes on sessions:** every session query would have to know about them, and the row would serve no other purpose.
  - **A cancelled row kept out of the lists:** the same, with a status that means something else.
  - **Days to skip on the schedule:** chosen. One column read in one place (`SessionPlanner::planned()`).

## Decision
- `ClassSession::canBeDeleted()`: not started, not held, no content recorded.
- `SessionPlanner::deleteSession()` deletes it and adds its day (the original day if it was moved) to `class_schedules.skipped_dates`.
- `planned()` leaves those days out, for building sessions and for the clash check.
- The «حذف» action needs `Update:Classroom` on the class, like the other changes to a session.

## Consequences
- A deleted session stays gone when the schedule is edited, including when its time changes. There is no way to bring it back through the schedule, or to see or clear the skipped days; the way back is a make-up or extra session by hand.
- A skipped date is only a date: if the schedule is later edited so that the day counts for another reason, it stays skipped.
- Deleting a session of a schedule with a teaching plan moves the plan's forecast along by itself, as nothing about the forecast is stored.
- There is no undo and no record of who deleted a session.
- Past sessions with nothing recorded (amber on the calendar) can't be deleted.
