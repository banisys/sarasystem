# 0119 — Staff reminders for upcoming sessions

- **Date:** 2026-11-21
- **Status:** accepted

## Context

Staff need to leave a short note for a later session and find it under that session's details when they return to teach or supervise it.

## Options considered

- **A — One note field on the session.** Simple to read, but one person could overwrite another's reminder and the note would lose its origin.
- **B — A separate reminder for each submission.** Preserves the author, source session and date, and lets more than one staff member leave a note.

## Decision

Use B. [SessionReminders](../../Modules/Class/app/Services/SessionReminders.php) supplies the timeline in the «یادآوری‌های جلسه» tab. It initially shows the open session and up to three later sessions still scheduled for that class and subject. Staff can reveal earlier sessions and more upcoming sessions in groups of three. The buttons refresh the timeline through the existing session page so it applies the same access checks on every request. Earlier sessions are available for reading reminders. The term is the class's own term. When the open session has no subject, the timeline includes the class's other sessions without a subject.

Each timeline row uses the session's Jalali date as its accordion heading. Opening a row shows that session's reminders and, for a later session, the note form. Read and write access follow the actual session assignment or a current non-attendee class role. The source and target must both be accessible to the author. The ward view carries no reminders. Staff may add a note only to a later session that has yet to start. When that session is opened, its reminders are available in its own row.

## Consequences

Reminders are plain text stored against the target session. Moving that session keeps its notes. Deleting it deletes its reminders. Deleting the source or author leaves the note and clears the corresponding reference.
