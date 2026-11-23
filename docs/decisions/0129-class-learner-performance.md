# 0129 — Learner performance from a class roster

- **Date:** 2026-11-23
- **Status:** accepted

## Context

Class staff need to open a learner's exam scores directly from the class roster.

## Options considered

- Give class staff access to the learner's branch-wide report.
- Reuse the performance report with a scope limited to the selected class.

## Decision

Offer «گزارش عملکرد» beside current attendees when the viewer has a current non-attendee membership in that class and Exam is enabled. Apply the same rules on direct requests within the current branch. The report includes only this class's session exams and follows the existing rules for published exams, numeric score visibility and the latest finished attempt.

## Consequences

Staff can change the exam group and return to the class roster. The report is read-only and opens without links to individual attempts. Class still works with Exam disabled.
