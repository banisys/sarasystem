# 0122 — Element Plus Container for the user-panel layout

- **Date:** 2026-11-22
- **Status:** accepted

## Context

The user panel already has a responsive sidebar, a mobile header and a navigation drawer. The layout should use Element Plus while retaining the existing Tailwind styling of its pages.

## Options considered

- Retain the custom layout markup and Tailwind styles throughout.
- Use Element Plus Container components for the shared layout and retain Tailwind for responsive styling.
- Move the panel to a separate Inertia document and replace its Tailwind styles with plain CSS.

## Decision

Use `ElContainer`, `ElHeader`, `ElAside` and `ElMain` in [AppLayout.vue](../../resources/js/Layouts/AppLayout.vue). The panel keeps its existing Tailwind styles and shares the Inertia document and assets with the public site.

## Consequences

The layout change applies to every page using the shared user-panel layout. Page content, route handling and panel navigation behavior follow their existing conventions.
