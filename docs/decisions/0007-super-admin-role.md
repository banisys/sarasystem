# 0007 — Super admin is a role, not a user flag

- **Date:** 2026-10-23
- **Status:** accepted (replaces the `users.is_super_admin` note in [ADR 0002](0002-tenancy-model.md))

## Context
Until now the installation-wide super admin was a boolean column on `users` (`is_super_admin`), with its own toggle in the user form and its own column in the users list. The user wants super admin to be managed like every other access right, as a role. The problem: with permission teams ([ADR 0002](0002-tenancy-model.md)) every role assignment belongs to one tenant, but super admin must apply in every tenant.

## Options considered
- **Keep the flag** — simple and tenant-free, but it is a second access mechanism next to roles, with its own UI.
- **Shield's `super_admin` role** — Shield checks it with `hasRole()`, which only sees roles in the current tenant. The user would need the role in every tenant, including new ones.
- **Own global `super_admin` role, counted in any tenant** (chosen) — assigned like any role, in one tenant (normally the head office). `User::isSuperAdmin()` checks whether the user holds it in *any* tenant.
- **Same, but only counted in the head office** — needs a "head office" marker on tenants, and adds nothing for security, because only super admins can give the role anyway.

## Decision
- A global role `super_admin` (`Role::SUPER_ADMIN`, label «مدیر کل سیستم»), created by the migration that drops `users.is_super_admin` and by the seeder.
- `User::superAdminTenants()` lists the tenants where the user holds it. `User::isSuperAdmin()` is true if that list isn't empty. `Gate::before` and every former `is_super_admin` check use it.
- Shield's own super-admin feature stays disabled (`config/filament-shield.php`).
- Assigning: the role has no permissions, so the permission-subset rule ([ADR 0003](0003-role-assignment-rules.md)) would let anyone give it. `RoleAssignment::assignableRoles()` excludes it for everyone except super admins.
- No lockout: a super admin can't take `super_admin` away from themselves. The option is disabled in the form, and saving keeps it. Other super admins can remove it.
- `super_admin` and `panel_user` are **system roles** (`Role::isSystemRole()`): they can't be deleted, and their name, guard and tenant can't change. The model refuses, and `RoleResource` hides delete and disables those fields.

## Consequences
- A super admin is a member of the tenant where they hold the role, and shows up in its users list with the «مدیر کل سیستم» badge.
- `isSuperAdmin()` loads `superAdminTenants` once per user instance. Code that grants or removes the role on a loaded user must call `unsetRelation('superAdminTenants')`. `User::makeSuperAdmin()` does this already.
- The `UserFactory::superAdmin()` state gives the role in a `head-office` tenant (created if missing).
