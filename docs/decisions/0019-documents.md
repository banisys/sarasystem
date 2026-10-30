# 0019 — Documents and documents in workflow steps

- **Date:** 2026-10-28
- **Status:** accepted

Type-level holder and public-form requirement settings were superseded by [ADR 0034](0034-document-requests-per-form.md). The catalog is now independent of forms; requested documents are configured with each form's other fields.

## Context
Employees and learners have documents: a scan of the national card and the birth certificate, a degree, a personnel photo, a health certificate. Different roles take part:
- certain roles upload them;
- other roles check them (approve or reject).

In a hiring or admission workflow ([ADR 0016](0016-workflows.md)), a step may have to collect some documents and another step may have to check them before the case moves on. LMS's employee preregister had a documents upload; it was not ported with the form ([ADR 0014](0014-job-applications.md)).

The user chose:
- document types are defined once, in the head office (primary tenant);
- applicants may upload some documents themselves in the public forms;
- some documents have an expiry date;
- documents can be required per job position / program.

## Options considered
- **Documents inside each module (Employee, Student):**
  - Con: the same feature twice; no shared screens.
- **A `Document` module that Employee and Student depend on:**
  - Con: module-to-module dependencies (ADR 0005 wants them rare).
- **A `Document` module plugged in through small core contracts:**
  - Employee and Student describe their people to the core, and the Document module reads that.
  - Pro: Employee and Student work without Document.
  - Pro: the workflow hook can be reused by other modules.

## Decision
**Core contracts** (`app/Documents`, `app/Workflow`):
- **`DocumentHolder` / `DocumentHolders`:** a module describes a kind of person who has documents. It gives:
  - its key and label;
  - its applicant model (job application, preregistration) and member model (employee, learner);
  - what documents can be required for (job positions, programs);
  - a name and a search for the upload screen.

  Employee registers `employee`; Student registers `learner`.
- **`ApplicantDocuments`:**
  - The documents an applicant uploads in a public form: fields for Vue, rules, storing.
  - What happens to them when the applicant is accepted.
  - The core binds a no-op (`NoApplicantDocuments`); the Document module binds the real one. `HireApplicant` and `AdmitStudent` call `accepted()`.
- **`StepExtension` / `WorkflowStepExtensions`:** a module adds work to workflow steps:
  - designer fields, stored in the step under `extensions.{key}` and copied into cases with the steps;
  - lines in the diagram;
  - cartable actions and sections;
  - `beforeApprove()`, which may refuse approving the step.

**Module `Document`:**
- **Document types** (`document_types`, «مدارک → انواع مدرک»):
  - Defined in the primary tenant's panel only.
  - Each has a title, file kind (image / PDF / both), file count/size limits, optional expiry and the **roles that may upload** and **roles that may check** it. Holder and public-form requirements are configured per form instead ([ADR 0034](0034-document-requests-per-form.md)).
  - Types are deactivated, not deleted, once used.
- **Documents** (`documents`):
  - At most one per record and type. Uploading again replaces the files and sets the status back to pending. Old versions are not kept.
  - Statuses: pending, approved, rejected (with a reason).
  - Optional expiry date. An expired or rejected document counts as missing.
  - Files are on the private disk (medialibrary) and are shown only through **signed links valid for 30 minutes**, handed to people who may see the document.
  - A document is first attached to the applicant record in the primary tenant. On acceptance it moves to the member record, its tenant and the user.
- **Who may upload or check** outside workflows: holders of the type's upload / check roles **in the document's tenant** (super admins always).
  - «مدارک → مدارک» lists the current tenant's documents (Shield `ViewAny:Document`), with filters for type, status and expiry (expired / within 30 days).
  - It has actions to upload for an applicant or member of the tenant, view files, approve, reject, upload again and delete.
- **Documents in workflow steps** (`DocumentStepExtension`): each step can name:
  - «مدارکی که در این مرحله بارگذاری می‌شوند»: the step can't be approved until each has files and is neither rejected nor expired. A document the applicant uploaded counts.
  - «مدارکی که در این مرحله تأیید می‌شوند»: the step can't be approved until each is approved.
  - The assignee does this in the cartable («بارگذاری مدارک», «بررسی مدارک»), **whatever the type's roles**: the step assignment is the authorization, as for the rest of the cartable. Each upload / check is written to the case history as a note.
  - The cartable shows a «مدارک» section: the required documents with their status, then any others.
- **Public forms:** «همکاری با ما» and «پیش‌ثبت‌نام» request the documents selected in their form settings (whole form and per position/program), with required / optional / not asked modes ([ADR 0034](0034-document-requests-per-form.md)).
  - Required ones must be sent, unless the open application already has them.
  - They are stored as pending documents with no uploader.

## Consequences
- Employee and Student don't depend on the Document module; turning it off removes the documents and the step settings take no effect.
- A step's document settings are copied into running cases with the steps; changing the workflow doesn't change running cases (as in ADR 0016).
- Checking a document in a workflow step bypasses the type's check roles. Whoever designs the workflow decides who checks.
- No versioning: a re-upload deletes the old files.
- No reminders before expiry yet: it is a list filter.
- Employee and learner pages also have a «مدارک» tab (added 2026-10-29); documents remain available in «مدارک» by person.
