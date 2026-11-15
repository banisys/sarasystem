# 0100 — Element Plus for the user panel

- **Date:** 2026-11-15
- **Status:** accepted

## Context
The user panel needs consistent tables, forms and dialogs as more staff workflows move to Vue. The question bank list is the first page to migrate.

## Options considered
- Extend the local Vue controls and build the table, pagination and dialog interactions in-house.
- Register Element Plus for the entire app, including pages that do not use it.
- Adopt Element Plus a page at a time and import the required components and styles from each migrated page.

## Decision
Use page-level imports, starting with the [question bank list](../../Modules/Exam/resources/js/Pages/QuestionLibrary.vue). Set the Persian component locale and adapt controls to the shared panel colors, typography and RTL layout. Keep the shared panel shell and use Inertia for navigation, Laravel validation and server pagination.

## Consequences
The public site, My Classes and individual bank question pages keep their current controls until separately migrated. Reka UI still handles the shared mobile navigation. Other pages do not need the question library's Element Plus assets.
