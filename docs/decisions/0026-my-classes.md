# 0026 — «کلاس‌های من» in the user panel

- **Date:** 2026-10-29
- **Status:** accepted

## Context
This is step 5 of the classes plan ([ADR 0022](0022-classes.md)). Steps 2–4 built sessions, attendance and session content, but only in the admin panel, so teachers needed admin access and learners saw nothing. The user asked for daily, weekly and monthly programs for everyone in a class, whatever their role.

## Options considered
- **Where:** admin panel only / **the user panel (Inertia)**, which every user has and which already lists their exams and contracts (ADR 0013, ADR 0020).
- **Whose sessions:** the sessions of every class the user is a member of (any class role, also after leaving, so history stays visible), plus sessions they teach or substitute in, in the current tenant.
- **Calendar:**
  - A JS calendar library: none installed, and adding a package needs the user's approval.
  - **Server-built views:** a day; a Saturday-to-Friday week; a Jalali month as a 7-column grid on wide screens and a list of days with sessions on phones. Holidays (the tenant's and the organization's) are marked. Navigation is plain links (`?view=…&date=…`).
- **Teachers' work:** keep it in the admin panel / **the same rules on the session page** (taking attendance, recording content with topics, homework and files), shown only while `AttendanceRegister` / `SessionContentBook` allow it. Teachers no longer need admin access for their own sessions.
- **Parents:** there is no parent–child link in the system yet (the `parent` role exists, but nothing says whose parent). Parents' views wait for that link.

## Decision
- **Routes** `my-classes.*` (auth + tenant):
  - `GET /my-classes` (`Class::Index`): the calendar and the user's classes, with their roles and the online link.
  - `GET /my-classes/sessions/{session}` (`Class::Session`):
    - details, the online link, what the session covered (or the plan's forecast), homework with its due date, material links (signed);
    - the user's own attendance;
    - the attendance and content forms for who may use them.
  - `POST …/attendance` and `POST …/content`: the same services as the admin actions. Their refusals come back as a `form` error. The content form takes the due date as a Jalali date (`JalaliDateInput`) and files directly.
- **Service** [MyClasses](../../Modules/Class/app/Services/MyClasses.php): the user's sessions query, whether they may see a session, their role in it, and the calendar data.
- **Header link** «کلاس‌های من» (`UserPanel::navigationUsing('classes')`) for users with a class membership or a session to teach in the tenant.
- **Files:** uploads keep the uploader's file name, in the user panel and (via `storeFileNamesIn`) in the admin panel. Medialibrary turns spaces in names into dashes.

## Consequences
- A teacher can do all of their session work without admin access. The admin-panel actions remain for staff and class editors.
- The calendar loads one view's sessions per request; a month of a busy teacher is a few dozen rows.
- No homework submissions yet: learners see the homework and materials but don't hand anything in.
- Parents see nothing until parents are linked to learners.
