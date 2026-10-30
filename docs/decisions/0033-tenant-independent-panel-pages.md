# 0033 — Identify tenant-independent admin pages in the tenant menu

- **Date:** 2026-10-30
- **Status:** accepted

## Context
Some admin pages show installation-wide data even though their URLs contain a tenant slug. Showing that tenant's name in the switcher implies that the displayed data belongs to it. Filament's `isScopedToTenant()` cannot identify these pages: many tenant-owned resources disable Filament's automatic scope and use our own `BelongsToTenant` scope or membership query instead.

## Options considered
- Infer independence from Filament's tenancy setting — would incorrectly label users and module resources as global.
- Maintain a list of route names — duplicates the resource/page structure and needs updating for each create, edit and view route.
- Mark independent resources and standalone pages explicitly — makes the display scope part of their declaration and covers their resource pages automatically.

## Decision
Resources and standalone pages with installation-wide data implement [IndependentOfTenant](../../app/Filament/Support/IndependentOfTenant.php). Initially these are tenants, roles and the access map. [PanelPageContext](../../app/Filament/Support/PanelPageContext.php) resolves the current page or its resource, including the original page during Livewire requests.

The [tenant-menu view override](../../resources/views/vendor/filament-panels/components/tenant-menu.blade.php) displays «مستقل از شعبه» and a globe in the trigger on those pages, including its collapsed-sidebar tooltip. The native dropdown still lists tenants by their real names and supports switching. Other pages retain the current tenant's name and avatar. Primary-tenant-only resources remain tenant-dependent.

## Consequences
- This marker changes presentation only; it does not change queries, authorization, the current tenant or URLs.
- New global resources/pages must opt in with the interface.
- The overridden view is based on Filament 5.10.3. Compare it with the upstream tenant-menu component on Filament upgrades to retain menu improvements.
