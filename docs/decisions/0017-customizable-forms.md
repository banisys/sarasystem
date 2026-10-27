# 0017 — Customizable forms (built-in field modes and added fields)

- **Date:** 2026-10-27
- **Status:** accepted

## Context
SaraSystem is installed for different organizations: school chains, companies and language institutes (ADR 0002). Their forms differ. In the «همکاری با ما» form (ADR 0014):
- each customer wants some fields of their own (e.g. Saraye Danesh's Kanoon questions, which ADR 0014 dropped because they were customer-specific);
- some customers don't want fields that the form has now (e.g. military status, postal code, résumé).

The same need is expected in other forms later: student registration, course enrollment and staff profiles. The user also wants the form to differ **per job position**.

## Options considered
- **Fixed form + code per customer:**
  - Pro: no new machinery.
  - Con: needs a release per customer and goes against a generic product.
- **Full form builder (every field dynamic):**
  - Pro: total freedom.
  - Con: loses the fields the system depends on and their logic: the one-open-application rule on the national code, the SMS-confirmed mobile, military status for Iranian men only, university fields for university degrees, and copying data into the user and employee at hiring.
  - Con: large to build.
- **EAV table for answers (one row per answer):**
  - Pro: easy to query by field.
  - Con: much more complex for no gain at this size.
- **Hybrid in the core:**
  - Fields the system needs are always asked.
  - Built-in fields get a mode.
  - Added fields are defined per tenant, for the whole form and per owner record. Answers are kept in jsonb with a copy of the field.
  - Pro: keeps the built-in logic and is reusable by every module.
  - Con: answers to added fields are not columns (harder to filter or report on).

## Decision
Hybrid, in the core (`app/CustomFields`, `FormCustomization`):

- **A customizable form** is a `CustomizableForm` that a module registers with `CustomForms::register()`. It has:
  - a key (e.g. `employee.job_application`) and a label;
  - **sections** where added fields can go;
  - **built-in fields** (`BuiltInField`), each with a default mode and the modes it allows.
  - Fields the system depends on are not listed as built-in fields, so they are always asked.
- **Modes** (`FieldMode`) of a built-in field: required / optional / hidden («پرسیده نشود»). A hidden field's input is dropped by validation (`exclude`) and stored as null or an empty list.
- **Added fields** (`CustomField`):
  - types (`FieldType`): short text, long text, number, single choice, multiple choice, checkbox and Jalali date;
  - each field has a label, required flag, options (for choices), help text and section;
  - a field's key is random (`CustomField::newKey()`) and never shown, so the whole form's keys and an owner's keys never clash, and a renamed field keeps its answers' key;
  - a required checkbox must be ticked.
  - No file fields yet.
- **Storage:** one `form_customizations` row per tenant, form and owner. The owner is null for the whole form, or a morph to a record such as a `JobPosition`. The row holds:
  - `field_modes` jsonb: built-in key → mode;
  - `fields` jsonb: the added fields, in order.
- **Owners:** records that can have their own setup use `HasFormCustomizations`, which also deletes the setup with the record.
- **Resolving:** `CustomizableForm::resolve($tenant, $owner)` returns a `ResolvedForm`:
  - the owner's mode wins over the whole form's, which wins over the default;
  - an empty owner mode means "as in the whole form";
  - the owner's added fields come **after** the whole form's.
  - The tenant is passed explicitly, because public forms are validated before any tenant is current.
- **Answers** are sent as `custom[<key>]`. `ResolvedForm` builds their rules, Persian-digit cleanup and stored value. Each answer is stored with a **copy of its field**: `{key, label, type, section, value}`.
  - Changing or deleting a field later does not change how old answers read.
  - Dates are stored as Gregorian `Y-m-d`.
- **UI:**
  - **Admin:** `App\Filament\CustomFields\CustomizeFormAction`. As a header action it sets up the whole form; as a row action (`perRecord`) it sets up the form for one record. `CustomFieldAnswers::section()` shows the answers in an infolist, and `CustomFieldInputs` edits them in a form.
  - **Vue:** `@/Components/CustomFieldInput.vue` renders one added field.
- **«همکاری با ما»** (`Modules\Employee\Forms\JobApplicationForm`):
  - **Always asked:** job, first and last name, nationality, national or foreign code, gender, mobile.
  - **Built-in fields:** father name, birth date, military status, home phone (optional by default), email, postal code, address, education, work history (optional by default) and résumé.
  - **Sections:** personal, contact, education, work and résumé.
  - **Where it is set up:** «همکاری با ما → موقعیت‌های شغلی». The header action «تنظیم فرم همکاری با ما» sets up all positions, and the row action «فیلدهای فرم» sets up one position. Both need `Update:JobPosition`.
  - **Public page:** `/careers` gets each position's resolved form and switches when the applicant picks a position. The request validates against the chosen position's form.
  - **Storage:** answers go to `employee_job_applications.custom_fields`. The built-in columns that can be left out are now nullable.

## Consequences
- A customer can add or remove fields without a release. Per-position differences need no new positions or code.
- Other modules reuse this for their forms (student registration, enrollment, …) by registering a `CustomizableForm` and placing the action and the Vue component.
- Answers to added fields can't be filtered or sorted in lists yet. If needed, query the jsonb (and add a GIN index).
- There are no file fields or conditions (show field B when A is …) yet.
- A built-in field hidden after applications were filed keeps its old values; they still show on those applications.
- The workflow's «ویرایش اطلاعات» follows the form of the position picked in it (it switches when the position changes):
  - a built-in field is required only when that form requires it;
  - the added fields of that form can be changed (`CustomFieldInputs`). Each saved answer gets a fresh copy of its field;
  - answers to fields that form no longer has (removed, or from the previous position) are kept unchanged;
  - the history lists each changed answer with its label and old / new value as staff read it (`Answers::changes()`).
