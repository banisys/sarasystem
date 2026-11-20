# 0084 — Exam sessions in a class timetable

- **Date:** 2026-11-12
- **Status:** accepted

## Context
Class editors need to plan several exams with individual dates and times from the class timetable. A sitting may be in person or use an online exam.

## Options considered
- Add recurrence and exam settings to teaching schedules. This would reuse the schedule table, but each exam would need its own rule and could be rebuilt when a schedule or holiday changes.
- Keep a separate exam timetable. This would need its own calendar, clash checks and session management.
- Add dated exam sessions directly to the class calendar. This reuses the session lifecycle and supports different details for each sitting.

## Decision
«افزودن برنامه امتحانی» records one or more individually dated `ClassSession` rows of kind `exam`. Each row has a Jalali date, start/end times and its own delivery mode ([ADR 0115](0115-session-delivery-mode.md)). Subject, invigilators, location and notes are optional. Invigilators can come from any branch ([ADR 0085](0085-exam-invigilators.md)). In-person rows use the class's location by default. The existing class, teacher and location clash checks apply, including between rows. A conflict rolls back the whole submission.

«افزودن برنامه امتحانی» is on the class calendar under [ADR 0109](0109-exam-scheduling-from-class-calendar.md). Each sitting opens its dedicated details page ([ADR 0086](0086-exam-session-page.md)). The session is independent of teaching recurrence and can be scheduled on a holiday or outside the teaching schedule.

## Consequences
The admin calendar marks exams with a purple label or icon on session cards and a purple diamond in the year and mini-month views. Status colours still indicate the state of each session. The session kind filter includes «امتحانی». Timetable planning works with the Exam module disabled and uses the existing session table.
