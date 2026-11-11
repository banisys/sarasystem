# 0082 — Question tags

- **Date:** 2026-11-11
- **Status:** accepted

## Context

Exam managers need to attach several reusable labels to a question and find labeled questions across banks and category branches.

## Options considered

- Store free-text labels with each question. This gives authors immediate entry but makes it hard to rename a label consistently or clean up duplicates.
- Manage reusable labels per tenant and link them to questions. This supports a single rename, consistent filtering, and separate permissions for managing the catalog.

## Decision

- The tenant's catalog lives at «آزمون → برچسب‌های سؤال». Titles are trimmed and unique within a tenant. The catalog shows how many questions use each tag.
- The question editor offers an optional searchable multi-select. The question list shows the tags and can filter by any of the selected tags. Bank and category filters can be combined with it.
- Exam owns the tags and the many-to-many links. Both foreign keys include the tenant ID so a link can only join records from the same tenant. Deleting a tag or a question cascades to its links. Question and relationship writes from the editor are transactional.
- Catalog management uses `*:QuestionTag` permissions. The education officer and the roles above it receive the standard tag permissions through the default role installer. Users with question editing permission can apply existing tags.

## Consequences

- Existing questions start with no tags. Tags organize the question catalog. Exam sections still draw questions from banks, and past attempts retain their snapshots.
- Renaming a tag updates its display everywhere it is used. Deleting it removes its assignments and leaves the questions in place.
- The school tutorial teaches creation, assignment and filtering when Exam is enabled.
