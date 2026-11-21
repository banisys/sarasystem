# 0119 — Staff reminders for upcoming sessions

- **Date:** 2026-11-21
- **Status:** accepted

## Context

Staff need to leave a short note for a later session and find it under that session's details when they return to teach or supervise it.

## Options considered

- **A — One note field on the session.** Simple to read, but one person could overwrite another's reminder and the note would lose its origin.
- **B — A separate reminder for each submission.** Preserves the author, source session and date, and lets more than one staff member leave a note.

## Decision

Use B. [SessionReminders](../../Modules/Class/app/Services/SessionReminders.php) supplies the timeline in «جزئیات جلسه». It starts with the open session and includes later sessions still scheduled in the same branch, subject and term. When the open session has no subject or term, the timeline stays in its class and matches the subject if present.

Read and write access follow the actual session assignment or a current non-attendee class role. The source and target must both be accessible to the author. The ward view carries no reminders. Staff may add a note only to a later session that has yet to start. When that session is opened, its reminders appear at the top of the timeline.

## Consequences

Reminders are plain text stored against the target session. Moving that session keeps its notes. Deleting it deletes its reminders. Deleting the source or author leaves the note and clears the corresponding reference.
