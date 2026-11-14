# 0097 — Edit class exam sessions from the schedule

- **Date:** 2026-11-14
- **Status:** accepted

## Context
Class editors need to correct an existing exam sitting directly from «برنامهٔ امتحانی».

## Options considered
- Use the session detail page's move action and handle other corrections separately.
- Reuse the exam scheduling fields in one edit form and save the existing session and invigilators in one transaction.

## Decision
The exam schedule offers «ویرایش» to class editors. They can edit the subject, invigilators, location and notes on a session that has not been cancelled. Changing the date or either time requires a scheduled session that has not started and a new start in the future. An editor must restore a cancelled session before editing it.

## Consequences
The edit keeps the session ID, attendance and seating plan. Changes to the time, room or invigilators are checked for clashes and saved together. The change is attributed to the editor. Linked online exams keep their own time window under [ADR 0079](0079-exams-in-class-sessions.md).
