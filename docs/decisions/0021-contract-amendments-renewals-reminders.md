# 0021 — Contract amendments, renewals and reminders

- **Date:** 2026-10-29
- **Status:** accepted

## Context
Contracts ([ADR 0020](0020-contracts.md)) are issued once, with their text fixed. In practice:
- a signed contract changes during its period, e.g. a raise in the middle of the year (an amendment, «الحاقیه»);
- a contract ends and the next period needs a new one (a renewal, «تمدید»);
- staff forget contracts that are about to end, and people forget to sign the contracts issued to them.

LMS had «قرارداد تکمیلی» (a supplementary contract with `parent_id`) for students only, and reminder commands for installments and cheques.

## Options considered
- **Amendments as free text written on each amendment:**
  - Pro: nothing to define in advance.
  - Con: no fields, no reuse, and every amendment is written by hand.
- **Amendments as contract types marked «الحاقیه»:**
  - Pro: the same editor, merge tags, fields and signing as contracts.
  - Con: amendment kinds must be defined in the head office first.
- **Renewal as "copy this contract":**
  - Con: it would copy the old fixed text.
- **Renewal as a new draft whose terms are filled from the previous contract:**
  - Pro: the text comes from the type as it is now.
- **Reminders as a list filter only:**
  - Con: nobody is told.
- **A daily scheduled command:**
  - Pro: staff and people are told.
  - Con: it needs the Laravel scheduler running.

## Decision
- **Amendment types:**
  - A contract type can be marked «الحاقیه است» (`contract_types.is_amendment`).
  - Amendment types are offered only for amendments, never for new contracts, renewals or workflow steps.
  - Every text can use the merge tags of the contract it amends or renews: number, title, start and end dates.
- **Contracts** get `parent_id` and `kind` (`original` / `amendment` / `renewal`).
- **Amendment:**
  - Row action «الحاقیه» on a **signed** contract that is not itself an amendment.
  - An amendment type of the same party is chosen, then its terms are filled in.
  - It is issued and signed like any contract.
  - Its number is the main contract's number plus its own sequence, e.g. `K-12/1`, `K-12/2`. The type's sequence is not used.
- **Renewal:**
  - Row action «تمدید» on a signed contract that is not an amendment.
  - The same type is preselected and another non-amendment type can be chosen.
  - The terms are filled from the previous contract: it starts the day after the previous one ends and lasts as long; the amount and the answers to fields with the same key are kept.
  - A contract has at most one renewal that is not cancelled.
  - The renewal is numbered by its type like any contract.
  - The text says «در ادامه‌ی قرارداد …» and an amendment's says «الحاقیه‌ی قرارداد …».
- **Reminders:** `contracts:send-reminders`, scheduled daily at 08:00 (app timezone, Tehran) by the module.
  - **Expiring:**
    - Applies to a signed contract (not an amendment) that ends within `CONTRACT_EXPIRING_WITHIN_DAYS` days (default 30) and has no renewal that is not cancelled.
    - A bell notification goes to the holders of a role with `Update:Contract` **in the contract's tenant**, and to whoever issued it.
    - It is sent once per contract (`expiry_reminded_at`).
  - **Unsigned:**
    - Applies to an issued contract whose type allows electronic signing.
    - The person gets an SMS `CONTRACT_UNSIGNED_REMINDER_DAYS` days (default 3) after it was issued or last reminded.
    - At most `CONTRACT_UNSIGNED_REMINDER_MAX` times (default 3).
  - The contracts list gets filters for kind and for «تا N روز دیگر تمام می‌شود» / «تمام شده». The end date is coloured when the contract is about to end without a renewal.

## Consequences
- Amendments don't change their main contract's dates or amount. A longer period is a renewal.
- Cancelling a contract doesn't cancel its amendments or renewal.
- The scheduler must run: `php artisan schedule:work` locally, a cron entry for `schedule:run` in production. Without it, no reminders are sent.
- Super admins get expiry reminders only for contracts they issued; tenants whose roles have no `Update:Contract` get none.
- Paper-only contracts waiting for a signature are not reminded about. Staff find them with the status filter.
