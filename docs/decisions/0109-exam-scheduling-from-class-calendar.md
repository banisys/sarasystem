# 0109 — Schedule exams from the class calendar

- **Date:** 2026-11-17
- **Status:** accepted

## Context
Class staff already use the calendar to find teaching and exam sessions. The work of adding, managing and editing exam sittings also had a separate entry point.

## Options considered
- Continue maintaining a dedicated schedule list alongside the calendar.
- Use the calendar for adding sittings and the session details for actions on a single sitting.

## Decision
The «افزودن» menu above the class calendar offers «برنامه امتحانی». The exam session details offer «ویرایش» for the sitting, «آزمون جلسه» for creating or editing the linked online exam, and «برگهٔ آزمون جلسه» for the printable paper. Saving the new online exam or cancelling its creation returns to the session details. The session and exam actions use their existing permissions.

## Consequences
The old exam schedule URL redirects to the class calendar after checking class access. Existing links continue to lead to the class. The exam session editor keeps its time and conflict checks under [ADR 0097](0097-edit-class-exam-sessions.md). The printable paper keeps the staff access rules in [ADR 0107](0107-single-exam-and-staff-paper.md).
