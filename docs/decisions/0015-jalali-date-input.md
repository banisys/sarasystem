# 0015 — Jalali date input for the Vue front end

- **Date:** 2026-10-26
- **Status:** accepted

## Context
Filament has Jalali pickers (`ariaieboy/filament-jalali`), but the Inertia/Vue pages had none: public forms such as «همکاری با ما» took dates as typed text (`1370/01/05`). ADR 0001 left the Vue-side library open.

## Options considered
- **`vue3-persian-datetime-picker`** — full-featured, but depends on `moment` + `moment-jalaali` (large, moment is in maintenance mode) and its look doesn't match our Tailwind forms; adding it needs approval and a manual install.
- **Another npm Jalali library (`jalaali-js`, `date-fns-jalali`) + our own picker UI** — small, but still a new dependency just for conversion.
- **Own component on the browser's `Intl` persian calendar** — no dependency; every current browser ships the persian calendar in `Intl`. We write and maintain the picker UI ourselves.

## Decision
Own component: [resources/js/Components/JalaliDateInput.vue](../../resources/js/Components/JalaliDateInput.vue) with helpers in [resources/js/jalali.js](../../resources/js/jalali.js). Gregorian → Jalali uses `Intl.DateTimeFormat('…-u-ca-persian')`; Jalali → Gregorian starts from a close estimate and steps a day at a time until `Intl` agrees.

The field stays a text input (people can still type, Persian digits allowed) with a calendar: month/year selects, Saturday-first grid, today marker, «امروز» / «پاک کردن». Its `v-model` is the same `1370/01/05` string, so the server keeps validating and converting with `App\Rules\JalaliDate`. The public field opens a popup; the [panel field](../../resources/js/Components/Ui/PanelJalaliDateInput.vue) uses an Element Plus dialog and the same date logic ([ADR 0100](0100-element-plus-for-user-panel.md)).

## Consequences
- No new date packages; both Vue fields share the picker logic.
- Date only: no time picker or date range yet.
- `minYear` / `maxYear` only limit what the calendar offers; the server rule is still the real check.
