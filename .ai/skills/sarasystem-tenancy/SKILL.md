---
name: sarasystem-tenancy
description: Implement or review tenant ownership, permissions, branch switching, and cross-tenant isolation in SaraSystem. Use when a feature reads or writes branch data, changes staff access, or adds an admin screen whose data scope needs to be determined.
---

# SaraSystem tenancy

Read the [tenancy conventions](../../../docs/conventions.md#tenancy-rules-see-adr-0002) and [architecture](../../../docs/architecture.md#tenancy). One installation serves an organization. Branches share a database, and user identities are global.

1. Determine which branch owns the record, whose role authorizes the action, and how the request or job establishes its current branch. Keep these decisions explicit in cross-branch flows. For example, a workflow case can be visible to someone assigned to act on it even when another branch owns it.
2. Follow [BelongsToTenant](../../../app/Models/Concerns/BelongsToTenant.php) for tenant-owned models. Review paths that can bypass the model scope, especially raw queries, related-record selectors and background jobs. A valid record ID alone does not authorize access across branches.
3. For admin work, check whether the screen's data is independent of the current branch. Use [IndependentOfTenant](../../../app/Filament/Support/IndependentOfTenant.php) for installation-wide screens. Use [ADR 0033](../../../docs/decisions/0033-tenant-independent-panel-pages.md) when the screen is limited to the primary branch or uses a custom resource scope.
4. Use [InteractsWithTenants](../../../tests/Concerns/InteractsWithTenants.php) to cover an allowed action in the current branch and the relevant denial or exclusion from a second branch. Exercise the feature's actual entry point, including a direct record URL, form submission, export, or job when that is how the record is reached.
