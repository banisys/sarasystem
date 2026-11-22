# 0123 — Status notifications in the user panel

- **Date:** 2026-11-22
- **Status:** accepted

## Context

The user panel displayed successful actions as inline alerts on individual pages. Users asked for the same kind of corner notification they see in the admin panel.

## Options considered

- Keep the inline alerts. They are easy to place beside a form but can appear outside the visible area after a save.
- Render the existing Element Plus notification in the shared Vue layout. It supports stacking and timed dismissal and can inherit the panel theme.

## Decision

[AppLayout.vue](../../resources/js/Layouts/AppLayout.vue) displays successful operations as a dismissible corner notification for six seconds. It appears at the top left in Persian and at the top right in left-to-right layouts.

[HandleInertiaRequests](../../app/Http/Middleware/HandleInertiaRequests.php) exposes existing session `status` messages as Inertia flash data for `/profile`. The layout also accepts status messages from client-side operations through `router.flash('status', message)`.

## Consequences

Browser history does not replay status notifications. Validation errors and warnings about the current page stay visible in context. Public pages keep their inline flash messages, and the Filament panel retains its own notifications.
