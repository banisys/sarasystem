# 0102 — Create an online exam from a class exam schedule

- **Date:** 2026-11-15
- **Status:** accepted

## Context
Class editors need to set up a new online exam while working from a dated exam session in the admin panel.

## Options considered
- Select an existing catalog exam and copy its settings. Editors would need to prepare a standalone exam first.
- Open a dedicated creation page reusing the admin exam form. The session can supply the window and eligible attendees before the form is saved.

## Decision
For a session without an exam, «آزمون جلسه» opens a dedicated create page with the exam's settings and question banks. The page uses the selected session's window and membership and returns to its class exam schedule after saving. The editor sets publication in the form.

The route requires permission to create an exam and edit the class. The optional Exam context resolves eligible exam sessions within the current branch. The page rechecks access on submission and saves the exam and bank sections together.

## Consequences
The ordinary admin creation page still handles standalone exams. Class session staff can also generate an exam from the question library on the user-panel context page under [ADR 0089](0089-shared-question-library-and-session-exam-generation.md).

The admin creation page can assign the new exam to a branch's exam group ([ADR 0104](0104-exam-groups.md)).

The admin creation page can preview and print a sample paper using the banks and counts in the unsaved form ([ADR 0105](0105-printable-session-exam-draft.md)).

Once an exam exists, the same schedule action opens its edit page for users with exam update permission. If historical duplicates exist, the edit action chooses the same exam as the print modal. A direct visit to the creation form opens session details ([ADR 0107](0107-single-exam-and-staff-paper.md)).
