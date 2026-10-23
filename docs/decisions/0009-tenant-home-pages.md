# 0009 — Tenant home pages and the primary tenant

- **Date:** 2026-10-23
- **Status:** accepted

## Context
Every tenant (school / branch) needs its own public home page. One of them, the organization's main one, should be the site root (`/`) instead of `/head-office`. The super admin chooses which one.

## Options considered
- **Path per tenant** (`/west`, `/center`) — one domain, no DNS work; but the slug shares the top-level URL space with app routes (`/login`, `/cpadmin`, …).
- **Subdomain or custom domain per tenant** (`west.example.com`) — cleaner separation, but needs DNS, certificates and spatie-multitenancy's domain finder; too much for now and can be added later on top of the same `Tenant` model.
- **Primary tenant as a setting** (spatie settings) vs **a flag on `tenants`** — the flag keeps it next to the data, and a partial unique index guarantees at most one.

## Decision
- `tenants.is_primary` (boolean, unique where true). `Tenant::primary()` returns the active primary tenant; `Tenant::makePrimary()` moves the flag in a transaction. A migration makes the oldest tenant primary, and the seeder makes the head office primary when none is.
- Routes ([TenantHomeController](../../app/Http/Controllers/TenantHomeController.php)): `/` shows the primary tenant's home page (the old `Welcome` page when there is no primary); `/{slug}` shows any other active tenant's page; the primary tenant's own slug redirects to `/`; inactive or unknown slugs are 404. `Tenant::homeUrl()` builds the right URL.
- `/{slug}` is the **last** route in `routes/web.php`, so every other route wins. To keep a tenant from being unreachable, slugs may not be one of `Tenant::reservedSlugs()`: the first segment of every registered route plus every entry in `public/`.
- In the panel's Tenants list: an «اصلی» star column and the row actions «تعیین به‌عنوان اصلی» and «صفحه‌ی خانه». The primary tenant can't be deactivated (make another one primary first).
- The home page does not make its tenant current; it only shows it. Tenant content on the public site is a later decision.

## Consequences
- A future route with a new first segment (e.g. `/news`) makes an existing tenant with that slug unreachable; `reservedSlugs()` only stops new slugs. Check existing slugs when adding top-level routes.
- Moving to subdomains later only changes the routes and `homeUrl()`.
