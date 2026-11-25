# 0047 — Class sessions on a calendar

- **Date:** 2026-11-03
- **Status:** accepted; the details modal is replaced by a page ([ADR 0050](0050-session-page-in-new-tab.md))

## Context
The class page's «جلسه‌ها» tab was a table. A class has dozens to hundreds of sessions a year, and the people running it ask "what happens this week?", "which past sessions were never recorded?", "how does the term look?". A list sorted by time answers these poorly. The user asked for the sessions on a calendar with four views (day, week, month, year), each session opening its details in a modal, and colours by status.

## Options considered
- **A — A calendar package (FullCalendar via a Filament plugin)** — drag and drop, many views for free / a new dependency, no Jalali month or year grid out of the box, Saturday-first weeks and Persian labels to configure, and the session actions would have to be rebuilt in its event callbacks.
- **B — Our own Blade calendar in the relation manager** — Jalali periods we already compute for «کلاس‌های من» (ADR 0026), the panel's Tailwind theme, and the existing Filament session actions reused as they are / no drag and drop.

## Decision
**B.**
- [SessionCalendar](../../Modules/Class/app/Services/SessionCalendar.php) owns the periods (day, Saturday-to-Friday week, Jalali month, Jalali year; «کلاس‌های من» uses the same ones) and lays a class's sessions out in them: a timeline of hours for day / week (at least 08:00–18:00, wider when a session needs it), a month grid, and twelve small months for the year with each day coloured by its sessions.
- The day and week timelines place overlapping sessions side by side, including a cancelled session and its replacement. Sessions outside an overlap use the full width of their day.
- Each session has a **tone** worked out when shown, not stored: «پیش رو» (blue), «در حال برگزاری» (primary, pulsing), «گذشته، ثبت‌نشده» (amber: its time is over and it is still `scheduled`, so neither attendance nor content was recorded), «برگزارشده» (green), «لغوشده» (red, struck through). Holidays are grey, Fridays and holidays have red day numbers. The legend below the calendar shows how many sessions of each tone the period has.
- A session opens `SessionActions::details()` («جزئیات»): status (and why it is amber or cancelled), kind, time and where it moved from, subject, teacher / substitute, place, meeting link, attendance by status, content or the plan's forecast, homework with hand-ins and grades, files, note. The row actions are its footer buttons (attendance, content, homework; the rest under «سایر»), so nothing new had to be written for them; «حذف» also closes the details. The same action is now the first row action of both session tables.
- The calendar's details action is a page action of the relation manager that finds the session among **this class's** sessions (argument `session`), not a table action, so the table's filters (from today, by default) don't hide past sessions.
- **Delivery mode:** sessions are «حضوری» or «آنلاین» ([ADR 0115](0115-session-delivery-mode.md), [ADR 0116](0116-in-person-and-online-delivery-modes.md)). The timelines and month grid show the mode icon on each session. The details show «نحوه‌ی برگزاری».
- **Date navigation:** the toolbar has previous and next period buttons and «امروز». «جلسه‌ی قبلی» / «جلسه‌ی بعدی» in the day and week views jump to the nearest day with a session before / after the period. The day view has a small Jalali month beside the timeline for selecting a day directly.
- **Filtering:** Both filters sit at the left end of the RTL toolbar. «همهٔ درس‌ها» lists the subjects used by the class's sessions, including inactive subjects used in past sessions. «همهٔ روش‌های برگزاری» offers «حضوری» and «آنلاین». The filters work together in all four views, including session totals, the small month and links to previous or next sessions. Clearing the subject filter also includes sessions without a subject within the selected delivery mode.
- The class page (`ViewClassroom`) is full width, so the week and month views have room.
- No list view in the tab (removed on request): the tenant-wide «کلاس‌ها → جلسه‌ها» is the list. The tab's table definition stays, unshown, because the row actions and their tests live on it. The view and date are in the URL (`?sessions=week&on=2026-11-03`), so a reload or a shared link keeps them; an unknown view (e.g. the old `list`) shows the month.
- The calendar header offers «جلسه‌ی غیر درسی» in the «افزودن» menu ([ADR 0141](0141-non-instructional-class-sessions.md)). The colours' legend with each tone's count is below the calendar.
- The «لغو جلسه» action was renamed from `cancel` to `cancelSession`: inside a modal, `cancel` is the name of the modal's own close button.

## Consequences
- No new package; the calendar follows the panel theme in light and dark mode.
- No drag and drop to move a session; moving still goes through «جابه‌جایی» (which checks clashes).
- The calendar shows one class; «کلاس‌ها → جلسه‌ها» (all sessions of the tenant) is still a table.
- Since the table is never shown, the calendar view renders `<x-filament-actions::modals />` itself (the table normally does).
- The year view loads every session of the class in the Jalali year in one query; fine for a class, not meant for a tenant.
