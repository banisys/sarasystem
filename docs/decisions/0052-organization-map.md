# 0052 — A map of the organization's tenants

- **Date:** 2026-11-04
- **Status:** accepted

## Context
The last step of the 3D idea: seeing all the organization's tenants at once, each as a building, and entering one. Tenants have no recorded location. Permissions are per tenant, so a map across tenants must not leak one tenant's data to someone with a role only in another.

## Options considered
- **Layout: real positions on a geographic map** — true to the place / every tenant needs coordinates entered, a map source inside Iran's network limits, and companies' departments have no place.
- **Layout: an automatic town** — nothing to enter, any organization / it says nothing about where tenants are.
- **Who sees it: super admins only** — simple / head office and multi-branch staff get nothing.
- **Who sees it: each person's own tenants** — useful to all / needs a per-tenant check.

## Decision
**An automatic town, for each person's own tenants.**
- Page `OrganizationMap`, implementing `IndependentOfTenant`. A building per tenant of the user's panel (`getTenants()`: active ones) where `BuildingRooms::canView()` holds; primary first, then by name, its roof purple.
- [OrganizationSites](../../Modules/Campus/app/Services/OrganizationSites.php) asks inside `$tenant->execute()` with `$user->withoutRelations()`: the user object remembers its roles, and the old tenant's would otherwise answer.
- The map's data has only each room's state and counts, no class names, teachers or links.
- A click opens a card with counts and «ورود به ساختمان», which goes to that tenant's `Building` page; that page has a button back.
- Names are on by default, since unnamed buildings mean nothing here.
- `blocks.js` holds what building and map share. All boxes are one `InstancedMesh`, so the draw calls don't grow with the tenants.

## Consequences
- Nothing to enter; a new tenant appears by itself.
- It must not be read as geography; the page says so.
- Opening the map runs the room queries once per tenant, and `canAccess()` checks tenants until the first allowed one on every panel page for a user with none allowed: few for such a user, who has few tenants.
- A real map could later replace the layout without changing the data.
