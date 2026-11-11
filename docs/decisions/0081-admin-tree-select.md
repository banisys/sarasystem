# 0081 — Tree selection in admin forms

- **Date:** 2026-11-11
- **Status:** accepted

## Context

Exam managers need to see the category hierarchy while selecting a parent or assigning a question, and to select several branches when filtering questions.

## Options considered

- Keep a standard select with full path labels. This preserves Filament defaults but makes it difficult to scan or expand a branch.
- Add a reusable Filament field backed by an explicit tree. This keeps option validation and state casting from Filament while adding an expandable, searchable display without a package.

## Decision

- [TreeSelect](../../app/Filament/Forms/Components/TreeSelect.php) accepts nested nodes with IDs and labels. It uses a scalar selection by default and an array with `multiple()`. Search retains ancestor context. Callers supply the permitted nodes and apply their own authorization and domain rules.
- The options open in a native popover flush against the field. This keeps subsequent fields in place and lets the options escape clipping in modals and table filters. The panel opens above the field when there is more room there and scrolls if space is limited.
- Exam uses single selection for a category's parent and a question's category. Editing a category excludes itself and its descendants from the parent selector.
- The question filter uses multiple selection. Results match any selected branch, including its descendants, and «بدون دسته» may be combined with branches. Existing URLs with a single category filter are normalized to the new state.

## Consequences

- The component changes how values are selected. The question's category and category parent remain nullable foreign keys.
- The component loads the permitted taxonomy for display. Callers should choose a bounded tree appropriate for a single form field.
