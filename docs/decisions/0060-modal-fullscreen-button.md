# 0060 — Full-screen button on admin panel modals

- **Date:** 2026-11-05
- **Status:** accepted

## Context
Admin panel modals have a fixed width, and long forms need scrolling. Users want to enlarge any modal from a button beside its ×. Filament has no hook for adding a button to the modal header.

## Options considered
- **Script that adds the button to each modal header** — no vendor view copied, but Livewire's morph removes the button and the state on every server response, so it has to be put back each time.
- **Override Filament's modal view** — the button and its state are part of the modal, and Alpine state survives the morph. A copy of a vendor view has to follow Filament upgrades.
- **A full-screen width set per action** — fixed in code, not the user's choice, and every modal would need it.

## Decision
Override the view in [resources/views/vendor/filament/components/modal/index.blade.php](../../resources/views/vendor/filament/components/modal/index.blade.php) with three small changes: `isFullscreen` added to the modal's Alpine data and reset when it opens, a `fi-modal-fullscreen` class, and an include of [modal-fullscreen-button.blade.php](../../resources/views/filament/admin/modal-fullscreen-button.blade.php). The styles are in `theme.css` and `glass.css`.

The button shows wherever the × shows, except on modals that are already full screen (`Width::Screen`). A modal opens at its normal size each time; the choice is not remembered.

`ModalDefaultsTest` holds the hash of Filament's original view and fails when an upgrade changes it, with a message to copy the view again and repeat the changes.

In the dark theme, a pane's `backdrop-filter` kept a modal opened inside it within the pane. The pane now drops the filter while such a modal is open, which also fixes those modals at their normal size.

## Consequences
- Every modal gets the button with no change to actions.
- Modals with a custom header or without a × have no button.
- A narrow form stretches to the whole screen.
