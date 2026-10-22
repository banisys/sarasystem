# 0002 — Tenancy model: tenant = branch/unit, single shared database

- **Date:** 2026-10-23
- **Status:** accepted

## Context
`spatie/laravel-multitenancy` is part of the stack ([ADR 0001](0001-tech-stack.md)). We had to decide what a tenant is and how tenant data is stored. In legacy LMS, each school branch is an `instance`, and most tables carry `instance_id`. Some data is shared across branches: users, people who work in or have children in several branches, and head-office staff (ستاد) who oversee all branches.

SaraSystem is a **generic product**. One installation serves one organization, and that organization can be a school chain (each tenant is a school), a company running internal training (each tenant is a branch or department), or a language institute with several branches (each tenant is a branch).

## Options considered
- **Tenant = organization (SaaS):** each customer organization is a tenant. Rejected, because SaraSystem serves one organization.
- **Tenant = branch, database per tenant:** strong isolation. Rejected, because cross-branch reporting, users who work in several branches, student transfers and head-office views would all need cross-database queries, and migrations would have to run N times.
- **Tenant = branch, single shared database** (chosen): one schema, and tenant data rows carry `tenant_id`.

## Decision
- A **tenant is one organizational unit** of the installation's organization: a school, a branch or a department, depending on the deployment. In code it is always `Tenant`. What the UI calls it (مدرسه / شعبه / …) is a per-installation setting.
- **There is no separate School entity.** The LMS School/instance pair collapses into Tenant. Two schools that shared an LMS instance become two tenants.
- **Head office (ستاد) is a tenant like any other.** Access across tenants comes from roles: global roles such as `super_admin`, or roles held in several tenants.
- **Roles are per tenant:** Spatie permission `teams` is enabled with `team_id` set to the tenant id. A person can be a teacher in one tenant and a manager in another. Global roles (`team_id = NULL`) are used only for installation-wide roles like `super_admin`.
- **The current tenant is chosen after login,** on a single domain. If the user belongs to one tenant it is selected automatically; otherwise they pick one, and can switch later.
- **All tenants share one PostgreSQL database.** There is no tenant connection switching (spatie-multitenancy runs in single-database mode).
- Tenant-owned tables have a `tenant_id` foreign key. A shared `BelongsToTenant` model trait adds a global scope that filters by the current tenant and fills `tenant_id` on create.
- **Users are global** (not tenant-scoped). Their relation to branches goes through memberships/roles, because one person can be a teacher in two branches or a parent of students in different branches.

## Consequences
- **Isolation is enforced by code, not by the database.** Every tenant-owned model must use `BelongsToTenant`, and tests must cover cross-tenant leakage. Raw queries and `withoutGlobalScopes()` need extra care.
- Cross-branch reports are plain queries without the tenant scope. Who is allowed to run them is an authorization question.
- Permission `teams` must be enabled in `config/permission.php` before real data exists. The permission migration has to be re-run (`migrate:fresh` is fine now). The current team id must be set wherever the current tenant is set (`setPermissionsTeamId()`).
- **Domain features must not assume a school.** Concepts such as the Iranian grade tree, academic years Shahrivar–Mordad and report cards are school-specific. They need to be optional or configurable per installation, or modelled generically (e.g. "levels", "terms"), so the same product also works for a company or a language institute.
- Filament has its own panel tenancy (`->tenant(...)`, URLs like `/cpadmin/{tenant}/…`, a built-in tenant switcher). When we use it, Filament's current tenant must be kept in sync with spatie-multitenancy's `Tenant::current()`, so that scopes, queued jobs and caches all agree.
- Queued jobs must be tenant-aware (spatie-multitenancy `queues_are_tenant_aware_by_default`).
- Legacy migration: LMS `instance_id` maps to the new `tenant_id`. Instances that are not branches (`www`, `cpadmin`, `api`, `admin`, dead cafeerent ones) are not tenants.

## Implementation notes
- The implementation is documented in [architecture.md → Tenancy](../architecture.md#tenancy).
- spatie-multitenancy runs in single-DB mode. Its tenant finder is not used, because it runs before the session exists. Middlewares resolve the tenant instead. Switch task: permission team id.
- The installation-wide super admin is a `users.is_super_admin` flag with `Gate::before`, not a role, because with teams every role assignment belongs to one tenant.
- Filament admin panel: use Filament panel tenancy (`->tenant(Tenant::class)`, tenant switcher, `User::getTenants()`/`canAccessTenant()`). A tenant middleware calls `$tenant->makeCurrent()` and sets the permission team id, so that Filament, the spatie current tenant and Shield all agree.
- Inertia side: after login, a tenant picker page (skipped when there is only one tenant), stored in the session, with the same `makeCurrent()` path.
