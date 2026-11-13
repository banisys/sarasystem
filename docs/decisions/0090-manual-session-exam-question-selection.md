# 0090 — Manual selection of session exam questions

- **Date:** 2026-11-13
- **Status:** accepted

## Context
A session exam author needs to see the proposed questions from a bank and change them before creating the draft.

## Options considered
- Store a temporary draw on the server for each bank. This would require a draft or a session token before the author saves the exam.
- Fetch eligible question summaries on demand and submit the confirmed IDs with the existing generation form.

## Decision
The bank picker opens with a random proposal for the requested count, or the row's previously confirmed choices. Confirming updates the row's count and submits explicit IDs on generation. Rows without a confirmed selection still draw randomly.

The server rechecks bank membership, question visibility, active status and count, and rejects duplicate choices. Explicit selections are reserved before any random draw so overlapping banks cannot displace them. The exam stores the selected question snapshot as before.

## Consequences
Closing the picker without confirming leaves the row as it was. The list is fetched only for a session manager, and it contains question summaries without answer keys. A selection that became unavailable before saving must be reviewed again.

After generation, the draft's saved question order can be reviewed and changed as described in [ADR 0091](0091-preview-and-reorder-session-exams.md).
