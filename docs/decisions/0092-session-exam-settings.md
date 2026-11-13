# 0092 — Settings for session exams

- **Date:** 2026-11-13
- **Status:** accepted

## Context
Session exam authors need the rules from the admin exam form that apply to a session, both at creation and after seeing the generated draft.

## Options considered
- Send authors to the admin edit page, which requires general exam permissions.
- Share a session settings form between generation and draft editing, with a server allowlist of the applicable fields.

## Decision
Expose title, instructions, duration, grace period, scores, pagination, negative marking, attempt limits, retake policy, result visibility and question/option shuffling. The time window and participants are inherited from the session and publication remains a separate action.

The exam list offers a labeled settings button to session managers who either created the exam or already have permission to update it in the admin panel. They can edit a draft or a published exam until the first attempt starts. Updates lock the same exam row as attempt start and preserve the publication state and question snapshot. A manual question order can be served unchanged by keeping question shuffling off, as described in [ADR 0091](0091-preview-and-reorder-session-exams.md).

## Consequences
Session authors can adjust the rules without access to the admin panel. Once a learner starts, the session workflow locks settings to protect active and historical attempts.
