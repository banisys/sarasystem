# 0141 — Non-instructional class sessions

- **Date:** 2026-11-25
- **Status:** accepted

## Context

The class calendar offered two categories for an additional session and let staff attach a subject. Staff need one way to add a session outside the teaching and exam schedules.

## Options considered

- Keep the previous categories internally and present one form.
- Store one category and remove subject links from those sessions.

## Decision

The «افزودن» menu offers «جلسه‌ی غیر درسی». [SessionPlanner](../../Modules/Class/app/Services/SessionPlanner.php) assigns the category on creation, and PostgreSQL requires an empty subject on every non-instructional session. Its edit form omits the subject field as well. Teaching and exam sessions still use subjects.

## Consequences

The migration consolidates both former categories and clears their subject links. It preserves session IDs and associated records such as attendance. The original category and subject cannot be recovered by rolling the migration back.
