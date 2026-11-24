# 0134 — Lock seating changes in the user panel

- **Date:** 2026-11-24
- **Status:** accepted

## Context

Class editors need to preserve an exam's seating plan after arranging it while invigilators can still consult the plan.

## Options considered

- Tie seating changes to the online exam's lock.
- Store a separate seating lock on the class session.

## Decision

Keep the seating lock on the exam session, off by default. The admin seating dialog can lock or unlock user-panel editing independently of saving the plan. It uses a lock icon on the seating button when locked. In the user panel, the same users who ordinarily manage seating can open the plan read-only.

## Consequences

The user-panel save operation checks the lock while holding the session row lock. Admin edits, attendance and the 3D view retain their existing behavior. The seating lock works independently of the Exam module and can be set before a plan is saved.
