# 0038 — Quick employee entry and CSV import

- **Date:** 2026-10-31
- **Status:** accepted

## Context
When an organization starts using SaraSystem it already has many employees (a school has dozens of teachers and staff), each needing an account with a role in their tenant. Hiring ([ADR 0016](0016-workflows.md)) covers outside applicants: public form, SMS check, workflow steps, final approval. Putting existing staff through it one by one is too slow. «کاربران → ایجاد کاربر» makes an account with roles, but it asks for a password, creates no `Employee` row (so no contracts/documents tabs), and works one person at a time.

## Options considered
- **Extend «ایجاد کاربر»** — already there; but users are a core screen and `Employee` belongs to the module, and it still makes one person at a time.
- **A minimal form plus a file import on «کارکنان → کارمندان», sharing one service with hiring** — the screen where employees are managed; one set of rules for all three paths.
- **Import format: Filament's CSV importer vs. XLSX through `maatwebsite/excel`** (installed for exports) — Filament's is built in: column mapping by header, an example file (UTF-8 BOM, opens in Excel), queued chunks, a completion notification and a CSV of failed rows with the reason. XLSX is friendlier but needs our own upload, mapping, queueing and error report. Excel saves CSV («CSV UTF-8»).

## Decision
- **[RegisterEmployee](../../Modules/Employee/app/Services/RegisterEmployee.php)** makes a person an employee of a tenant: reuse the user with the national code (only if the actor may manage them, ADR 0003; identity left as is) or create one with a random password, give the roles the actor may give there (optionally the panel role), create the active `Employee` row. Refusals are `EmployeeException`s. `HireApplicant` now uses it, then closes the application, moves documents and dispatches `ApplicantAccepted`.
- **«ثبت سریع کارمند»** ([QuickCreateEmployeeAction](../../Modules/Employee/app/Filament/Actions/QuickCreateEmployeeAction.php)) on the employees list, for the current tenant: national code, name, mobile, job title (required); personnel code, employment type (default full-time), start date (default today) (optional); at least one role, and «دسترسی به پنل مدیریت» (off by default). For an existing account, name and mobile are hidden. «ثبت و بعدی» keeps the job details and roles for the next person.
- **«ورود از فایل»** ([EmployeeImporter](../../Modules/Employee/app/Filament/Imports/EmployeeImporter.php)): Filament CSV import into the current tenant (passed as an option; the importing user must hold `Create:Employee` there). Same columns; Persian digits, Jalali dates (`1404/07/01`) and employment types by label are accepted. Roles chosen in the modal apply to rows whose «نقش‌ها» column is empty; a row can name its own roles by label or name. Each row is one savepoint, so a failed row doesn't spoil its chunk.
- No password and no welcome SMS: the person signs in with an SMS code to their mobile ([ADR 0004](0004-authentication.md)) and may set a password in `/profile`.

## Consequences
- The rest of an employee's file (documents, contracts, details) is completed later; nothing marks a file as incomplete yet.
- The import runs on the queue (`queue` service locally; restart it after deploying code changes). Tables: `imports`, `failed_import_rows` (failed rows keep their data, including national codes and mobiles, until deleted) and the existing `job_batches`.
- A mobile shared by several accounts can't be used to sign in; that person signs in with their national code (ADR 0004).
- No XLSX upload yet; it can be added with `maatwebsite/excel` if CSV proves awkward for users.
