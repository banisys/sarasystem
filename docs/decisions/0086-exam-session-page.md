# 0086 — Dedicated page for exam sessions

- **Date:** 2026-11-12
- **Status:** accepted

## Context
Exam sessions share records, scheduling and attendance with teaching sessions. Staff need an exam-specific page when opening an exam from the calendar, including the room in 3D.

## Options considered
- Adapt the original session page at the same URL. This keeps one route, but gives the exam no address of its own.
- Add a route for exam sessions within the existing resource. This gives exam links a clear destination and retains the existing tenancy and authorization rules.

## Decision
Use `class-sessions/exams/{id}` in the admin panel. It accepts only exam sessions. The calendar and details actions link directly there; authorized requests for an exam at `class-sessions/{id}` redirect there. The dedicated page shows the schedule, location, invigilators, notes and attendance, with the session's existing scheduling actions and optional online exam integration.

Reuse the Campus 3D room and attendance data. It shows assigned invigilators in purple at the front and the exam's attendee statuses on their seats. Other class staff, such as supporters, do not appear in the exam room. It follows the same authorization and cancellation rules as the regular session scene. Seating is symbolic until an editor saves a plan ([ADR 0087](0087-exam-seating.md)).

## Consequences
Both pages use the same session records and write operations. Planning and attendance work with Campus and Exam disabled. Existing links to teaching sessions still open the teaching session page.
