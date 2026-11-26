# 0149 — Shared admin rich editor tools

- **Date:** 2026-11-26
- **Status:** accepted

## Context

LMS adds HTML source editing and right-to-left and left-to-right text direction controls to several Filament forms. SaraSystem needs the same tools for content that mixes Persian and other languages.

## Options considered

- Register the tools on each form, as some LMS forms do. This lets individual forms opt in but repeats toolbar and action configuration.
- Register a Filament rich content plugin in the admin panel defaults. This gives all admin editors the tools while allowing each field to control its toolbar.

## Decision

Use a shared admin rich content plugin. The source dialog edits sanitized HTML within the editor's supported document schema and applies it to the field's draft. The direction controls use TipTap's direction command. Browser and PHP extensions retain explicit block direction through editing and rendering, including when resolving contract merge tags.

The toolbar also has a full-screen toggle at its left edge. It expands the existing editor below the top bar and beside the desktop sidebar, using the same panel bounds as [expanded modals](0060-modal-fullscreen-button.md). The editor stays mounted so toggling the view preserves the draft and undo history.

## Consequences

Forms retain their own toolbar and validation settings. The source dialog follows the same save flow as editing in the toolbar, and edited content is still subject to the field's existing validation.
