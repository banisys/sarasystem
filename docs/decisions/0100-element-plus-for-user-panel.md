# 0100 — Element Plus for the user panel

- **Date:** 2026-11-15
- **Status:** accepted

## Context
The user panel needs consistent tables, forms and dialogs as more staff workflows move to Vue. The rollout began with the question bank list and now covers the shared shell and all user-panel pages.

## Options considered
- Extend the local Vue controls and build the table, pagination and dialog interactions in-house.
- Register Element Plus for the entire app, including pages that do not use it.
- Adopt Element Plus a page at a time and import the required components and styles from each migrated page.

## Decision
Import components and styles where they are used. Set the Persian locale in [AppLayout](../../resources/js/Layouts/AppLayout.vue) and apply the product theme and RTL adjustments in [profile.css](../../resources/css/profile.css). Use the shared panel button, notice and dialog components for recurring patterns. Keep Inertia for navigation, Laravel validation and server pagination.

## Consequences
The shared mobile menu uses the Element Plus drawer. The Reka UI dependency was removed after its last usage was replaced. The public frontend and Filament keep their own controls; the public app entry does not register Element Plus. The class calendar and Jalali date handling, exam timer and question reordering, file selection and 3D previews retain their domain logic.

[ADR 0101](0101-user-panel-dark-theme.md) defines dark mode for the panel and its Element Plus controls.
