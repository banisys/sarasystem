# 0037 — Use server validation instead of native browser validation in the admin panel

- **Date:** 2026-10-30
- **Status:** accepted

## Context
The admin panel is Persian, but the browser may interrupt submission with its own validation bubble, such as “Please fill out this field.” These messages depend on the browser's language and prevent the request from reaching the application's Persian validator.

## Options considered
- Remove required, type and constraint attributes from individual fields — duplicates work across resources and can remove useful input semantics and accessibility information.
- Override Filament's form and modal templates — adds maintenance for upstream markup changes.
- Set standard `novalidate` attributes through Filament component defaults — applies consistently to existing and new forms without editing each resource or vendor code.

## Decision
[AdminPanelProvider](../../app/Providers/Filament/AdminPanelProvider.php) sets `novalidate` on schema forms, action-modal windows (which render as forms when submitted), and wizard steps that wrap forms. Filament/Livewire and Laravel still validate submitted data and display the existing Persian messages.

Some controls explicitly call `reportValidity()`, which bypasses a form's `novalidate`. The [admin validation hook](../../resources/views/filament/admin/disable-native-validation.blade.php), registered at body start, cancels native `invalid` events before the browser or Filament tries to display or reveal native validation errors. It registers once and checks the current admin body class so SPA navigation does not apply that behavior outside the panel.

## Consequences
- Native browser validation does not block admin submissions or show language-dependent bubbles.
- Server rules, required markers, input types, accessibility attributes and file restrictions remain in place.
- New admin forms must follow these defaults; a custom raw HTML form with user inputs must also set `novalidate`. Validation rules and Persian field labels still belong in the server-side form/request configuration.
- The public site and Inertia user forms keep their existing behavior.
