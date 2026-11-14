# 0089 — Personal question libraries and session exam generation

- **Date:** 2026-11-13
- **Status:** accepted

## Context
Teaching staff need to author and share question banks and questions from the user panel, then create an exam for a specific class session from those materials.

## Options considered
- Keep assigning standalone exam templates. Staff would still need a separately configured exam before they could use their questions in a session.
- Store references to shared banks on the exam and draw live at attempt start. Subsequent edits, removal or withdrawal of sharing could change a scheduled exam.
- Draw questions when creating a session exam and store the selected questions with it. This fixes the question set at creation and keeps the existing attempt lifecycle.

## Decision
- Reuse the tenant-owned question and bank tables. Personal records have an owner and are private by default. An owner can edit and delete their own items. Sharing a bank and sharing a question are independent. Authorized colleagues in the same tenant can read and reuse public items. A public question added to a colleague's bank remains a reference and is no longer available for new draws if its owner makes it private.
- The Exam library is available to instructors and existing question-bank administrators. Class supplies teaching staff eligibility through the optional Exam context source, including teachers, substitutes and current non-attendee class members such as supervisors and supporters. Exam has no dependency on Class.
- The session form replaces the prepared exam selector. Session managers choose their own or shared banks and a count per bank. The server rechecks access and draws active questions in bank order, without duplicates. An insufficient bank count fails the request without creating an exam.
- A new exam stores a snapshot of the selected question content and grading data, uses the session's time window, and starts as an unpublished draft. Session membership, publication, attempts, scoring, review and deletion reuse the existing flows. The snapshot is excluded from ordinary model serialization and participant responses only expose the fields allowed by the taking and result flows.

## Consequences
Old standalone exams and existing session copies keep drawing from their bank sections. Existing administrative questions and banks have no personal owner and do not appear in the user library. Authorized admin catalog access stays as before. A generated exam continues to use its saved question set after a source is edited, deleted or made private.

Manual selection per bank is covered by [ADR 0090](0090-manual-session-exam-question-selection.md).

The bank table, bank dialog and dedicated question pages are covered by [ADR 0098](0098-separate-question-bank-and-question-pages.md).
