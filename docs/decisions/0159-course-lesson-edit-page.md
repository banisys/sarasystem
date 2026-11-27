# 0159 — Dedicated course lesson edit page

- **Date:** 2026-11-26
- **Status:** accepted

## Context

The lesson editor contains rich text, media uploads and an optional exam code. Creation already uses a dedicated page, while editing opened in a table dialog.

## Options considered

- Keep the edit dialog in the lessons list.
- Open the shared lesson form on a dedicated edit page.

## Decision

The lesson row's edit action opens `/{course}/lessons/{record}/edit` under the course resource. Both pages group the shared form into lesson details, media and attachments, and access and exam settings. Saving or cancelling returns to the course's lessons list.

## Consequences

The edit page requires permission to update the owning course and resolves the lesson through that course. The same section, media and exam rules apply as before.

The edit form shows [previews of saved files](0161-admin-lesson-file-previews.md).
