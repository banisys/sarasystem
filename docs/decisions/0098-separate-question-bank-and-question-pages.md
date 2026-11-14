# 0098 — Separate question bank and question pages

- **Date:** 2026-11-14
- **Status:** accepted

## Context
Instructors need a bank table and a separate page for each bank's questions.

## Options considered
- Keep the bank list and question editor on one page, with a selected bank.
- Give banks and questions separate pages so instructors can link directly to a bank and manage it independently.

## Decision
The library opens a paginated bank table. The same dialog handles creating and editing a bank. Each bank links to its own question table. Instructors edit their questions in a dialog. The «افزودن سؤال» dialog saves directly to the current bank for its owner or a staff member with permission to edit that bank ([ADR 0099](0099-add-questions-from-bank-pages.md)). Existing bank links with a query parameter redirect to their dedicated pages. The full question catalog remains available at its direct URL.

## Consequences
The existing tenant, ownership and sharing rules apply to both pages. Creating, editing or deleting a question from a bank page returns to that bank.
