# Progress

_Agents: update this at the end of every meaningful task. Newest entries first in the log._

## Current focus
Defining scope of the redesign: which LMS features to keep, drop and add.

## In progress
- Page builder (`Site` module, ADR 0010): step 1 (pages) done; step 2 = widgets for page content.

## Next
- Real SMS provider driver (choose provider: Kavenegar / Ghasedak / …; credentials from the user).
- Pick a Jalali date library/picker for Vue.
- Decide how modules ship Inertia pages (resolver for `Module::Page` names, module Vite configs via `vite-module-loader.js`).

## Done
- 2026-10-23 — Site: every tenant has a home page (`/`) in the page list: created with the tenant (existing ones by migration), listed first, fixed path, not deletable; unpublished → default home page. 152 tests pass.
- 2026-10-23 — First module `Site`, page builder step 1 (ADR 0010): pages per tenant with a chosen path (`/about-us`; `/` replaces the home page), list/create/edit/delete in «وب‌سایت → صفحه‌ها», public pages with a static header/footer (`PublicLayout.vue`). Public URLs now go through a core fallback route and module `PageResolver`s. Fixed: the panel's id is set before module discovery (enabling a module gave a 500). 147 tests pass.
- 2026-10-23 — Tenant home pages (ADR 0009): `/{slug}` per tenant, the primary tenant (chosen in the Tenants list, star) at `/`; reserved slugs. ADR 0008 corrected: `stechstudio/filament-impersonate` was already installed (ADR 0001) and is now unused. 131 tests pass.
- 2026-10-23 — Impersonation (ADR 0008): super admins can log in as another user from the user edit page and return with «بازگشت به حساب خودم» (bar in the panel and the user panel). 121 tests pass.
- 2026-10-23 — Users: list shows the number of tenants each user belongs to (sortable, names in tooltip); the edit page lists all their tenants with their roles in each. 113 tests pass.
- 2026-10-23 — Admin panel page «نقشه‌ی دسترسی» (`AccessMap`, super admin only): tenants, users and roles and how they connect, in three views (by tenant / user × tenant matrix / by role) with filters. 111 tests pass.
- 2026-10-23 — Admin panel: switching tenant from the tenant menu stays on the same page (e.g. `/cpadmin/head-office/users` → `/cpadmin/west/users`); record pages and pages without access there fall back to the list / dashboard. 108 tests pass.
- 2026-10-23 — Local demo data (`DemoSeeder`, run by `db:seed` locally): 3 branches, 8 roles with labels/permissions, 35 users (password `password`). 101 tests pass.
- 2026-10-23 — Super admin is now the global `super_admin` role instead of `users.is_super_admin` (ADR 0007). A migration moves existing flags to the role in the head office. Only super admins assign it, no self-removal, system roles protected. 100 tests pass.
- 2026-10-23 — Admin panel: list pages have no search box any more; users, tenants and roles get text filters instead (`TextFilter`, Persian digits allowed). 96 tests pass.
- 2026-10-23 — Admin panel: removed the top-bar global search; all table row actions are one dropdown in the first column and row click is off, so cell text can be copied (`PanelTable`). 94 tests pass.
- 2026-10-23 — Admin panel: removed the LMS `margin-bottom: -5rem` on page header actions; in Filament 5 it hid the header buttons (e.g. «ایجاد کاربر») behind the table, showing as a blue glow in dark mode.
- 2026-10-23 — Seeder gives the local super admin the `panel_user` role in the head office, so they appear in its users list (membership = holding a role in the tenant). Re-running the seeder is safe.
- 2026-10-23 — Adminer service in `docker-compose.yml` (DB web UI on http://localhost:8081).
- 2026-10-23 — Inertia front end uses the Shabnam font too (one font across the whole system).
- 2026-10-23 — Ported the LMS admin-panel UI (font Shabnam, theme + glass dark mode, colors, avatar, sidebar search, table top scroll, persisted filters, panel-wide defaults). Project name fixed to «سرا سیستم» (`APP_NAME`). 90 tests pass.
- 2026-10-23 — Module infrastructure (ADR 0005): core stays in `app/`, business areas in `Modules/`; Filament discovery per enabled module, `Module::Page` Inertia pages (browser + tests), `Modules` PHPUnit suite, `@` Vite alias, custom nwidart stubs (`--inertia`, tenant routes, no API/Blade). Verified with a temporary module, then removed.
- 2026-10-23 — Profile page (`/profile`): email, SMS-confirmed mobile change, password change (no current password within 15 min of an SMS login), `auth.session` logs out other sessions, link in the Filament user menu. 89 tests pass.
- 2026-10-23 — Authentication (ADR 0004): one `/login` for everyone (Filament login removed), identifier = mobile / national code / email, password or SMS code (log driver), rate limiting, tenant picker + dashboard + user-panel layout. 78 tests pass.
- 2026-10-23 — Persian role labels: `roles.label` + `Role::display_name`; Shield RoleResource published and extended (label field/column, Jalali dates); users UI shows labels; seeder labels `panel_user`. 49 tests pass.
- 2026-10-23 — Role assignment rules (ADR 0003): permission-subset rule, no managing stronger users (in any tenant), no changing own roles, role definitions super-admin only; server-side validation. 45 tests pass.
- 2026-10-23 — Admin panel resources: Tenants (super admin only, deactivate instead of delete) and Users (members of current tenant, roles per tenant, add existing user, remove from tenant, super-admin protection). Tenant label configurable via `config/sarasystem.php`. 36 tests pass.
- 2026-10-23 — Tenancy implemented per ADR 0002: `Tenant` model and table, `BelongsToTenant` trait and fail-closed `TenantScope`, permission teams (`tenant_id`), super admin flag + `Gate::before`, Filament panel tenancy (`/cpadmin/{tenant}`), web-side session tenant + switch route, seeder (head office, `panel_user`, local super admin). Tests now run on PostgreSQL; 23 tests pass.
- 2026-10-23 — Tenancy model decided → [ADR 0002](decisions/0002-tenancy-model.md); product framed as generic (school / company / language institute).
- 2026-10-23 — Stack installed & wired (latest versions, all verified compatible with Laravel 13 / Filament 5): Filament 5.10 panel at `/cpadmin` with Shield + ApexCharts; Spatie permission/media/tags/settings migrations run; nwidart modules configured; spatie-multitenancy installed (not configured); Inertia 3 + Vue 3.5 + Ziggy with RTL root view and Welcome page; Laravel Boost (guidelines, skills, MCP via docker). Build and tests pass. Details in `architecture.md`.
- 2026-10-23 — Laravel 13.38 skeleton added (PHP 8.5.9), `.env` on PostgreSQL, migrations run, default tests pass, http://localhost:8000 → 200.
- 2026-10-22 — PostgreSQL 18.6 container running (`postgres:18-alpine`, pulled via ArvanCloud mirror).
- 2026-10-22 — `git init` (branch `main`), `.gitignore`, `docker-compose.yml` (app + PostgreSQL 18).
- 2026-10-22 — Tech stack decided → [ADR 0001](decisions/0001-tech-stack.md).
- 2026-10-22 — Deep survey of `../LMS` (code + live DB, read-only): architecture concepts, module usage, domain facts, dead tables → `docs/legacy-lms.md`, `docs/glossary.md`, `docs/overview.md`.
- 2026-10-22 — Surveyed `../LMS`; module inventory in `docs/legacy-lms.md`.
- 2026-10-22 — Project docs scaffold created.

## Known issues / tech debt
- Site pages: no preview of unpublished pages; Latin-only paths.
- Demo roles have no `*:Page` permissions yet; only super admins see «صفحه‌ها» until roles are given them (Shield role editor).
- `stechstudio/filament-impersonate` is installed but unused (our own impersonation, ADR 0008); remove it with `composer remove stechstudio/filament-impersonate` once approved.
- Adding a top-level route (e.g. `/news`) hides an existing tenant whose slug is the same; `Tenant::reservedSlugs()` only blocks new slugs.
- Impersonation has no per-action audit trail; actions are recorded as the impersonated user (only start/end are logged).
- User identity (name, email, password) is global. Since ADR 0003 a manager can only edit users who are not "bigger" than them anywhere, but a manager can still change, for example, the password of a teacher who also teaches in another branch. Decide later whether password resets should go through the user (email/SMS reset) instead.
- `CLAUDE.md` Boost block still has one generic line recommending Laravel Cloud (from Boost's foundation rules); we don't use Cloud. It is regenerated, so don't hand-edit it.

## Open questions
_(Things that need a decision from the user.)_
- SMS provider for production (and sender number).
- Configurable tenant label per installation (مدرسه / شعبه / …): where to store it (spatie settings?) and how school-only concepts (grades, academic year) become optional.
- Which LMS modules/features to drop, which new ones to add?
- What exactly does TMS cover (vs. existing Course/Academy/Employee)?
- LMS overlaps to resolve: `Feedback` tickets vs `Ticket` module; `Course` module vs `School\Course`; `schl_exam_*` vs `exam_*`.
- Data migration: migrate live LMS data (MySQL → PostgreSQL ETL) or start with a fresh DB?
- Drop the dead cafeerent tables/roles (cars, tours, booking, cip, entity, kanoon, …)? See `legacy-lms.md` → Dead weight.
