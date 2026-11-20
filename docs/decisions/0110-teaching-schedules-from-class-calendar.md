# 0110 — Manage teaching schedules from the class calendar

- **Date:** 2026-11-17
- **Status:** accepted

## Context
Class editors use the calendar to find sessions. Teaching schedules had their own page and may generate several sessions or have no sessions on the calendar.

## Options considered
- Keep the dedicated teaching schedule page alongside the calendar.
- Add schedule management to the calendar and let a linked session open its schedule directly.

## Decision
The «افزودن» menu above the class calendar offers «افزودن برنامه درسی». The collapsible «برنامه‌های درسی» section lists the class's schedules and gives editors access to edit or delete them. The teaching session details offer «ویرایش جلسه» for title, subject, delivery mode ([ADR 0115](0115-session-delivery-mode.md)), location and note. This action saves only the selected session, preserves its time, and marks it as manually edited so the recurring schedule does not overwrite the change. The class calendar is where editors manage the recurring schedule.

## Consequences
Old teaching schedule links redirect to the calendar after checking class access. The shared scheduling form uses the existing recurrence, planning and clash checks.
