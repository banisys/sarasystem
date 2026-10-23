# 0010 — Site pages (page builder, step 1)

- **Date:** 2026-10-23
- **Status:** accepted

## Context
Each tenant needs public pages at addresses it chooses, e.g. `/about-us`. A page has a static header and footer, and its content will be built from widgets (step 2). Step 1: create, list, edit and serve pages. Legacy LMS has pages and blocks in its `Core` module; this is a fresh design, not a port.

## Options considered
- **Where the code lives:** in the core vs a module. Pages are a business feature, so they go in the **`Site` module** (ADR 0005). But the public URL space (`/`, `/{tenant}/...`) is core (ADR 0009), and the core must not depend on a module.
- **Routing:** a module route per page vs one catch-all. A catch-all **fallback route** in the core runs after every other route (modules' too, whatever their load order) and hands the path to resolvers that modules register.
- **Which tenant a page belongs to:** the panel's current tenant only, or a field in the form. The user chose a **tenant field** (default: current tenant).
- **Tenant home pages:** stay fixed, or can be built too. The user chose: a page at path `/` **replaces** the tenant's default home page.

## Decision
- **Core** ([app/PublicSite](../../app/PublicSite)): `Route::fallback(PublicSiteController)` (and `/`). `PublicSite::locate()` splits the request path: a first segment naming a non-primary active tenant selects that tenant, anything else is a path on the primary tenant's site; the primary's own slug redirects to the URL without it. Then every service tagged `PublicSite::RESOLVER_TAG` (`PageResolver`) may answer; otherwise `/` shows the default home page (`Tenants/Home`) and anything else is 404. `PublicSite::url()` builds public URLs; `PublicSite::layoutProps()` gives the static header/footer data (`site` prop, `PublicLayout.vue`).
- **Module `Site`:** `site_pages` (`tenant_id`, `title`, `path`, `is_published`; unique per tenant and path), model `Modules\Site\Models\Page` (`BelongsToTenant`), `SitePageResolver` serves published pages as `Site::Page`. Admin: «وب‌سایت → صفحه‌ها» (`PageResource`, Shield permissions `*:Page`).
- **Paths:** `/` or `/seg/seg` with lowercase Latin letters, digits and hyphens; normalized on save (`About-Us/` → `/about-us`). The first segment must not be an app route (`Tenant::routeSlugs()`) or **any** tenant's slug, for every tenant, because any tenant may become primary and serve its pages from the root. In the other direction, the module reserves the first segments of all page paths as tenant slugs (`Tenant::reserveSlugsUsing()`).
- **Tenant field:** options are the user's panel tenants where they hold `Create:Page` (permissions are per tenant); fixed after creation. The list shows the current tenant's pages only (`BelongsToTenant` scope; Filament's own tenant scoping is off because it would force new pages into the current tenant). After creating a page for another tenant, the editor is taken to its edit page in that tenant.

## Consequences
- Unpublished pages can't be previewed yet.
- Paths are Latin only; Persian slugs (`/درباره-ما`) would need URL-encoding rules.
- Widgets (step 2) will add the page content; `Site::Page` has the place for it.
- Every unmatched URL now runs `PublicSite::locate()` (one or two small queries) before its 404.
