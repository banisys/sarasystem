# 0112 — Optional titles for individual sessions

- **Date:** 2026-11-17
- **Status:** accepted

## Context

Class editors need to name an existing teaching or exam session so people can identify it on the calendar.

## Options considered

- Use the subject name for every session of that subject.
- Store an optional title on the individual session.

## Decision

Store a nullable title on the session. Editors set or clear it from the session's edit form. Admin and user calendars prefer the custom title and use their existing labels when it is empty.

## Consequences

Sessions of the same subject can carry different titles. Existing sessions keep their calendar labels until an editor sets a title.
