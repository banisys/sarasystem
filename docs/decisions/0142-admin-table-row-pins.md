# 0142 — Admin table row pins

- **Date:** 2026-11-25
- **Status:** accepted

## Context

Staff need to keep a few records at the top of the admin lists they work in.

## Options considered

- Keep pins in the browser session, where they expire with that session.
- Persist personal pins in the database so they follow the user across sessions.

## Decision

The shared [PanelTable](../../app/Filament/Support/PanelTable.php) adds «سنجاق کردن» and «برداشتن سنجاق» to the row actions menu. Pins are saved per user, branch and table, including the owner of a relation table. Each action uses a row resolved by Filament through the current table's query.

Pinned rows show a pushpin in place of the row actions ellipsis and a tinted background in both panel themes. They come first among records matching the filters. Database tables apply this order before pagination, and the table's usual sort orders rows within each section. The modules list uses the same pin action on its string record keys.

## Consequences

Pinning is a display preference available to viewers of a row. Staff can switch to drag-to-reorder mode for tables with a shared manual order. That mode uses the stored manual order and suspends the personal pins while it is open.
