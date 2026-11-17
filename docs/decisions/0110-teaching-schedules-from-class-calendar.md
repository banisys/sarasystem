# 0110 — Manage teaching schedules from the class calendar

- **Date:** 2026-11-17
- **Status:** accepted

## Context
Class editors use the calendar to find sessions. Teaching schedules had their own page and may generate several sessions or have no sessions on the calendar.

## Options considered
- Keep the dedicated teaching schedule page alongside the calendar.
- Add schedule management to the calendar and let a linked session open its schedule directly.

## Decision
The «افزودن» menu above the class calendar offers «افزودن برنامه درسی». The collapsible «برنامه‌های درسی» section lists all of the class's schedules and gives editors access to edit or delete them. A teaching session linked to a schedule also offers «ویرایش برنامهٔ درسی». Saving from a session returns to the calendar because changing the recurrence may remove that session.

## Consequences
Old teaching schedule links redirect to the calendar after checking class access. The shared scheduling form uses the existing recurrence, planning and clash checks.
