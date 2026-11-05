# 0054 — Each class is a room in one building

- **Date:** 2026-11-05
- **Status:** accepted

## Context
The tenant's building (ADR 0051) drew a window per class and put online-only classes in a second block. A tenant with one in-person and one online class therefore showed two small buildings of one window each, the same picture the organization map (ADR 0052) uses for tenants, so each class read as a building. The user asked for one building with each class as a room in it.

## Options considered
- **Keep the windows, only merge the two blocks** — least code / a window still doesn't read as a room, and the page would look like the map.
- **Rooms seen through an open front** — every room can be seen and picked from outside, nothing to walk through / it is not how a building looks from the street.
- **Rooms inside closed walls, entered with the camera** — looks real / rooms hide each other, and it needs walking or floor-by-floor controls.

## Decision
**One building per tenant, front cut away, a room per class.**
- [building-scene.js](../../Modules/Campus/resources/js/building-scene.js) builds it; the map keeps its blocks with windows from [blocks.js](../../Modules/Campus/resources/js/blocks.js).
- A room has a board, a teacher's desk and learners' desks. Online-only classes are in the same building, with a screen and no learners' desks.
- The state's colour is on the room's floor and back wall: from the corner view the back wall alone is mostly hidden.
- Places without a class are walled off (and the building has at least three floors, [ADR 0055](0055-urban-look.md)).
- The page's default view is more from the front than the map's, so the rooms' insides show.

## Consequences
- The server's data, the card, the live updates (ADR 0053) and the map don't change.
- The furniture is the same in every room; it doesn't show the class's size.
- With very many classes the building is tall and the rooms are small until zoomed.
