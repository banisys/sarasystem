# 0088 — Delete unused session exams

- **Date:** 2026-11-13
- **Status:** accepted

## Context
Session staff need to remove a session copy they added by mistake before a participant starts it. The session copy is independent of its template ([ADR 0079](0079-exams-in-class-sessions.md)).

## Options considered
- Allow every session manager to delete an unused copy. This would give staff permission to delete copies assigned by other staff.
- Record who assigned each copy and let that person delete it while they can still manage the session. Use the existing exam delete permission for copies whose assigner is unknown.

## Decision
Record the assigning user on new session copies. A repeated assignment keeps the original assigner. The session page offers deletion to that user while they can manage the session and no attempt exists. For copies without a recorded assigner, deletion requires both session management access and the existing exam delete permission.

Deletion locks the same exam row as attempt start ([ADR 0078](0078-exam-attempt-consistency.md)) and checks for any attempt, including one in progress, before removing the copy.

## Consequences
The template and question banks are preserved, and the template can be assigned again. Existing copies have no recorded assigner, so the migration leaves their ownership unknown.
