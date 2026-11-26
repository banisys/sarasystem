# 0154 — Unique exam codes

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Staff need a short code to refer to an exam, following the course creation form.

## Options considered

- Require a manually assigned code.
- Accept an optional code and generate one when it is blank.

## Decision

The exam form accepts an optional code, unique across the installation. A new exam with no supplied code receives an automatically generated `EXM-` code, including exams created through a context. Existing exams receive codes derived from their IDs.

## Consequences

Codes are distinct across branches. Exam visibility and participation still follow the exam's tenant and access rules.

The admin exam list displays each exam's code beside its title.
