# 0160 — Link the original exam to a course lesson

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Staff create an exam in the admin exam catalog, then enter its code on the lesson form. [ADR 0155](0155-course-lesson-content-and-exam-code.md) created a second exam during that submission, leaving staff with two codes and two records to manage.

## Options considered

- Copy the exam for each lesson and keep the original available separately.
- Attach the original exam to one lesson through its existing exam context.

## Decision

Saving the lesson assigns the existing exam to that lesson in the same transaction. The exam keeps its ID, code, branch, questions and settings. The editor must have permission to update the exam in the selected or primary branch. Because course enrollment determines participation after attachment, the exam must be standalone and have no assigned users, roles or attempts.

## Consequences

An exam can belong to one lesson, and new attachments are limited to one exam per lesson. Edits in the exam catalog affect the same exam the lesson uses. Earlier lesson copies and their results are retained as stored.
