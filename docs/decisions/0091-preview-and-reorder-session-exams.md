# 0091 — Preview and reorder generated session exams

- **Date:** 2026-11-13
- **Status:** accepted

## Context
After generating a draft, its author needs to review the actual question set and arrange it before publishing.

## Options considered
- Reorder bank rows before drawing questions. This does not provide control over the questions that were actually drawn.
- Reorder the saved exam snapshot. This preserves question content and sets the order used by participant attempts.

## Decision
Session managers can preview the question text, options and scores of generated exams. Only the author may reorder an unpublished draft with no attempts. The server validates a complete permutation against the preview revision and uses the same exam row lock as attempt start. Saving a manual order disables question shuffling for that exam.

## Consequences
Existing bank-based exams without a snapshot have no fixed set to preview. Published exams and exams with attempts can still be previewed if they have a snapshot. A stale preview must be reopened before saving.
