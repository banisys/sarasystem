# 0055 — An urban look for the building and the map

- **Date:** 2026-11-05
- **Status:** accepted

## Context
The building (ADR 0054) and the map (ADR 0052) stood on lawns with pine trees, and a tenant with few classes was a low house. The user asked for an urban look with less green space.

## Options considered
- **Streets and street furniture only** — nothing that can be mistaken for data / a one-storey house on a street still doesn't look urban.
- **Also taller buildings, whatever the number of classes** — looks like a town / height no longer shows the number of classes, and floors without classes need something on them.
- **Also neighbouring buildings that aren't tenants** — a fuller town / they can't be told from tenants on the map, and bring back several buildings on the building page, against ADR 0054.

## Decision
**Taller buildings on real streets, no made-up neighbours.**
- Shared in [blocks.js](../../Modules/Campus/resources/js/blocks.js): roads, crosswalks, cars, lamps, planters, flat roofs with machines, shops, small street trees. All are boxes in the one batch, apart from the trees, so the draw calls stay at four.
- Ground is pavement. The only green is low planters and the street trees, kept low so they hide no room.
- At least three floors. A place without a class has a grey shutter, in a colour no state uses, and the legend says so.
- Map: a grid of streets around the plots, façades in turn with the primary's purple roof kept, and an unused plot in the last row as a car park. Rows are no longer centred, so that buildings stay off the streets.
- Building page: a street corner, the side road on the side away from the default camera.
- Everything is placed by index, with no randomness, so the scene looks the same after a reload or rebuild.

## Consequences
- Server data, cards and live updates don't change.
- Height says less about the number of classes, for tenants with few.
- Cars don't move: frames are drawn only when something changes (ADR 0049).
