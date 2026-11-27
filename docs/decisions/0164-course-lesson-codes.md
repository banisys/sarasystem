# 0164 — Unique course lesson codes

- **Date:** 2026-11-27
- **Status:** accepted

## Context

Course administrators need a short code to identify and find lessons in a course's admin list. The platform already uses [optional, generated codes for exams](0154-exam-codes.md).

## Options considered

- Require administrators to enter a code for every lesson.
- Accept a custom code and generate one when the field is blank on creation.

## Decision

The lesson form accepts an optional code that is unique across the installation. A new lesson with a blank code receives `LSN-` followed by eight uppercase letters or digits. Leaving the field blank during editing preserves the saved code. The migration gives existing lessons a code based on their ID. The course's lesson list shows the code and supports searching by it.

## Consequences

Lesson codes belong to Course and work with Exam disabled. The separate exam code field continues to link an exam to the lesson. Rolling back the migration drops stored lesson codes.
