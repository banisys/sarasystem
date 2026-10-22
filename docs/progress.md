# Progress

_Agents: update this at the end of every meaningful task. Newest entries first in the log._

## Current focus
Defining scope of the redesign: which LMS features to keep, drop and add.

## In progress
- 

## Next
- Real SMS provider driver (choose provider: Kavenegar / Ghasedak / …; credentials from the user).
- Self-host a Persian font (e.g. `vazirmatn` npm package; user installs) for the Inertia front end.
- Pick a Jalali date library/picker for Vue.
- Decide how modules ship Inertia pages (resolver for `Module::Page` names, module Vite configs via `vite-module-loader.js`).

## Done
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
