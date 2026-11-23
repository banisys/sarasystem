# 0124 — User panel content width

- **Date:** 2026-11-23
- **Status:** accepted

## Context

The user panel needs a readable layout on wide screens. The academic calendar needs the available width for its columns.

## Options considered

- Set the width on each page so it can fit its own content.
- Set a shared width and let the academic calendar opt into the full width.

## Decision

Center the desktop sidebar and main content together within a 1,280-pixel container at the default root font size. The [academic calendar index](../../Modules/Class/resources/js/Pages/Index.vue) opts into full width through [AppLayout](../../resources/js/Layouts/AppLayout.vue).

## Consequences

New panel pages inherit the shared width. Session details use that width as well. On smaller screens the panel fits the viewport with its existing responsive padding.
