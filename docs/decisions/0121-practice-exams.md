# 0121 — Practice exams

- **Date:** 2026-11-22
- **Status:** accepted

## Context

Instructors need to create practice from their question banks. Learners should be able to repeat it and choose when to see the solution to an individual question.

## Options considered

- **A — Add a separate practice activity.** This would build a parallel workflow for question selection, publication, participant access and saved answers.
- **B — Add a practice mode to exams.** Reuse the current editor, question snapshots, participation window and attempt runner while giving practice its own grading and solution rules.

## Decision

Use B. Standalone and session exam creation offer «آزمون تمرینی». Existing exams default to graded mode. The mode is fixed when the exam is created so that revealing a solution can never change the scoring contract of its attempts.

Practice supports unlimited starts inside the exam's configured availability. Each start uses the existing question selection and timing rules. Submitting or timing out closes the attempt and keeps the learner's answers. Practice skips automatic scoring, never enters manual grading, and stays out of grading results. Staff can still see the number of attempts on the exam.

Switching to practice in the creation form hides scoring, retake and result settings. Timing and question selection remain available. The user-panel question picker omits point values in practice.

The learner can request the solution for a single question during practice or when viewing their latest completed practice. The participant endpoint checks publication, current branch, eligibility, and ownership of the saved question snapshot. It returns the correct choice, accepted blanks, or a written model answer when available.

## Consequences

Question bank content and session membership still use their established rules. Exam can run without Class. The practice flag lives on the exam and a database rollback drops that flag, so production deployments with created practice exams should use a forward fix.
