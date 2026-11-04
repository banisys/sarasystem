# 0050 — A session opens as a page in a new tab

- **Date:** 2026-11-04
- **Status:** accepted

## Context
A session on a calendar opened its details in a modal (ADR 0047). The user asked for it to open in a new tab instead, with the session they clicked marked on the calendar, so they can keep the calendar and several sessions open side by side.

## Options considered
- **A — A page per session, replacing the modal everywhere** — one place for the details and buttons, a real link (new tab, copy, share) / the page needs its own access rule.
- **B — The page from the calendar, the modal kept for «جزئیات» in the lists** — no change to the lists / two ways to show the same thing, to be kept in step.

## Decision
**A.**
- `class-sessions/{id}` ([ViewClassSession](../../Modules/Class/app/Filament/Resources/ClassSessions/Pages/ViewClassSession.php)) shows `SessionActions::detailsEntries()`, with `pageActions()` as header buttons. The breadcrumb goes to the class. Deleting the session goes back to the class.
- On a calendar a session is a link with `target="_blank"`, so middle click and copying the link work too. «جزئیات» in the session lists opens the same page in a new tab.
- **The mark:** Alpine state on the calendar remembers the session last clicked, and it gets a dashed border. The state stays while the calendar is on screen, also when its view or period changes; it is not stored, so a reload clears it, and a new click moves it.
- **Access:** the details were reachable from any calendar a person could open, so `ClassSessionResource::canView()` allows the sessions list permission, the session's own view permission, or the right to see its class. A class's tab needs only the last, so `ViewClassSession` turns off Filament's resource-wide check (the list permission) and applies `canView()` when opened and on every later request. The 3D view keeps its stricter rule (ADR 0049). Another tenant's session is not found.

## Consequences
- The calendar no longer loads a session's record in the modal action; each visible session only adds a link.
- A session's page has a stable address, so it can be shared with someone who has access.
- Changes made in the new tab show on the calendar only after it refreshes.
- Each click opens another tab, also on the same session.
