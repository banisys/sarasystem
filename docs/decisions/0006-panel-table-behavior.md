# 0006 — Panel-wide table behavior through a Table subclass

- **Date:** 2026-10-23
- **Status:** accepted

## Context
Every list in the admin panel should behave the same way: row actions in one dropdown in the first column, and no action when a row is clicked, so users can select and copy cell text. Filament's resource list page sets the row URL and row action (view/edit) itself, after `Table::configureUsing()` has run. Because of that, panel-wide defaults alone can't turn row clicks off, and they can't group actions that a resource passes later.

## Options considered
- **Per resource** — each table calls `ActionGroup::make()`, `recordActionsPosition()`, `recordUrl(null)` and `recordAction(null)`. It is explicit, but easy to forget in every new module, and it duplicates the same calls everywhere.
- **Base list page** — our own `ListRecords` that changes the table after `table()`. It doesn't cover `ManageRecords`, relation managers or table widgets, and every generated page would need its parent class changed.
- **Table subclass bound in the container** — `Table::make()` resolves `app(Table::class)`, so binding `PanelTable` replaces every table in one place. It covers all tables and modules automatically. It also relies on overriding Filament getters (`getRecordUrl`, `getRecordAction`, `getRecordActionsPosition`) and `pushRecordActions`, so these must be checked on Filament upgrades.

## Decision
[PanelTable](../../app/Filament/Support/PanelTable.php), bound in `AdminPanelProvider::configureComponentDefaults()`:
- Ungrouped row actions are wrapped in one `ActionGroup`; groups a table already passes are kept as they are.
- Actions sit `BeforeColumns`, unless a table sets a position itself or uses a grid/stack layout.
- Row URL and row action always return `null`.

## Consequences
- Resources keep writing `->recordActions([EditAction::make(), …])`; the dropdown and position come for free.
- No table can make rows clickable. If one ever needs that, add an opt-in to `PanelTable` instead of working around it.
- `PanelTableTest` covers the behavior. If a Filament upgrade renames these methods, that test fails.
