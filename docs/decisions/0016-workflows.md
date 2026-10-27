# 0016 — Approval workflows and the cartable; hiring as the first workflow

- **Date:** 2026-10-26
- **Status:** accepted

## Context
After someone sends the «همکاری با ما» form (ADR 0014), the application has to be reviewed in several steps until an employee is created. The user wants to design these steps in the admin panel. Each step sends the application to someone's cartable, and that person can approve, edit, reject or send it back.

LMS had a generic cartable in `Core`: records such as leaves and contracts went through entity groups and verifiers, and each step was logged in `cartable_records` (about 10k rows). Other approval flows will come later (leave, contracts), so the hiring steps should not be a hiring-only feature.

The user decided:
- A step goes to a role in a tenant, or to one person.
- When several people hold the role, one approval is enough.
- No forwarding to a tenant chosen at run time; every step has a fixed tenant.
- Every job position can have its own workflow.
- In-panel notifications only (no SMS for now).
- What an "employee" contains is left to us.

## Options considered
- **Steps hard-coded in the Employee module (status enum):**
  - Pro: the simplest option.
  - Con: steps can't be designed in the panel.
  - Con: every later approval flow would start again from zero.
- **A package** (`symfony/workflow`, `spatie/laravel-model-states`):
  - Pro: ready-made state machines.
  - Con: definitions live in code.
  - Con: no cartable, no idea of roles held per tenant, no panel designer.
- **A small engine of our own, in the core** (chosen):
  - Pro: built around roles per tenant, Filament and our tenancy.
  - Pro: modules plug in their own kinds of records.
  - Con: we build and maintain it.
- **Where the engine lives:**
  - A `Workflow` module would make Employee (and later modules) depend on another module, which ADR 0005 wants to keep rare.
  - Like LMS's Core cartable, it is platform infrastructure, so it goes in `app/`. The core does not know module models; modules register them.

## Decision
**Engine in the core** (`app/Workflow`, models `Workflow`, `WorkflowCase`, `WorkflowCaseAction`):
- **Workflow («فرایند»):**
  - Belongs to a tenant and is designed in «کارتابل → فرایندها».
  - Fields: name, `subject_type` (the kind of record), active flag, and an ordered list of steps (jsonb).
  - A step has a name, a tenant, an assignee and the extra actions allowed in it.
    - The assignee is a role (anyone holding it in that tenant) or a person (a member of that tenant).
    - The extra actions are chosen from: reject, return to an earlier step, edit the record.
    - Approving, claiming and adding a note are always allowed.
- **Case («پرونده»):**
  - One record going through a workflow (`workflow_cases`, polymorphic `subject`).
  - It belongs to the subject's tenant (`BelongsToTenant`).
  - It keeps a **copy of the steps** taken when it started, so editing or deleting a workflow never breaks running cases. Changes apply to new cases only.
  - The current step's assignee (tenant, role or user) is copied into columns for the cartable query.
- **Cartable («کارتابل من»):** cases whose current step is the user's in the current panel tenant.
  - Access comes from the step assignment (`WorkflowCase::assignedTo()`), not from Shield permissions. The resource is excluded from Shield and `WorkflowCasePolicy` allows every staff member.
  - The case may be owned by another tenant (applications are the primary tenant's, a step may be in a branch). The cartable therefore reads cases without the tenant scope, and the assignment is the authorization.
  - The subject is loaded through the case without its tenant scope, and so is the history (`workflow_case_actions` has no `tenant_id`; it is read only through its case).
- **Engine** (`WorkflowEngine`): every change goes through it.
  - It locks the case and checks the actor is still the assignee.
  - It checks the case is still at the step the actor saw, so a double submit or a race with another holder of the role is refused.
  - It writes the history and calls the subject's hooks.
  - A failing hook (`WorkflowActionDenied`) undoes the whole action.
- **Actions:**
  - Approve: to the next step. At the last step this completes the case, with extra fields the subject asks for.
  - Reject: needs a reason and closes the case.
  - Return: to any earlier step, with a reason.
  - Edit: through the subject's form; the history keeps the old and new values.
  - Note: for example an interview result.
  - Claim / release: a claimed case leaves the cartable of the role's other holders. Claiming is optional; the first approval wins anyway.
- **Notifications:** Filament database notifications (the bell), sent to every assignee when a case enters a step. They are sent at once, not queued, so the cartable works without a queue worker.
- **Subjects:** a module describes its records with a `WorkflowSubject` and registers it in `WorkflowSubjects`. The subject provides:
  - title and details for the cartable
  - extra actions
  - the edit form
  - completion fields
  - hooks: `moved`, `completed`, `rejected`
  - where its workflows can be designed

**Hiring** (`Modules/Employee`, `JobApplicationSubject`):
- **Which workflow:** each job position may name a workflow. A new application starts it at once; a position without one only files the application. «شروع فرایند استخدام» on the applicant's page starts one by hand (for example for older applications).
- **Who designs it:** hiring workflows can be designed only in the primary tenant, which owns the applications. Their steps may go to any tenant.
- **Application status:**
  - `submitted` while at the first step
  - `in_review` after it
  - `rejected` / `hired` when the case ends
- **The applicant sending the form again** (open question of ADR 0014):
  - allowed while the case is at the first step and unclaimed; it is noted in the history;
  - refused once a reviewer has it.
- **Editable in a workflow:** job position, names, contact fields and address. Not education or work history, birth date or nationality.
- **Final approval** asks for:
  - the tenant where the person will work
  - employment type
  - start date
  - personnel code (optional, unique)
  - roles there, limited by ADR 0003: only roles the approver could assign there
  - admin-panel access (`panel_user`)
- **What the final approval does** (`HireApplicant`):
  - **Account:** reuses the user with the same national code if the approver may manage them (ADR 0003), and leaves their identity untouched. Otherwise it creates a user: mobile marked verified (it was confirmed by SMS), email left empty if another user has it, random password (they log in with an SMS code).
  - **Roles:** assigns the chosen roles.
  - **Employee:** creates an `Employee` row.
  - **Application:** marks it `hired`.
- **Employee** (`employees`, «کارکنان → کارمندان», per tenant):
  - user, job title, personnel code, employment type (full time / part time / contract / hourly), status (active / suspended / left), start and end dates, the application it came from
  - unique per tenant and user
  - identity stays on the global user

## Consequences
- **Other flows:** leave, contracts and similar flows only need a `WorkflowSubject` and a designed workflow.
- **No branching yet:**
  - no conditions inside a workflow (choosing a workflow per job position covers the common case)
  - no parallel approvals
  - no deadlines or escalation
  - no per-step forms beyond notes
- **Stuck cases:** a case whose step role is deleted, or whose step person leaves, stays stuck. Nobody can cancel or reassign a running case yet.
- **Hiring roles:** the final approver can only give roles whose permissions they hold in the chosen tenant. A head-office approver hiring into a branch where they hold no roles can only give permission-less roles there (and panel access). Give the approver a role there, or use a super admin.
- **Lost race:** a case that moved on while someone had it open is no longer in their cartable, so their next click on it ends in "not found".
- **New pieces:**
  - The `notifications` table and `->databaseNotifications()` are now part of the panel.
  - `WorkflowCaseResource` is excluded from Shield generation (`config/filament-shield.php`).
