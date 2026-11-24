# 0133 — Lock session exam changes in the user panel

- **Date:** 2026-11-24
- **Status:** accepted

## Context

An exam administrator needs to preserve a session exam while class staff continue to use its user-panel pages.

## Options considered

- Remove session staff permissions for the entire class or branch.
- Add a reversible, per-exam restriction for user-panel changes.

## Decision

Store an unlocked-by-default flag on the exam. Administrators with edit permission can lock or unlock an attached exam from its admin edit page. The user panel blocks settings, group, question and publication changes and deletion while locked. Server-side writes recheck the flag after locking the exam row, so a stale page cannot bypass the restriction.

## Consequences

The admin panel can continue to edit a locked exam. Publication status, existing attempts, participant access, previews and grading retain their own rules. Unlocking restores the user-panel actions otherwise permitted for that exam.
