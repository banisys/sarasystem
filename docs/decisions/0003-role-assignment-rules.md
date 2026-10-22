# 0003 — Role assignment: permission-subset rule

- **Date:** 2026-10-23
- **Status:** accepted

## Context
With per-tenant roles ([ADR 0002](0002-tenancy-model.md)), anyone with `Update:User` could give any role to anyone, including themselves, and including roles with more permissions than their own. Role definitions are global, so anyone allowed to edit a role would also change it in every tenant.

## Options considered
- **Numeric role levels:** each role gets a level, and you may only assign lower levels. Easy to explain, but someone must maintain the numbers (one wrong number reopens escalation), and roles in a generic product (school / company / institute) don't fall into one linear order.
- **Explicit "may assign" lists per role:** flexible, but heavy to configure for every installation.
- **Permission subset** (chosen): you may only hand out what you already have. There is nothing to configure, it stays correct as roles change, and escalation is impossible by construction.

## Decision
For everyone except the super admin (who bypasses all rules):

1. **Assignable roles:** in a tenant, an actor may assign a role only if every permission of that role is among the actor's own effective permissions in that tenant. Roles with equal permissions count as assignable, so a branch manager can appoint another branch manager.
2. **Higher users are off-limits:** an actor may edit, change the password of, or remove from the tenant a user only if **every** role that user holds, **in any tenant**, is assignable by the actor in the current tenant. Identity fields (name, email, password) are global, so a user who is "bigger" anywhere is protected everywhere. Super admins can only be managed by super admins.
3. **No changing your own roles:** the roles field is disabled when editing yourself.
4. **Role definitions are managed by the super admin only.** `RolePolicy` denies everyone else and `RoleResource` is excluded from Shield's permission generation, just like `TenantResource`.

The rules are enforced on the server (the role options and saving both go through the same check), not only by hiding UI.

## Consequences
- Implemented in `App\Authorization\RoleAssignment`. The user resource uses it for the role options, for saving, for `canEdit` and for the remove action.
- A tenant needs at least one manager appointed by the super admin. From there, managers can appoint peers and lower staff.
- A manager cannot edit someone who is a teacher in their branch but a manager in another branch. That is intended, and the super admin or the other branch's manager handles such accounts.
- If a role later gains a permission, holders of weaker roles lose the ability to assign it automatically, without any reconfiguration.
