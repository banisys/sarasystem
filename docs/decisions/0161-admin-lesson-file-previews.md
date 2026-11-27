# 0161 — Preview saved lesson files in the admin editor

- **Date:** 2026-11-27
- **Status:** accepted

## Context

Course editors need to review the files already attached to a lesson, including lessons in unpublished courses.

## Options considered

- Extend the learner file route to accept editors.
- Serve editor previews through the admin panel, with the same course update permission as the lesson edit page.

## Decision

The edit form plays saved video and audio in the page. It shows common raster images, opens PDFs in a new tab, and offers a download for other attachments. A route in the course resource serves each file after checking panel access, course update permission and file ownership.

## Consequences

The learner route retains its publication and enrollment checks. Editors can review replacements by saving and reopening the lesson.
