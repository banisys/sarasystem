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
Override the view in [resources/views/vendor/filament/components/modal/index.blade.php](../../resources/views/vendor/filament/components/modal/index.blade.php) with three small changes: an `x-init` that focuses the window, a `fi-modal-fullscreen` class, and an include of [modal-fullscreen-button.blade.php](../../resources/views/filament/admin/modal-fullscreen-button.blade.php). The styles are in `theme.css` and `glass.css`.

The button shows wherever the × shows, except on modals that are already full screen (`Width::Screen`).

The choice is one setting for all of a user's modals: enlarging one means every modal opens enlarged until the user brings one back. It lives in an Alpine store ([modal-size-preference.blade.php](../../resources/views/filament/admin/modal-size-preference.blade.php)) and in the browser's local storage under a key with the user's id, as other panel preferences do, so two people sharing a browser keep their own. A database column would follow the user across devices, but needs an endpoint and a migration for a display preference, and a phone and a desktop may well want different sizes. A modal with no button stays at its own size, so nobody is left in a full-screen modal they can't shrink.

Filament's focus trap focuses the modal window on opening, and the browser scrolls a window taller than the screen to show it, which hid the header, the × and this button. So the panel turns that autofocus off (`ModalComponent::autofocus(false)`) and the `x-init` above focuses the window with `preventScroll`.

`ModalDefaultsTest` holds the hash of Filament's original view and fails when an upgrade changes it, with a message to copy the view again and repeat the changes.

In the dark theme, a pane's `backdrop-filter` kept a modal opened inside it within the pane. The pane now drops the filter while such a modal is open, which also fixes those modals at their normal size.

## Consequences
- Every modal gets the button with no change to actions.
- Modals with a custom header or without a × have no button.
- A narrow form stretches to the whole screen, and a user who chose full screen gets small confirmation modals full screen too.
- A modal without a header no longer focuses its first field on opening. Such modals are rare, and modals with a header never did.
- The choice does not follow the user to another browser.
