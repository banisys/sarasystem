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
«افزودن برنامه امتحانی» records one or more individually dated `ClassSession` rows of kind `exam`. Each row has a Jalali date and start/end times. Subject, invigilators, location and notes are optional. Invigilators can come from any branch ([ADR 0085](0085-exam-invigilators.md)). The class's location is used by default when it meets in person. The existing class, teacher and location clash checks apply, including between rows. A conflict rolls back the whole submission.

The dedicated «برنامهٔ امتحانی» page ([ADR 0096](0096-separate-class-schedule-pages.md)) lists the class's past and upcoming exam sessions, with time, subject, invigilators, location, status and a link to the exam's dedicated page. The same sessions appear in the «جلسه‌ها» calendar ([ADR 0086](0086-exam-session-page.md)). The session is independent of teaching recurrence and can be scheduled on a holiday or outside the teaching schedule. When Exam is enabled, staff can attach an online exam from «آزمون‌های جلسه» under the existing rules in [ADR 0079](0079-exams-in-class-sessions.md).

## Consequences
The admin calendar marks exams with a purple label or icon on session cards and a purple diamond in the year and mini-month views. Status colours still indicate the state of each session. The session kind filter includes «امتحانی». Timetable planning works with the Exam module disabled and uses the existing session table.
