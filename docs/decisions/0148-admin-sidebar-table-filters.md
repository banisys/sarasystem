# 0148 — Admin table filters in the sidebar

- **Date:** 2026-11-26
- **Status:** accepted

## Context

Admin table filter dropdowns overlap the list and can make the Apply button hard to reach on long forms.

## Options considered

- Keep the filters anchored to their toolbar button and give the dropdown a sticky footer.
- Open the active table's filters over the navigation sidebar and reserve a separate footer for Apply.

## Decision

Use the navigation sidebar for the default admin table filters. Expand the sidebar when the toolbar filter button is clicked. Only the filter fields scroll. Applying filters closes the panel, and closing on desktop exposes the navigation again.

## Consequences

The [filter view](../../resources/views/vendor/filament-tables/components/filters.blade.php) moves the existing Filament form into the sidebar while its Livewire state and actions stay with the originating table. The shared admin theme controls its layout. Explicit alternative Filament filter layouts retain their own presentation.
