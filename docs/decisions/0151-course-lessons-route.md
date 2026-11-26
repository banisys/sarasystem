# 0151 — Course lessons route

- **Date:** 2026-11-26
- **Status:** accepted

## Context

The course admin interface calls its learning units lessons, while the page URL and action still used the legacy activity terminology.

## Options considered

- Replace the activities URL and require updated bookmarks.
- Use a lessons URL for navigation and redirect existing activities URLs.

## Decision

Use `/{record}/lessons` under the course resource for the lesson management page. Redirect the previous URL within the same admin panel and branch.

## Consequences

Existing links continue to work. The destination page applies the course update permission, and the rename requires no data migration. [ADR 0152](0152-course-lesson-creation-page.md) adds the lesson creation route under this page.
