# 0152 — Dedicated course lesson creation page

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Staff open «ایجاد درس» from a course's lessons list. The lesson form includes content fields and file uploads.

## Options considered

- Keep the form in the list's create dialog.
- Give lesson creation its own course-scoped page.

## Decision

«ایجاد درس» opens `/{course}/lessons/create` under the course resource in the current tab. Saving or cancelling returns to the same course's lessons list. The existing option to save and add another lesson keeps staff on the creation page.

## Consequences

The page uses the course update permission and validates that the selected section belongs to the course. Creation and [editing](0159-course-lesson-edit-page.md) share the same form card and «متن درس» uses the [shared rich editor](0153-rich-course-lesson-text.md).

Saving a new lesson with an empty code generates its [lesson code](0164-course-lesson-codes.md).
