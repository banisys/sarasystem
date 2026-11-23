# 0104 — Exam groups

- **Date:** 2026-11-16
- **Status:** accepted

## Context
Staff need to find related exams, such as a June 1405 exam series or a midyear series, even when they are created from different class sessions.

## Options considered
- Free text on each exam. Labels are quick to enter but can diverge and need to be renamed individually.
- Multiple reusable tags. Flexible, but unnecessary when staff want to classify each exam in one series.
- One optional group from a managed branch catalog. Titles can be reused, renamed and filtered consistently.

## Decision
Staff with the relevant permissions create, rename and delete branch groups under «آزمون → گروه آزمون». Group titles are unique within a branch. The shared admin exam form offers one optional group, including when creating an exam from a class session. The admin exam list shows the group and filters by it.

The [user-panel session exam page](0125-session-exam-group-assignment.md) uses the same catalog when creating or regrouping an exam.

## Consequences
Existing exams and exams created outside the admin form can remain ungrouped. Removing a group clears the assignment and preserves the exams and results. The form and the database enforce that an exam and its group belong to the same branch.
