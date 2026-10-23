# 0011 — Site widgets (page builder, step 2)

- **Date:** 2026-10-23
- **Status:** accepted

## Context
A site page's content (ADR 0010) is built from widgets placed in order. The first widget shows free HTML. More types will follow.

## Options considered
- **Storage:** a `site_page_widgets` table (one row per widget) vs a **`content` jsonb column** on `site_pages` holding `[{type, data}, ...]`. The column matches Filament's Builder field directly, keeps a page and its widgets saved together, and needs no joins to show a page. Querying inside widgets is rarely needed.
- **HTML safety:** show HTML as typed (any staff member with `Update:Page` could then run scripts on visitors and on staff browsing the same domain), restrict the widget to super admins, or **sanitize**. Sanitizing keeps the widget usable by branch editors.
- **Sanitizer:** `symfony/html-sanitizer` (already installed, a dependency of `filament/support`) vs HTMLPurifier (new package).

## Decision
- `site_pages.content` (jsonb, default `[]`), edited with a Filament **Builder** («افزودن ویجت»; reorder, delete) in the **page builder** «صفحه‌ساز», a separate page of each record: `/cpadmin/{tenant}/pages/{id}/builder` ([PageBuilder](../../Modules/Site/app/Filament/Resources/SitePages/Pages/PageBuilder.php)). It is reached only from the «صفحه‌ساز» row action in the page list; the page's settings form has neither the widgets nor a link to them (the user's choice).
- **Builder behaviour:** block previews (`blockPreviews()`): «افزودن ویجت» opens a modal with the widget's form, and an item is edited only in a modal (edit icon in its header). The list shows each widget's header only — its title, then the type in parentheses (smaller, lighter; `site::filament.widget-label`, `<bdi>` so Latin text keeps the order in RTL), «— مخفی» when hidden, and its actions — with no body and nothing to fold or expand (the user's choice): the builder is not collapsible and hides item bodies with a class (`[&_.fi-fo-builder-item-content]:hidden`). Blocks still need a preview view for modal editing; it is an empty `site::filament.widget-preview`. An eye button in each header shows/hides the widget (takes effect on save). New widgets are added at the end only (no «درج» insert-between button), and widgets can't be cloned.
- **Common fields of every widget** (`Widget::block()`): `title` (required), `show_title` (show it as a heading above the widget on the site, default off) and `is_visible` (default on). Hidden widgets are left out by `Widgets::render()`. Items saved before these fields existed count as visible, and the page builder fills them in on load (`Widgets::withDefaults()`: title = widget type, visible), because a Toggle reads a missing value as off.
- Widget types are classes extending [Widget](../../Modules/Site/app/Widgets/Widget.php): `type()` (stored, never renamed), `label()`, `schema()` (admin form), `component()` (Vue component in `Modules/Site/resources/js/Widgets`) and `props()` (stored data → component props, run on every view). They are listed in [Widgets::all()](../../Modules/Site/app/Widgets/Widgets.php). `Widgets::render()` turns stored content into `[{component, props}]` and skips unknown types; `Site::Page` renders them in order.
- **HTML widget** ([HtmlWidget](../../Modules/Site/app/Widgets/HtmlWidget.php), `Html.vue` with `v-html`): CodeEditor (HTML). Sanitized with symfony/html-sanitizer **on save and on every view**: safe elements plus `class`/`style`/`id`/`dir`; `<iframe>` with an https `src` only (maps, video embeds); links http(s)/mailto/tel; no scripts, event handlers or `javascript:` URLs.

## Consequences
- Custom Builder item actions must re-render the builder like Filament's own ones (`callAfterStateUpdated()` + `partiallyRender()`): in the browser Filament sends back only the parts marked for re-rendering, while Livewire tests always render the whole page, so a missing call only shows in the browser.
- Adding a widget = one PHP class + one Vue component + a line in `Widgets::all()`.
- `symfony/html-sanitizer` is a direct requirement in `composer.json` (it was first only a Filament dependency).
- `style` attributes are allowed: editors can change the look of their HTML, not run code.
- Iframes may point at any https site; restrict to known hosts if needed.
