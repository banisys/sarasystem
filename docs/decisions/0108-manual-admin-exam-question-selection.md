# 0108 — Manual question selection in the admin exam form

- **Date:** 2026-11-17
- **Status:** accepted

## Context
Exam editors need to review and hand-pick questions from each bank in the admin form. Existing admin exams can draw from multiple banks, including banks with overlapping questions.

## Options considered
- Convert the entire exam to a question snapshot when an editor confirms a selection. That would freeze the draw from every bank.
- Store selected question IDs per bank section. This allows the editor to combine explicit choices with existing automatic draws.

## Decision
Each bank row in the admin form offers «انتخاب سؤال‌ها». The dialog proposes a draw or shows the saved choices, lists active questions from that bank, and updates the row's count on confirmation. Confirming an empty selection restores the automatic draw. The save validates bank membership, tenant scope, active status, count, and uniqueness across sections.

Admin bank sections store optional question IDs. At the start of an attempt and when rendering a paper, explicit selections take priority over automatic draws across overlapping banks. Attempts still copy the questions they serve.

## Consequences
Older sections continue drawing by count. New attempts and previews read the current version of each selected question and report an error if an editor must replace a selection that became unavailable. Exams generated with a fixed snapshot in the user panel continue using [ADR 0090](0090-manual-session-exam-question-selection.md).
