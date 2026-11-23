# 0127 — Learner exam performance report

- **Date:** 2026-11-23
- **Status:** accepted

## Context

Learners need to see their scores from a series of exams across subjects, such as the Aban class exams.

## Options considered

- Rely on each exam's result page. It shows the answers and grading details for one exam at a time.
- Extend School report cards. Those group scores by academic period and depend on School's curriculum.
- Add a participant report to Exam using the branch's existing exam groups and the exam host's subjects.

## Decision

Add «گزارش عملکرد» to the user-panel menu when the participant has a published, graded-mode exam in a group that allows numeric scores. The report opens with the newest available group selected. For a chosen group it shows the participant's score and maximum for each exam, with exams grouped by the subject of their session. It uses the latest finished attempt for each exam and shows the score once grading is complete.

The report uses the current branch and the same participant and result-visibility rules as exam access. Class supplies subjects through the existing Exam context extension. A grouped standalone exam or an exam with no linked subject appears under «آزمون‌های بدون درس».

## Consequences

Group assignments and class-session subjects are reflected on the next visit. The report also works for standalone exams when Class and School are disabled.
