# 0043 — «همه‌ی پرونده‌ها»: every case of a tenant, read only

- **Date:** 2026-11-01
- **Status:** accepted

## Context
The cartable ([ADR 0016](0016-workflows.md)) shows only the cases waiting for the user. Managers asked to see every running case, and finished ones, to follow where each record is and who it waits for. `progress.md` had «a monitor of all running cases» under Next.

## Options considered
- **Widen the cartable** with a «همه» filter — one screen, but the cartable's access comes from step assignments (`WorkflowCasePolicy`, no permissions), and mixing "mine to act on" with "everything" would blur that.
- **A separate read-only resource with its own permissions** — chosen.
- **Scope:** all tenants (a head-office view) or the current tenant's cases. All tenants would break the fail-closed tenant scope (ADR 0002) for one screen; per tenant follows it, and most cases of the current workflows (hiring, admission) already belong to the primary tenant.

## Decision
- New resource «کارتابل → همه‌ی پرونده‌ها» ([AllWorkflowCasesResource](../../app/Filament/Resources/AllWorkflowCases/AllWorkflowCasesResource.php), `/cpadmin/{tenant}/workflow-cases`): the cases **owned by the current tenant** (the subject's tenant, `TenantScope`), in progress, approved or rejected. Columns: subject, workflow, status, current step, who it waits for (the claimer, else the person or role, with the step's tenant), start and end; filters by status, workflow and kind.
- «فلوچارت فرایند» (row action, and a button on the case page) opens a page of its own (`workflow-cases/{id}/flowchart`, same permission as the case page) with the workflow's flowchart, drawn from the steps **the case copied** when it started (so later edits to the workflow don't show), with steps done, the current step or the step it was rejected in marked, and under each step what was done there from the case's history: who approved, returned (and to which step), edited (the changes), claimed or noted, when, and their notes; the current step says whom it waits for and since when. The chart is shared with «نمایش فرایند» ([WorkflowFlowchart](../../app/Filament/Workflow/WorkflowFlowchart.php), `resources/views/filament/workflow/flowchart.blade.php`).
- Case flowcharts show only the actual path (2026-11-02): replay history in order, repeating a step for each return visit and keeping each visit’s actions together. Unreached steps, available-action badges and hypothetical return/rejection branches are hidden. A rejection exit appears only where rejection happened; the approval ending appears only for completed cases. The workflow definition view still shows every possible step and action.
- The case page is read only: status, steps, who it waits for, then the cartable's subject / extension / history sections (`WorkflowCaseInfolist::details()`). Acting on a case stays in the assignee's cartable.
- Access: `ViewAny:WorkflowCase` / `View:WorkflowCase` (Shield `manage` limits the resource to these two). The resource checks them itself, because `WorkflowCasePolicy` stays the cartable's. Default roles: `tenant_manager`, and so `org_admin`.

## Consequences
- A head-office manager sees the head office's cases; a case owned by a branch is seen in that branch. A step in a branch does not show the case in the branch's list.
- No cancel / reassign yet (still under Next); this page is where they would go.
- `shield:generate` creates all twelve `*:WorkflowCase` permissions; only the two above are used (the others were deleted from the local DB).
