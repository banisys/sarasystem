# 0099 — Add questions from bank pages

- **Date:** 2026-11-14
- **Status:** accepted

## Context
A staff member managing a shared bank needs to create a question from that bank's page.

## Options considered
- Limit adding questions on the page to the bank owner.
- Allow the bank owner and users who already have the bank's update permission to add a question.

## Decision
Use the existing bank update policy for staff who can already open the bank in their library. The bank page posts to a bank-specific route that attaches the new question to the current bank. Additional destinations in the form are limited to the author's own banks.

## Consequences
Sharing a bank grants read access for reuse. The question belongs to its author and has its own sharing setting. The bank is checked again inside the write transaction.
