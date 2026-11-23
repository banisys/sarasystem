# 0125 — Group assignment for session exams

- **Date:** 2026-11-23
- **Status:** accepted

## Context

Staff need to organize session exams using the [existing branch catalog](0104-exam-groups.md), including after participants have submitted answers.

## Options considered

- Include group assignment in the settings dialog, which closes to edits after the first attempt.
- Provide a separate group action and include the group selector when generating an exam.

## Decision

Offer the branch catalog on the creation form and on each editable exam card. The group action uses the existing exam-settings permission check and saves only the optional group. It remains available after the first attempt.

## Consequences

Staff can regroup an exam without changing its settings, participants or results. The session determines the branch of the catalog, and both write paths validate the group against that branch.
