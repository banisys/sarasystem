# 0069 — User panel with a side menu, opening on a module's page

- **Date:** 2026-11-08
- **Status:** accepted

## Context
After logging in, a teacher came to an empty dashboard (a greeting and the tenant's name) and had to find «کلاس‌های من» among the header links to see their calendar. The header kept every module's link in one row, and it ran out of room as modules added links (classes, wards' classes, score book, report cards, contracts, exams). The user asked for a light user panel with a menu on the right, where a teacher lands on their classes on a calendar.

## Options considered
- **Dashboard shows the calendar** — the teacher sees the week on the dashboard. Duplicates «کلاس‌های من» and puts Class code in the core's page (or needs a widget hook).
- **Redirect after login only** — `LoginController` sends teachers to `/my-classes`. Misses the tenant picker and a tenant switch, which also go to `/dashboard`, and puts Class knowledge in the core.
- **A module offers the panel's home** — a core hook `UserPanel::homeUsing()`; `/dashboard` redirects to the first URL a module gives. Every path into the panel (login, tenant picker, switch, the brand link) goes through `/dashboard`, so they all land there.

## Decision
- [AppLayout.vue](../../resources/js/Layouts/AppLayout.vue) is a side menu at the start of the page (the right in Persian): app name, the current tenant with a switch icon, the modules' links with icons and the current one marked, and at the bottom the admin-panel link (if allowed), the profile (the user's name) and «خروج». Below `lg` it is a top bar with a button that slides the menu in. Plain Tailwind, inline icons ([PanelIcon.vue](../../resources/js/Components/PanelIcon.vue)), no new package.
- Links from `UserPanel::navigationUsing()` take an optional `icon` (a name known to PanelIcon; unknown names get a default).
- `UserPanel::homeUsing(name, fn (User, Tenant): ?string)`: the first provider giving a URL is the panel's home. `/dashboard` redirects there; the shared prop `panelHome` carries it, so the menu drops «داشبورد» and the brand links to it.
- Class registers its home: whoever has classes (teaches a session or is a member) opens on `/my-classes`, the week calendar. That includes learners; a guardian without classes of their own still gets the dashboard, now with a card per menu link.

## Consequences
- A teacher logs in and sees their week at once; the same for learners.
- The dashboard is unreachable for people with a module home. If it gets content of its own later, revisit this (e.g. a «داشبورد» link to a separate route).
- Two modules offering a home: the first registered wins (module boot order). Only Class offers one now.
- Each page view runs every home provider once more (Class: one or two `exists` queries).
