# 0093 — User-panel URL prefix

- **Date:** 2026-11-13
- **Status:** accepted

## Context

The public site and user-panel sections used the same top-level URL space. Users want panel sections under a recognizable prefix, such as `/profile/exams/library`.

## Options considered

- Keep panel sections at the root. Each new section would continue to reserve a top-level public slug.
- Move the sections below the existing `/profile` account page. The URL identifies the panel and groups its sections in one namespace.

## Decision

Keep the account page at `/profile`. Move the dashboard, tenant selection and switching, and the Class, Contract, Exam and School panel routes under that prefix. Keep route names and access checks so links, forms and tutorial missions resolve the new URLs automatically. Public pages, sign-in and sign-out, and shared endpoints such as signed files and entry to online sessions keep their existing paths.

## Consequences

Bookmarks to the previous panel URLs need updating. The old top-level segments are available to the public site where no other route reserves them.
