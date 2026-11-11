# 0083 — Fill-in-the-blank exam questions

- **Date:** 2026-11-11
- **Status:** accepted

## Context

Question authors need to place a short answer inside a sentence and accept equivalent answers.

## Options considered

- Treat the answer as descriptive and have staff grade it. This supports interpretation but requires a review for every answer.
- Grade one blank against an explicit list of accepted answers. This fits short answers and reuses the existing text autosave.
- Support multiple blanks with per-blank scoring. This needs a new response format and grading rules.

## Decision

Add «جای خالی» with one `___` marker and one or more accepted answers. The attempt copies the answer list so later edits to the bank do not change its grading. The participant enters text in place of the marker. Matching ignores case, repeated whitespace, and common Persian and Arabic digit and letter variants. A match receives full credit. Wrong and missing answers receive zero. Negative marking still applies to choice questions.

## Consequences

Staff can review automatic scores and accepted answers. Participants can see the accepted answers only when the exam shows answers and another attempt is unavailable.
