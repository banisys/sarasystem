# 0115 — Choose delivery mode when scheduling sessions

- **Date:** 2026-11-21
- **Status:** accepted (supersedes the class-wide mode in [ADR 0022](0022-classes.md) and [ADR 0047](0047-class-session-calendar.md)). [ADR 0116](0116-in-person-and-online-delivery-modes.md) supersedes the blended option.

## Context

A class can have in-person teaching and online exams in the same calendar. A class-wide choice cannot describe those sessions or determine which ones use an online room.

## Options considered

- Keep an editable mode on the class and let schedules and sessions override it.
- Set the mode when planning teaching, exams and make-up or extra sessions. Use the class's location and online platform as shared settings.

## Decision

The admin chooses «حضوری» or «آنلاین» in each teaching schedule, each exam row and each make-up or extra session. Teaching schedules copy their mode into the sessions they generate. Editing a teaching schedule changes the mode of untouched future sessions. A staff member can change an individual session in its details.

Existing schedules and sessions receive their class's former mode in the migration. The former class value also supplies the initial selection for existing classes and supports older data producers that omit the mode. New classes start with «حضوری» selected when scheduling.

The class form offers an optional shared online platform and a default location. In-person sessions use a physical location. Online sessions use the configured online room in effect on that date, with the existing access rules. An online exam attached to a session uses its separate Exam module settings.

## Consequences

Calendar cards and session details show the individual session's mode. The building view uses the live or next session's mode when one exists. Rolling back the migration drops individual settings and returns to the former class-wide mode.
