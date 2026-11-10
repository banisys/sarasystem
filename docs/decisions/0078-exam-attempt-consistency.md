# 0078 — Exam attempt consistency and bounded reads

- **Date:** 2026-11-10
- **Status:** accepted

## Context
Before integrating online exams into class sessions, the existing independent Exam module needs a reliable attempt lifecycle. Autosave trusted an already-loaded status, and finishing was neither atomic nor idempotent. Question selection loaded complete banks and the participant list loaded all attempts.

## Options considered
- **Controller-only checks** — inexpensive, but another request can finish an attempt after the check.
- **Attempt row locks inside service transactions** — serialize writes to the same attempt and recheck persisted state; require no new infrastructure or schema.
- **Load banks and shuffle in PHP** versus **filter, shuffle and limit in SQL** — database selection bounds hydrated question bodies/options while preserving section order and exclusion of previously selected questions.

## Decision
Keep `ExamTaking` as the module's lifecycle boundary. Save, submit and finish acquire a tenant-scoped attempt row lock inside a transaction. Private operations share that lock; submit saves and grades atomically. Finish leaves an already-closed attempt unchanged, including submission time and timeout flag. Starting uses the freshly locked exam settings, retaining the existing per-exam start lock.

Select only each section's requested active questions in SQL, excluding questions already drawn. Read only the participant's latest attempt per exam in the list. Close overdue attempts in ID-based batches of 100 so changes to their status cannot skip later rows.

## Consequences
- The public service signatures, routes, UI, scoring rules and tenant boundaries remain compatible. No Class/School dependency, new package or migration is introduced.
- Transactions serialize writes to one attempt; regression tests exercise stale models and repeated finalization, not a concurrent-process load test.
- Random ordering still requires database work over eligible questions; this bounds PHP memory rather than promising constant database cost.
- Existing limits remain: abandoned attempts close lazily, scoring settings are read from the live exam, and starts for different participants of the same exam serialize on its row. Class-session assignment and participant sources are a separate next step through module extension hooks.
