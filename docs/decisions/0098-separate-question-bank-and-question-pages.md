# 0098 — Separate question bank and question pages

- **Date:** 2026-11-14
- **Status:** accepted

## Context
Instructors need a bank table and a separate page for each bank's questions.

## Options considered
- Keep the bank list and question editor on one page, with a selected bank.
- Give banks and questions separate pages so instructors can link directly to a bank and manage it independently.

## Decision
The library opens a paginated bank table. The same dialog handles creating and editing a bank. Each bank links to its own question page, which preselects that bank when its owner creates a question. An «همهٔ سؤال‌ها» link opens the full question catalog, including questions without a bank. Existing bank links with a query parameter redirect to their dedicated pages.

## Consequences
The existing tenant, ownership and sharing rules apply to both pages. Creating, editing or deleting a question from a bank page returns to that bank.
