# 0051 — A tenant's 3D building and the Campus module

- **Date:** 2026-11-04
- **Status:** accepted; the building's shape (windows, a second block for online classes) replaced by [ADR 0054](0054-class-rooms-in-one-building.md)

## Context
After the session's 3D view (ADR 0049), the next step of the 3D idea is to see a tenant as a building, with what happens in its classes now. No floors or rooms are recorded, classes have only a free-text place, and the 3D code now has a second use, so it needs a home. Clients pay per feature (ADR 0041).

## Options considered
- **Where it lives: all in Class** — less code / 3D can't be sold or switched off apart, and the later map of tenants would not belong there.
- **A `Campus` module, with Class giving rooms through a core hook** — a switch of its own, works with any module off / a hook with one source for now.
- **The building: rooms and floors entered by hand** — true to the place / a data entry job for every client before anything shows, and a meaning that doesn't fit online classes or companies.
- **The building: generated from the classes** — nothing to enter, any organization / it is not the real building.

## Decision
**A `Campus` module; a building generated from the classes.**
- Page `Building` («کلاس‌ها ← ساختمان سه‌بعدی», per tenant). One window per running top-level class (active, not ended); sub-classes share their class's room. Only this page loads the script.
- Windows: yellow when a session is on now, blue when one is still to come today, dark otherwise; cancelled sessions don't count. Worked out on the server; the page keeps itself current ([ADR 0053](0053-live-3d-windows.md)) and says the time.
- Classes that meet only online are in a second, glassy block; blended ones are in the main block.
- Clicking a window opens a card: mode, people, the session now or the next one today, links to the class and session in a new tab, when the user may open them.
- Core hook [BuildingRooms](../../app/Campus/BuildingRooms.php) / `RoomSource`; [ClassroomRooms](../../Modules/Class/app/Services/ClassroomRooms.php) is Class's source. Seeing the building needs the right to list classes (`ViewAny:Classroom`) in the tenant. With Class off there is no source and the page is off.
- The camera, controls, names and clean-up moved to the core's [stage.js](../../resources/js/scene/stage.js), because modules can't import each other. Each view keeps its own scene.
- A session's 3D view stays in Class, since it is about attendance, but shows only with Campus on (`Module::find('Campus')`), so one switch controls all 3D.

## Consequences
- No migration, nothing to enter; a new class appears by itself.
- It must not be read as a floor plan: the order of windows follows the titles, and a new class moves the others. The page says it is symbolic.
- A client can have classes without 3D.
- A back side is left for later.
