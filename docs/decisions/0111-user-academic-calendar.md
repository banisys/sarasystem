# 0111 — Academic calendar in the user panel

- **Date:** 2026-11-17
- **Status:** accepted

## Context

Users need a monthly schedule of both teaching and exam sessions in the user panel. The existing «کلاس‌های من» page opens on a week, and its desktop month cells show each session's time and class without distinguishing exam sessions.

## Options considered

- Change the existing URL and update internal links, leaving bookmarks to be updated manually.
- Move the named routes to the new URL and redirect bookmarked calendar views and session details with their query parameters.

## Decision

Use «تقویم آموزشی» in the side menu and page title and open the Jalali month by default. Day and week remain selectable. The new path is `/profile/academic-calendar`. Keep the existing calendar route names. The former path now hosts the [class membership page](0128-staff-class-memberships.md) and redirects legacy calendar views with query parameters and session details.

Show teaching and exam sessions in the current tenant where the person is on the attendance roster or has a teaching or invigilation assignment ([ADR 0117](0117-academic-calendar-session-membership.md)). Show the exam label in the month grid and retain the session-kind label in the day and week cards.

Use the class calendar's status classification and colours ([ADR 0047](0047-class-session-calendar.md)) in all views and both themes. Exams use the same status colours and have a separate fuchsia badge.

Display the subject on session cards when available. The class, subject and delivery mode filters are retained in the calendar URL ([ADR 0118](0118-academic-calendar-filters.md), [ADR 0120](0120-academic-calendar-class-filter.md)).

In the month grid, a session opens its details directly. The day number opens the day view.

The session page has a separate staff tab for reading and leaving reminders ([ADR 0119](0119-session-reminders.md)).
The session tabs sit below the page header with extra spacing and align to the right in Persian. The tabs have a responsive horizontal gap. When the row overflows, it scrolls and keeps the active tab in view when its viewport changes.
Choosing a session tab updates the `tab` query parameter on the current URL and preserves its other parameters. Reloading or opening the link restores the selected tab when it is available to the viewer. The details tab is the fallback.

The «مطالب و تکلیف» tab groups the lesson write-up, assignment and files into distinct sections. When the tab has room for two columns, editors see the assignment and file controls alongside the lesson write-up, and readers see the assignment and hand-in alongside the lesson material. Smaller widths stack the sections in reading order.

The «مطالب و تکلیف» tab places [course lessons shared by class staff](0165-share-course-lessons-in-class-sessions.md) beside the homework column when space permits. Shared lesson rows identify their media and attachments before opening, and staff can see the lesson codes in the shared lesson rows. The same submission saves the write-up, homework, files and lesson codes.

The toolbar uses the panel surface, white in light mode, and highlights the active view with white text on green. Previous and next use chevrons with accessible labels, and «امروز» keeps its visible label.

## Consequences

Existing links generated from route names use the new URL. Bookmarked views keep their selected date, view and ward. Access to sessions still follows the user's class roles and teaching assignments, including the read-only guardian view.
