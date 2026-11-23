# 0129 — Learner performance from a class roster

- **Date:** 2026-11-23
- **Status:** accepted

## Context

Class staff need to open a learner's exam scores directly from the class roster.

## Options considered

- Give class staff access to the learner's branch-wide report.
- Keep the group selector and limit it to the selected class.
- Show a single term report scoped to the viewer's subject assignments in the selected class.

## Decision

Offer «گزارش عملکرد» beside current attendees when the viewer has a current non-attendee membership in that class and Exam is enabled. Apply the same rules on direct requests within the current branch. To determine whether the class term is current, use the active flag and dates of the term assigned to the class. This keeps a class in its own period when the organization has overlapping terms.

Require an explicit assignment on a teaching schedule of the subject in the selected class. Class membership alone grants entry to the report. Class-level roles such as supporter and supervisor use the same schedule assignment for subject-level access. The report includes numeric scores from the latest finished attempt per graded exam linked to a session of an assigned subject within the class term. Grouped and ungrouped exams share the same report. Staff access to these scores is governed by their assignment, while participant result visibility governs the learner's own report.

## Consequences

Staff can return to the class roster. The report is read-only and opens without links to individual attempts. It shows an empty state when the class has no current term or there are no eligible scores. Class still works with Exam disabled.
