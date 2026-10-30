# 0036 — Tenant-independent workflow definitions

- **Date:** 2026-10-30
- **Status:** accepted

## Context
The user wants one shared workflow catalog across the installation. Definitions must not be restricted to the head office or filtered by whichever tenant is selected in the panel. Running cases and the tenant responsible for each step still require isolation.

## Options considered
- Remove the tenant filter in the resource only — would leave creation, relationship selectors and other model queries tenant-dependent.
- Keep the primary tenant as the owner and bypass scopes throughout the application — would retain a misleading ownership model and require repeated exceptions.
- Make the definition model global while keeping cases tenant-owned — one consistent catalog for list pages, record URLs, selectors and execution.

## Decision
- [Workflow](../../app/Models/Workflow.php) no longer uses `BelongsToTenant`. Its definition has no owner tenant; new definitions leave the legacy `tenant_id` null. A migration makes that column nullable and preserves previous values only as upgrade history. IDs, names, step snapshots and existing references are unchanged; definitions are not merged or duplicated.
- [WorkflowResource](../../app/Filament/Resources/Workflows/WorkflowResource.php) implements `IndependentOfTenant`. All tenants show the same definitions and the selector displays «مستقل از شعبه», including create/edit/view pages. Existing workflow permissions still authorize each operation in the current panel context.
- Hiring and admission workflow types are available for design in every tenant, even though their public application records remain owned by the primary tenant.
- Workflow lists and diagrams show aggregate case counts across tenants through `Workflow::cases()`. Case contents remain protected by `WorkflowCase` scoping and cartable assignment checks.
- `WorkflowCase`, its subject ownership, step tenant assignments, notifications and the engine's authorization remain tenant-aware. Shared definitions do not grant access to other tenants' case contents.

## Consequences
- Creation, editing, relationships and lookups use the same global model without scattered scope bypasses.
- A workflow permission grants management of a shared definition, so editing it affects future cases across the installation. Running cases keep their existing step snapshots.
- The former owner column is not accepted for mass assignment. Rollback of the nullable-column migration is intentionally a no-op: a global definition has no previous owner to recover. Use a forward migration for a future ownership change.
- Primary-tenant-only restrictions for programs, job positions and public forms are unaffected.
