# 0102 — Assign existing exams from the class exam schedule

- **Date:** 2026-11-15
- **Status:** accepted

## Context
Staff need to select an existing online exam from the admin exam catalog while working on a dated exam session.

## Options considered
- Reuse one exam record across sittings. This would share attempts and results across classes.
- Restore the independent-copy operation from [ADR 0079](0079-exams-in-class-sessions.md) in the schedule's row menu. This keeps the roster and attempts specific to each sitting.

## Decision
«اتصال آزمون» opens a searchable picker of standalone exams from the current branch. It requires permission to edit the class and view the exam catalog, and is available for sessions that have not been cancelled when Exam is enabled. Published sources produce published copies, and draft sources produce drafts.

The copy uses the session window and membership. It inherits settings and bank sections from the source. Existing participants and attempts stay with the original. Reassigning the same source to the same session returns its existing copy.

## Consequences
The context page still offers question-bank generation under [ADR 0089](0089-shared-question-library-and-session-exam-generation.md). Both flows use the existing context fields and session membership rules.
