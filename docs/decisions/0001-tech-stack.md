# 0001 — Tech stack

- **Date:** 2026-10-22
- **Status:** accepted

## Context
SaraSystem is a redesign of `../LMS` (Laravel 13 + Filament 5 + Blade themes + MySQL). The new system needs an admin panel for staff, plus a public site and a user panel for students, parents and teachers. The user panel needs a modern, app-like UI.

## Options considered
- **Keep the LMS stack as-is** (Blade themes for the front, MySQL): least change, but the legacy theme/block layer and ViewModels come with it.
- **Laravel + Filament admin + Inertia/Vue front + PostgreSQL** (chosen): keeps the team's Laravel/Filament knowledge, gives a modern SPA-like front end without a separate API project, and moves to a stricter database.

## Decision
| Layer | Choice |
|---|---|
| Backend | Laravel (same major as LMS: 13) |
| Admin panel | Filament (5) |
| Public site & user panel | Inertia.js + Vue 3 + Tailwind CSS |
| Database | PostgreSQL |
| Modularity | `nwidart/laravel-modules` |
| Multi-tenancy | `spatie/laravel-multitenancy` |
| Roles & permissions | `bezhansalleh/filament-shield` (on `spatie/laravel-permission`) |
| Impersonation | ~~`stechstudio/filament-impersonate`~~ — replaced by our own service, see [ADR 0008](0008-impersonation.md) |
| Settings | `spatie/laravel-settings` + `filament/spatie-laravel-settings-plugin` |
| Tags | `spatie/laravel-tags` + `filament/spatie-laravel-tags-plugin` |
| Media / uploads | `spatie/laravel-medialibrary` + `filament/spatie-laravel-media-library-plugin` |
| Excel export | `pxlrbt/filament-excel` |
| Charts (admin) | `leandrocfe/filament-apex-charts` |
| Jalali dates (admin) | `ariaieboy/filament-jalali` |
| AI dev tooling | `laravel/boost` (MCP server + AI guidelines for coding agents) |

## Consequences
- **No copy-paste from LMS.** LMS code relies on MySQL specifics, Blade themes, ViewModels and a custom access layer. Features are rebuilt and LMS is used as a reference for business rules.
- **Data migration is a separate project:** MySQL → PostgreSQL, plus a schema redesign. It needs its own ETL scripts (see open questions in `progress.md`).
- **Legacy subsystems replaced by packages:**
  - roles/permissions/model_permissions → Shield / spatie-permission
  - `medias` → media library
  - `tags` → spatie tags
  - `instance_configs` → spatie settings
  - Blade themes/blocks → Inertia pages + Vue components
- **Tenancy design is required before modelling data:** what a tenant is (organization vs school/branch), single DB vs DB-per-tenant, and how spatie-multitenancy works together with Filament's own panel tenancy and with spatie-permission "teams".
- **Front end needs Jalali support too.** `filament-jalali` covers only the admin, so the Vue side needs its own Jalali date library/picker.
- Check that every plugin has a release compatible with the Filament major we use before installing. (Done on 2026-10-23: all compatible with Laravel 13 / Filament 5.10; exact versions in `composer.lock`.)
- Added on 2026-10-23 at the user's request: `tightenco/ziggy` (Laravel route names in Vue via `route()`).
