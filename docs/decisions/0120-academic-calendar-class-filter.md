# 0120 — Class filter in the academic calendar

- **Date:** 2026-11-22
- **Status:** accepted

## Context

People who belong to several classes need to see one class's sessions at a time in the user-panel calendar.

## Options considered

- **A — Offer classes from the person's calendar sessions.** This includes classes where the person only has a session assignment and omits memberships with no visible sessions.
- **B — Offer classes from the calendar's class membership list.** The class picker and the class cards below the calendar use the same memberships, including classes whose sessions fall outside the displayed period.

## Decision

Use B. The `classroom` query parameter selects a class from the person's existing class list in the current branch. In a guardian view, the list belongs to the ward. An unavailable class ID is ignored. The class filter applies together with subject and delivery mode across day, week and month views ([ADR 0118](0118-academic-calendar-filters.md)).

## Consequences

«همهٔ کلاس‌ها» clears the class filter. Calendar sessions still follow the attendance roster and teaching and invigilation assignments in [ADR 0117](0117-academic-calendar-session-membership.md).
