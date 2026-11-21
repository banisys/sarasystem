# 0118 — Subject and delivery filters in the academic calendar

- **Date:** 2026-11-21
- **Status:** accepted

## Context

The academic calendar shows a person's teaching and exam sessions. The user asked to see the subject on the month cards and to have the subject and delivery filters available in the class calendar.

## Options considered

- **A — Filter the sessions already loaded in the browser.** Easy to apply to a single view, but ties the available subjects and selected filters to that period.
- **B — Filter calendar responses through the URL.** Preserves selections through date and view changes, refreshes, and shared links.

## Decision

Use B. The `subject` and `mode` query parameters apply together against the person's visible sessions in the current branch ([ADR 0117](0117-academic-calendar-session-membership.md)). The subject menu includes subjects from those sessions across dates, including inactive subjects attached to past sessions. The mode menu offers «حضوری» and «آنلاین», using the session's effective mode. Month cards display the subject when one is associated with the session.

## Consequences

Clearing a filter restores its unrestricted results within the other selection. The guardian view uses the ward's available subjects and sessions.
