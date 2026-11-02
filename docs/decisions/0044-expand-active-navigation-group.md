# 0044 — Expand the active admin navigation group

- **Date:** 2026-11-02
- **Status:** accepted

## Context
Filament remembers collapsed navigation groups in the browser. A page's active menu item can therefore be hidden inside a previously collapsed group, including when a user follows a link outside the sidebar.

## Options considered
- Override Filament's sidebar/group templates — duplicates vendor templates and adds upgrade maintenance.
- Extend the existing sidebar hook — reuses the panel's navigation lifecycle and Filament's persisted sidebar store.

## Decision
Extend [sidebar-nav-search.blade.php](../../resources/views/filament/admin/sidebar-nav-search.blade.php) to remove active groups' labels from the sidebar store's collapsed groups after Alpine initialization and on each page initialization, including Livewire SPA navigation. Use Filament's rendered active-group markers rather than matching URLs ourselves.

## Consequences
The current page's group opens even if its collapsed state was saved previously. Other groups keep their state, and users can still collapse groups manually (including with collapse-all) until the next navigation. Search filtering keeps its existing behavior. No vendor templates or dependencies change.
