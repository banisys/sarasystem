# 0107 — One exam per formal sitting and a printable staff copy

- **Date:** 2026-11-16
- **Status:** accepted

## Context
A dated class session of kind Exam represents one formal sitting. Staff need a question paper directly on its admin details page, even if their role does not manage the class or its exams.

## Options considered
- Keep the link to the user-panel exam list and let staff choose among several exams.
- Allow one new exam per formal sitting and show its question paper in an admin modal.

## Decision
Both exam creation flows check for an existing exam while holding the branch row lock in the save transaction. The rule applies to sessions of kind Exam. Legacy duplicate records and results remain available through the existing management flows. The modal chooses the latest published exam, or the latest draft if none is published.

New exams in a session of kind Exam are graded and use the session's saved closing time. The creation forms omit practice and late start. The user-panel settings editor also omits both options and preserves any values already saved on legacy exams.

The exam session's «برگهٔ آزمون جلسه» action ([ADR 0109](0109-exam-scheduling-from-class-calendar.md)) opens the printable sheet without navigation. Active employees in the branch who already have branch panel access may open it without class or exam permissions. Those employees see only the paper on this page unless the existing class policy also allows the session details. Class managers who can see the session retain the paper action. The Employee integration uses the optional core directory contract.

## Consequences
The paper uses saved questions when present and a representative bank draw for exams that draw questions at attempt time. It omits grading keys. The session details, other admin actions, online participation, and results keep their existing authorization. Regular class sessions still support multiple exams.

The shared A4 paper has a right-to-left identity grid with the exam title, branch, class, scheduled date and start time, duration, and spaces for the candidate's details. The question table shows each question's stored weight, space for written answers, and the sum of the printed weights.

The admin exam list offers «پیش‌نمایش آزمون» to users who can view the exam. The modal uses the same paper and opens the selected row's exam, including standalone exams. It uses the session's class and start time when the session is accessible, and otherwise uses the exam's own start time when set.
