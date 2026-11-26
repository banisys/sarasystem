# 0153 — Rich text for course lessons

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Course authors need formatting in «متن درس». Existing lessons store plain text.

## Options considered

- Convert existing bodies to HTML in place.
- Store rich text separately and keep the original plain text available.

## Decision

Use the shared admin rich editor and store its output in `body_html`. Prefill the editor from escaped plain text when opening an older lesson. The learner page sanitizes HTML before rendering it and displays the original plain text for lessons that have yet to be saved with the editor.

## Consequences

Existing lesson bodies need no data conversion. Files and videos continue to use the lesson’s dedicated upload fields.
