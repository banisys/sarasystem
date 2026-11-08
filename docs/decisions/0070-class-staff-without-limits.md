# 0070 — Class staff work on any session, at any time

- **Date:** 2026-11-08
- **Status:** accepted (changes who and when in [ADR 0024](0024-class-attendance.md), [ADR 0025](0025-teaching-plans-and-session-content.md) and [ADR 0030](0030-homework-submissions.md))

## Context
Taking attendance and recording content opened 15 minutes before a session and closed for teachers 3 (attendance) or 7 (content) days after. They were open only to the session's teachers and substitutes and to members whose class role had the «حضور و غیاب می‌گیرد» / «مطالب جلسه را ثبت می‌کند» flag. A supporter («پشتیبان») of a class had neither flag, so they couldn't record the content of the class's sessions. A teacher also couldn't prepare a coming session's homework and files ahead of time. The user asked for every user with a teacher, supervisor or supporter class role (of any kind) to have no limits on changing a session, past, present or future.

## Options considered
- **Turn the flags on for those roles and lengthen the windows in config** — no code, but still a window, and each organization's roles must be set right.
- **List the roles by name** (teacher, supervisor, supporter) — class roles are made by each organization with its own titles (ADR 0022), so names can't be relied on.
- **Every non-attendee class role is staff** — the existing «شرکت‌کننده است» flag already separates learners from the rest.

## Decision
- **Class staff** = the session's teachers and substitutes, plus every current member of the class whose class role is not an attendee role ([SessionAccess::isClassStaff()](../../Modules/Class/app/Services/SessionAccess.php)). Staff take attendance, record content and grade homework in **every session of the class, at any time, before, during or after**, whatever their role's flags.
- Unchanged: whoever may edit the class works from a few minutes before the start, with no end. An **attendee** whose role has a flag (e.g. a class monitor) keeps the window. The class-role form now shows the two flags only for attendee roles.
- Still refused: a **cancelled** session, until it is restored, since recording would mark it held; and a member who has **left** the class.
- Content recorded before a session starts no longer marks it held, so homework and files can be prepared ahead. Recording again after it starts, or taking attendance, marks it held as before.
- Moving, cancelling, substitutes and make-up sessions stay in the admin panel for whoever may edit the class. «تغییرات» here means what the session page offers.

## Consequences
- A supporter, supervisor or any other staff role can now change a session's attendance years later. Guardians get an absence SMS whenever someone first records an «غایب», even late.
- The config windows (`CLASS_ATTENDANCE_LOCKS_AFTER_DAYS`, `CLASS_CONTENT_LOCKS_AFTER_DAYS`, `CLASS_ATTENDANCE_OPENS_MINUTES_BEFORE`) now apply only to attendee roles with a flag, and the opening one also to class editors.
- A session whose content was only prepared ahead, with no attendance, shows as «ثبت‌نشده» after it ends until someone records it.
- No record of who changed what beyond the last `recorded_by` / `content_recorded_by`.
