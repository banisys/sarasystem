# 0092 — Settings for session exam drafts

- **Date:** 2026-11-13
- **Status:** accepted

## Context
Session exam authors need the rules from the admin exam form that apply to a session, both at creation and after seeing the generated draft.

## Options considered
- Send authors to the admin edit page, which requires general exam permissions.
- Share a session settings form between generation and draft editing, with a server allowlist of the applicable fields.

## Decision
Expose title, instructions, duration, grace period, scores, pagination, negative marking, attempt limits, retake policy, result visibility and question/option shuffling. The time window and participants are inherited from the session and publication remains a separate action.

Only the author may update settings on an unpublished exam with no attempts. Updates lock the exam row and leave its question snapshot intact. A manual question order can be served unchanged by keeping question shuffling off, as described in [ADR 0091](0091-preview-and-reorder-session-exams.md).

## Consequences
Session authors can review questions and change the rules before publishing without access to the admin panel. Active and historical attempts keep the rules under which the exam was offered through the session workflow.
