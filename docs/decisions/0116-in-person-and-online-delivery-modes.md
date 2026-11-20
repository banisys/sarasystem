# 0116 — Keep in-person and online delivery modes

- **Date:** 2026-11-21
- **Status:** accepted (supersedes the blended option in [ADR 0115](0115-session-delivery-mode.md))

## Context

Class editors need two delivery modes and a way to see only sessions of one mode on the class calendar.

## Options considered

- Convert previously blended records to in-person to retain physical room scheduling for those sessions.
- Convert them to online to preserve access to configured online rooms and existing meetings.

## Decision

Forms offer «حضوری» and «آنلاین». The migration converts the former blended value to online in classes, teaching schedules and sessions. It retains stored location data. Online sessions keep using the room configured for their date.

In «تقویم کلاسی», the delivery mode and subject filters apply together in all four views. Session totals and previous or next session navigation follow the selected filters. «همهٔ روش‌های برگزاری» clears only the delivery mode filter.

## Consequences

After conversion, an administrator can set a formerly blended session to in-person if needed. Rolling back the application cannot recover the former blended values from the migrated data.
