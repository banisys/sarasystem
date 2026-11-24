# 0132 — User-panel seating access for exam invigilators

- **Date:** 2026-11-24
- **Status:** accepted

## Context

Invigilators need to assign seats for the exams they supervise. The admin seating editor requires classroom update permission, which an invigilator may lack.

## Options considered

- Require classroom update permission in both panels.
- Allow the effective invigilator to edit the seating plan of the assigned exam in the user panel.

## Decision

In the user panel, accept classroom editors and the effective invigilators of an accessible, non-cancelled exam in the current branch. Use the substitute where one is assigned. Check the same permission when loading the editor and when saving under the session row lock.

## Consequences

The assignment grants access to that exam's seating plan only. The admin editor still requires classroom update permission. Existing roster validation and the shared seating plan from [ADR 0087](0087-exam-seating.md) apply in both panels.
