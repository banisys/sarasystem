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
Use Element Plus for standard interface components throughout `/profile`, including cards, tabs, tables, form controls, dialogs, collapsible sections, pagination and empty states. Import components and styles where they are used. Set the Persian locale in [AppLayout](../../resources/js/Layouts/AppLayout.vue) and apply the product theme and RTL adjustments in [profile.css](../../resources/css/profile.css). Use the shared [panel components](../../resources/js/Components/Ui) for repeated patterns. Keep Inertia links for navigation, and Laravel for validation and server pagination.

## Consequences
Panel forms use [PanelFormField](../../resources/js/Components/Ui/PanelFormField.vue) for stacked labels above Element Plus controls. The shared style uses muted 14 px text, a 6 px gap, and a small inset on the leading edge in both themes. Compact controls in tables and inline answers retain their layouts. Validation errors still come from Laravel through Inertia.

The public frontend and Filament keep their own controls; the public app entry does not register Element Plus. The class calendar keeps its Jalali grid and server-provided sessions. The deadline picker shares the same Jalali calculation as the public date field and uses Element Plus controls inside a dialog. The file picker queues files with Element Plus, then the existing Inertia form sends them when it is saved. Exam timing, question reordering and 3D previews retain their domain logic.

[ADR 0101](0101-user-panel-dark-theme.md) defines dark mode for the panel and its Element Plus controls.
