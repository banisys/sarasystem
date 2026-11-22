# 0111 — Academic calendar in the user panel

- **Date:** 2026-11-17
- **Status:** accepted

## Context

Users need a monthly schedule of both teaching and exam sessions in the user panel. The existing «کلاس‌های من» page opens on a week, and its desktop month cells show each session's time and class without distinguishing exam sessions.

## Options considered

- Change the existing URL and update internal links, leaving bookmarks to be updated manually.
- Move the named routes to the new URL and redirect bookmarked calendar views and session details with their query parameters.

## Decision

Use «تقویم آموزشی» in the side menu and page title and open the Jalali month by default. Day and week remain selectable. The new path is `/profile/academic-calendar`. Keep the existing route names and redirect GET links from the former path, including session details.

Show teaching and exam sessions in the current tenant where the person is on the attendance roster or has a teaching or invigilation assignment ([ADR 0117](0117-academic-calendar-session-membership.md)). Show the exam label in the month grid and retain the session-kind label in the day and week cards.

Use the class calendar's status classification and colours ([ADR 0047](0047-class-session-calendar.md)) in all views and both themes. Exams use the same status colours and have a separate fuchsia badge.

Display the subject on session cards when available. The class, subject and delivery mode filters are retained in the calendar URL ([ADR 0118](0118-academic-calendar-filters.md), [ADR 0120](0120-academic-calendar-class-filter.md)).

In the month grid, a session opens its details directly. The day number opens the day view.

The session page has a separate staff tab for reading and leaving reminders ([ADR 0119](0119-session-reminders.md)).

The toolbar uses the panel surface, white in light mode, and highlights the active view with white text on green. Previous and next use chevrons with accessible labels, and «امروز» keeps its visible label.

## Consequences

Existing links generated from route names use the new URL. Bookmarked views keep their selected date, view and ward. Access to sessions still follows the user's class roles and teaching assignments, including the read-only guardian view.
