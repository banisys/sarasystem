# 0075 — Create sessions on past dates

- **Date:** 2026-11-09
- **Status:** accepted

## Context
A one-off schedule for 12:40 saved at 12:41 silently created no session because the planner only included future start times. The user wants schedules to create sessions on past dates too, including sessions already finished.

## Options considered
- **Future starts only** — avoids historical creation but silently omits valid meetings.
- **Include ongoing sessions** — fixes a late save but still prevents entering historical schedules.
- **All dates in the schedule** — supports historical entry; rebuilding must preserve existing historical records.

## Decision
- `SessionPlanner::planned()` includes every occurrence within the schedule dates, regardless of the current time. Holidays, manually skipped dates, clash checks and the 1000-session limit still apply.
- Synchronization considers all existing sessions. An existing session whose start has passed (including exactly now) keeps its identity, times, teachers, status and recorded content; its original date is consumed so it is not created again. Future sessions retain the existing synchronization rules from ADR 0023.
- The make-up / extra session form also accepts past dates and keeps a past date selected in the calendar.
- The sample-data generator records history on completed planner-created sessions instead of creating another set of past sessions.
- Existing schedules are repaired on save or synchronization; there is no automatic installation-wide backfill.
- Moving an existing session and deleting a schedule retain their existing rules; this decision changes session creation.

## Consequences
- A historical session is created as scheduled, with no fabricated attendance or content; the calendar can show it as unrecorded until staff enter its records.
- Existing past sessions remain when a schedule changes or is deleted.
- Repeated saves do not duplicate past sessions. Past dates count toward the schedule’s session limit and clash checks.
