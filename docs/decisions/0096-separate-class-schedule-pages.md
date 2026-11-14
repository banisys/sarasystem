# 0096 — Separate teaching and exam schedule pages

- **Date:** 2026-11-14
- **Status:** accepted

## Context
The class list needs distinct entry points for managing recurring teaching schedules and dated exam sessions.

## Options considered
- Keep the two tables on the same page and link to each section from the class row.
- Give each schedule type a page and row action, following [ADR 0061](0061-class-parts-as-row-actions.md).

## Decision
«برنامهٔ درسی» keeps the existing `classes/{id}/schedules` route and teaching schedule actions. «برنامهٔ امتحانی» opens `classes/{id}/exam-schedules` and hosts exam session creation and editing ([ADR 0097](0097-edit-class-exam-sessions.md)), the list of dated sittings, and links to their details.

## Consequences
Both pages use the class's existing tenant and permissions. Exam sessions still use the class session lifecycle and appear in its calendar. Existing teaching schedule URLs stay valid.
