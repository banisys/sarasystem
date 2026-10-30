# 0034 — Configure requested documents in each public form

- **Date:** 2026-10-30
- **Status:** accepted

## Context
Document types should be reusable definitions, independent of employee/learner forms. Choosing a type's holder and public-upload requirements in «انواع مدرک» mixed the catalog with the configuration of `/careers` and `/preregister`. The user wants requested documents alongside the other form settings, including per-program differences such as «زبان انگلیسی».

This supersedes the type-level holder, applicant-upload and requirement settings in [ADR 0019](0019-documents.md).

## Options considered
- A separate requirement editor and table per module — duplicates the existing form-wide/per-owner inheritance and UI.
- A generic file custom-field type — duplicates the document catalog's file limits, expiry, review, workflow and person-file lifecycle.
- Extend the existing built-in field modes with document fields — reuses required/optional/hidden modes, inheritance and form authorization while keeping the Document module optional.

## Decision
- «انواع مدرک» contains title/instructions, file kind and limits, expiry, staff upload/review roles and active status. There is no «برای کارمند/فراگیر», public-upload toggle or type-wide requirement editor. Every type can be used for any registered holder.
- «برنامه‌های آموزشی → تنظیم فرم پیش‌ثبت‌نام» and each program's «فیلدهای فرم» have a «مدارک» section. The equivalent job-position actions configure hiring documents. Each type is required, optional or not asked. New types default to not asked. An empty per-owner choice inherits the form-wide setting; explicit hidden suppresses an inherited request.
- Core [AdditionalBuiltInFields](../../app/CustomFields/AdditionalBuiltInFields.php) lets a module contribute built-in fields to another form by key. These fields use the existing `form_customizations.field_modes` storage. [DocumentFormFields](../../Modules/Document/app/Services/DocumentFormFields.php) registers `document_{type id}` fields, grouped under «مدارک», for the forms described by each `DocumentHolder`.
- Public form fields, validation, storing and the person's required-document checklist resolve the same modes. Only active types defined by the primary tenant and selected for the chosen form/program/position are accepted. Unknown, hidden and foreign document IDs are rejected; storage also filters them. Existing uploads count when a required document is submitted again.
- Workflow steps still choose their own upload/review types independently. Staff permissions, private files, signed links and transfer on hiring/admission are unchanged.

## Consequences
- Employee and Student depend only on core document contracts, not the Document module. Without Document there are no extra document fields.
- The upgrade migration copies existing applicant-visible type settings into the matching form-wide/per-owner modes, preserving unrelated modes and custom fields. Requirements for a selected owner are copied only when that owner belongs to the primary tenant. Existing documents and files are untouched.
- Legacy `document_types.holders`, `applicant_uploads`, `required_for` and `document_type_requirements` are retained as an upgrade snapshot; application behavior no longer reads them. Rollback is intentionally a no-op: new per-form choices cannot be represented by the legacy flags, so use a forward fix. A later cleanup can remove the archived schema.
- A staff-only requirement belongs in a workflow step; creating a document type never adds an upload request to a public form.
- A disabled type keeps its configured modes but is not requested. Reactivating it restores those requests.
