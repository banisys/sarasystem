# 0012 — Site menus

- **Date:** 2026-10-23
- **Status:** accepted

## Context
The user asked to port the LMS menu section (`/cpadmin/core/menu`, `Modules/Core` `MenuResource` + `MenuTree`) to fit this project. In LMS a menu is a root row of one `menus` table (`root_id`, `parent_id`, `instance_id`, `language_id`, `type` url / external_url / route, `attributes` JSON with icon / image / description, `active` 0/1/2, soft deletes). It is managed as a list of root menus plus a drag-and-drop tree page, and shown on the site by a `MenuBlock`.

## Options considered
- **Where:** the core vs the **`Site` module**. Menus only serve the tenant's public site, so `Site` (ADR 0005).
- **Storage:** one table with root rows (LMS) vs **`site_menus` + `site_menu_items`**. The two-table version needs no `root_id` bookkeeping and keeps a menu's title apart from its items. A jsonb tree on the menu (like page content, ADR 0011) was rejected: moving items between levels by drag-and-drop is simpler with rows.
- **How a menu reaches the site:** a menu widget placed on pages (like LMS's `MenuBlock`) vs **one menu per tenant, chosen in the tenants list and shown in the site header**. The user chose the second, with no menu widget: menus are made in «وب‌سایت → منوها» (usually in the head office) and given to tenants from `/cpadmin/{tenant}/tenants`.
- **Core hooks:** the tenants list and `PublicLayout` are core, which must not depend on a module (ADR 0005). So the core got two generic, named hooks that the module fills: `TenantResource::addRecordActionsUsing()` and `PublicSite::navigationUsing()`.
- **Link types:** LMS's url / external_url / route vs **page / url / none**. A link to a page by id follows the page when its path changes. Laravel route names mean nothing to site editors, and an address typed as-is covers app routes (`/login`). "None" gives a title over a submenu, which LMS could not do.

## Decision
- Tables `site_menus` (`tenant_id`, `title`), `site_tenant_menus` (`tenant_id` unique, `menu_id`: the menu of each tenant's site header) and `site_menu_items` (`tenant_id`, `menu_id`, `parent_id`, `title`, `link_type`, `page_id`, `url`, `open_in_new_tab`, `is_visible`, `sort`). `Menu` and `MenuItem` use `BelongsToTenant`. `TenantMenu` does not: it is set by super admins across tenants, and the menu may belong to another tenant. Deleting a menu or an item deletes its items/submenu (foreign key cascade, no soft deletes). Deleting a page leaves its items with no page; they are not shown.
- **Admin** «وب‌سایت → منوها» ([MenuResource](../../Modules/Site/app/Filament/Resources/SiteMenus/MenuResource.php), Shield permissions `*:Menu`): a list (create / rename in a modal, then on to the items) and an items page `menus/{id}/items` ([MenuItems](../../Modules/Site/app/Filament/Resources/SiteMenus/Pages/MenuItems.php)): a tree with add / add-submenu / edit / delete in modals and drag-and-drop between levels (Filament's `x-sortable`). Changing items needs `Update:Menu`. Every item id coming from the browser is looked up within the menu (and tenant). An item that is not found gives 404, because without a record of its own a Filament action falls back to the page's record (the menu).
- **Addresses** must start with `https://`, `http://`, `/`, `#`, `mailto:` or `tel:` (`MenuItem::URL_PATTERN`). This is checked on save and again when shown.
- **Choosing:** row action «منوی سایت» in the tenants list ([ChooseTenantMenuAction](../../Modules/Site/app/Filament/Actions/ChooseTenantMenuAction.php), super admins only like the whole list). It lists every tenant's menus as "title (tenant)", or «بدون منو».
- **Site:** `SiteServiceProvider` registers a navigation provider. `PublicSite::layoutProps()` puts its links in `site.navigation`, and `PublicLayout` shows them under the header with [NavMenu.vue](../../resources/js/Components/Public/NavMenu.vue): top level in a row, submenus on hover/focus. This applies on every public page, the default home page included. Shown items are the visible ones that lead somewhere (published page, allowed address) or have no link; a hidden item hides its submenu. `Menu::itemTree()` reads items and pages without the tenant scope, because a branch's site may show a head-office menu whose page links point to head-office pages.
- **Core hooks are keyed by name:** registering again replaces, since the app (and the module's boot) runs once per test while the static lists stay.
- **Not ported:** languages (no languages here), `alias`, `key_trans`, icon / image / description (used only by LMS's banner-slider menu views), the three-state `active`, route-name links, LMS menu view variants, a menu block/widget.

## Consequences
- One menu per tenant, in the header only. Footer menus or menus inside pages would need a second slot or a widget.
- Whoever can edit a head-office menu changes the header of every tenant that uses it.
- Adding icons or images to items later means new columns (or a jsonb `attributes`) plus widget props.
- Drag-and-drop calls `moveItem()` once per drop. That updates the moved item's parent and the order of its new list.
