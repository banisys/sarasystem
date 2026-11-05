# 0056 — The map of tenants opens from the tenants list

- **Date:** 2026-11-05
- **Status:** accepted

## Context
The user asked to remove the map (ADR 0052) from the navigation menu and open it from a button on the tenants list. That list is core and for super admins only; the map is in the Campus module and open to anyone who may list classes in some tenant.

## Options considered
- **Name the Campus page in the core's list page** — one line / the core would depend on a module (ADR 0005, ADR 0041).
- **A core hook for header buttons, filled by Campus** — the same shape as the row actions hook (ADR 0012) / one more hook.

## Decision
**A hook:** `TenantResource::addHeaderActionsUsing()`. Campus adds «نقشه‌ی {شعبه‌ها}», shown to those who may open the map; with Campus off there is no button.

A tenant's building (ADR 0051) left the menu too: a row action «ساختمان سه‌بعدی» of the tenants list opens that tenant's, through the existing row actions hook.

## Consequences
- The page and its permissions don't change; only its menu item is gone.
- The tenants list is for super admins, and the map and building now link only to each other, so other people who may list classes have no way in from the menus, though their right and the URLs still work.
