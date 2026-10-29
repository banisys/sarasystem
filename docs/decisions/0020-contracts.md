# 0020 — Contracts

- **Date:** 2026-10-29
- **Status:** accepted; amendments, renewals and reminders added by [ADR 0021](0021-contract-amendments-renewals-reminders.md)

## Context
Employees and learners sign contracts: an employment contract when someone is hired, a tuition or training contract when a learner is admitted, and new ones each period. LMS has a `Contract` module ([legacy-lms.md](../legacy-lms.md)). Reviewed on 2026-10-29, it:
- keeps every kind of contract in one table, with type ids, roles (`school_finance`, `main_office_finance`, `ceo`) and discount ids written in the code;
- depends on schools, school years and grades;
- keeps most data (amount, wage parameters, approvals) in a `params` JSON;
- has its own two-step finance approval;
- mixes in money: installments, cheques, discounts with CEO approval, payments through the `Sale` module, debt follow-ups and reminders;
- renders the contract text from an HTML template with `[placeholders]` each time it is shown, so changing a template changes old contracts;
- is printed and signed on paper.

Several of its models (warranties, trackers, check lists, categories, payment information) are not used anywhere.

The user chose:
- money (installments, discounts, payments) is not part of this module; it comes later in a finance module;
- each contract type chooses how it is signed: electronically (SMS code), on paper, or either;
- real PDF output, with a package;
- LMS contract data is not migrated;
- the person signs after they are accepted (hired / admitted), in their own user panel. Applicants don't sign.

## Options considered
- **Port the LMS module:**
  - Con: school-only and hard-coded.
  - Con: it brings the money part with it.
  - Con: it duplicates the workflow engine (ADR 0016).
- **Contracts inside Employee and Student:**
  - Con: the same feature twice.
- **A `Contract` module plugged in through small core contracts (as Document, ADR 0019):**
  - Pro: Employee and Student work without it.
  - Pro: hiring and admission workflows can prepare contracts through a step extension.

## Decision
**Core** (`app/Contract`, `app/Events`):
- **`ContractParty` / `ContractParties`:** a module describes a kind of person who signs contracts. It gives:
  - its key and label;
  - its applicant model and member model;
  - the person's name, a search of the tenant's members, and the member's user;
  - the values it puts into contract texts: the shared person tags (name, national code, father name, birth date, mobile, address) and its own (job title, program, …).

  Employee registers `employee`; Student registers `learner`.
- **`ApplicantAccepted` event:** `HireApplicant` and `AdmitStudent` dispatch it with the applicant, the new member and the actor, inside the workflow transaction. Listeners can move what belonged to the applicant. Contracts listen to it. Documents still use `ApplicantDocuments` and could move to the event later.
- `OneTimePassword` gets a sign purpose (`PURPOSE_SIGN`, with the record in the purpose, so a code signs one thing only).

**Module `Contract`:**
- **Contract types** (`contract_types`, «قراردادها → انواع قرارداد»):
  - Defined in the primary tenant only.
  - Each has: a title, the parties it is for, the **text**, the **signature method** (electronic / paper / either), an optional number prefix, and active.
  - **Text:** written in Filament's rich editor. Values are **merge tags** (`{{ person_name }}`), picked from a panel with Persian labels: the contract's own values, the parties' values and the type's added fields.
  - **Fields:** a customizable form (ADR 0017, `contract.contract`, per type). Built-in fields are start date, end date and amount (in rials). The type can add fields (wage, working hours, …), and each added field is also a merge tag.
  - Types are deactivated, not deleted, once used.
- **Contracts** (`contracts`, per tenant):
  - **Draft:** prepared for an applicant (in a workflow step) or a member (in «قراردادها → قراردادها»). Editable and deletable.
  - **Issued:**
    - When issued, the text is rendered **once** and stored with its SHA-256 hash, and the contract gets its number (prefix + per-type sequence).
    - Later changes to the type or the person don't change an issued contract.
    - If the type allows electronic signing, the person gets an SMS.
  - **Signed:**
    - Electronically: by the person, in the user panel («قراردادها», `/contracts`), with an SMS code to their mobile. The time, mobile and IP are kept.
    - On paper: staff record the paper signature, with a scan of the signed copy (private disk).
  - **Cancelled:** an issued or signed contract can be cancelled with a reason.
  - The end date is shown; there is no separate "ended" status.
- **PDF:**
  - Made with `mpdf/mpdf` in the Shabnam font (`resources/fonts/Shabnam*.ttf`).
  - Issued contracts print their stored text. Drafts print the current text, marked «پیش‌نویس».
  - A signature block under the text says how and when the contract was signed.
- **Workflow steps** (`ContractStepExtension`, `extensions.contracts.prepare`):
  - A step can name contract types to prepare.
  - Its assignee fills in each contract in the cartable («تنظیم قرارداد», prefilled when it exists) and can preview it.
  - The step can't be approved until each named contract is prepared.
  - When the applicant is accepted, the drafts move to the member, their tenant and user, and are issued.
- **Permissions:**
  - Shield permissions on `Contract` and `ContractType`. Issuing, recording a paper signature and cancelling need `Update:Contract`.
  - In workflows, the step assignment is the authorization (as in ADR 0019).
  - A person sees only their own non-draft contracts in the user panel.

## Consequences
- Employee and Student don't depend on the module; without it, steps' contract settings take no effect.
- Issued texts are fixed: a wrong issued contract is cancelled and a new one issued.
- No money yet: the amount is a value for the text. Installments, discounts and payments wait for a finance module, which can attach to contracts.
- Not built yet: contract approval workflows, Word import of templates (LMS has a converter), the organization's own signature. Contracts are not shown on the employee / learner pages.
- An electronic signature here is an SMS-confirmed acceptance with an audit trail, not a certificate-based digital signature.
