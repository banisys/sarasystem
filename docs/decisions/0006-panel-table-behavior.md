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
- **Full-width lists (2026-11-09):** the admin theme removes the main content maximum width when the page content contains a Filament table (`.fi-main:has(.fi-page-content .fi-ta)`). This covers resource lists, standalone table pages and relation tables across modules without changing each page class. Normal sidebar space and page padding remain; pages without content tables keep their existing width.
- **Default sorting (2026-11-09):** all model-backed lists and relation tables inherit descending primary-key order (`id desc`) from the panel defaults, using the qualified key to avoid ambiguity in joined queries. Resource-specific defaults by title, date, home-page priority or `sort` have been removed. User-selected sorting remains primary, with descending ID as a tie-breaker. Reordering mode still uses its configured order column; array-backed tables without a database ID (the module list) retain their supplied order.
- **Timestamps (2026-11-09):** the shared column setup appends missing `created_at` / `updated_at` columns and reuses existing ones, consistently labelled «تاریخ ایجاد» / «آخرین ویرایش», visible by default, toggleable and sortable, with the Jalali date above a smaller `HH:mm` time and compact column widths. Added columns resolve model timestamp support lazily, after the table query is configured; array-backed tables and models without the corresponding timestamp hide them. This extends the existing shared-table decision rather than repeating declarations in every resource and relation manager.
- **Last touched row (2026-11-08):** a shared `TOOLBAR_AFTER` hook tracks the last record pressed or clicked in each table, using capture listeners so actions that stop propagation and text selection also mark it. A light dashed outline works in both themes; its colour is applied immediately, without transitioning from the row’s text colour. The record key keeps the mark through Livewire updates; another row moves it, and leaving or reloading the page clears it. This is only a visual marker, independent of bulk-selection checkboxes.
- **Arrow keys (2026-11-08):** after a row is clicked, ↑ / ↓ move the marker and focus through that table’s visible records on the current page, stopping at either end and scrolling the row into view. Clicking outside the rows deactivates keyboard navigation. Unmodified arrows only: editable fields, buttons, links, menus and open modals retain their own behaviour.
- **Delete lives in the list only** (user's rule, 2026-10-27): edit and view pages never get a delete button in their header. Each table's row actions carry the delete action with its rules (hidden for protected records, confirmation text). When a generated edit page comes with `DeleteAction` in `getHeaderActions()`, remove it.
- No search box above tables (`->searchable(false)` in the panel defaults). Text look-ups are filters ([TextFilter](../../app/Filament/Support/TextFilter.php)), so all narrowing happens in one place, the filters dropdown. A table that really needs the box can opt back in with `->searchable()`.

## Consequences
- Resources keep writing `->recordActions([EditAction::make(), …])`; the dropdown and position come for free.
- No table can make rows clickable. If one ever needs that, add an opt-in to `PanelTable` instead of working around it.
- `PanelTableTest` covers the behavior. If a Filament upgrade renames these methods, that test fails.
