# System map

The admin menu's **«نقشهٔ سامانه»** (`/cpadmin/{tenant}/system-map`) gives a Persian, RTL overview of the installation: public/user/admin surfaces, the shared core, module groups, administrative screen links and business connections. It is super-admin-only and implements `IndependentOfTenant`. It contains no tenant records. Screen links and their availability still follow the selected tenant and each screen's authorization.

## Regenerating

Open the page or press **«بازسازی نقشه»**. This performs a full page load and builds the map again from the running application; there is no stored image, generated file, database table or separate generator command to go stale.

- Installed modules, descriptions, enabled status and required modules come from the existing [ModuleManager](../app/Features/ModuleManager.php) and `Modules/*/module.json`.
- Administrative destinations come from the current Filament panel's registered resources and pages, using their labels, authorization and generated URLs. Only navigation-enabled screens with an index destination are listed. Record-specific tabs/actions are not separate sitemap destinations.
- Disabled modules retain their overview cards but have no screen links or active business connections. Core remains visible even when every module is disabled.
- New modules appear automatically under «سایر امکانات» with their technical name if they have no map metadata.

Normal deployment cache invalidation still applies. If the application uses cached configuration/routes/components, clear/rebuild those as usual before reloading the page. After CSS changes, run `docker compose exec -u sail laravel.test npm run build`.

## Keeping the meaning current

Automatic discovery can find pages and declared dependencies, but cannot infer business meaning from code. Each module owns an optional `sitemap` object in its `module.json`; [Student](../Modules/Student/module.json) is a complete example.

| Field | Meaning |
|---|---|
| `label` | Short Persian display name. Falls back to the module name. |
| `group` | `education`, `operations`, `experience`, or `other`; unknown groups fall back to `other`. |
| `surfaces` | Array containing `public`, `user`, and/or `admin`; describes which interfaces expose the module. This is curated, not inferred from route paths. |
| `connections` | Array of `{ "target": "ModuleName", "label": "Persian relationship" }`. `core` is reserved for the shared core. Unknown, absent or disabled endpoints are omitted. Direction is from the owning module to the target. |

Use `requires` at the root of the manifest **only for real technical dependencies** (ADR 0041). A business connection does not register code, enable another module or create a dependency. Do not add future/unimplemented features as current connections.

Display labels and connection labels support `:learner` and `:learners`, resolved from the installation's configurable learner terminology. Descriptions are the existing module manager descriptions.

When developing a feature:
1. Keep its manifest description and `sitemap` labels/surfaces/connections accurate. Add a connection at its source only, avoiding duplicate inverse descriptions.
2. Register its resources/pages through the normal panel discovery. No second screen list needs maintaining.
3. Reload the map as a super admin. Open the module card and verify links in both the primary and another tenant. A primary-only resource should not be linked in another tenant.
4. Verify the map with the module disabled; the card remains, links and connections disappear. Respect declared dependencies when disabling a base module.
5. Run `docker compose exec -u sail laravel.test php artisan test --compact tests/Feature/Admin/SystemMapTest.php`. If tutorial steps change, also run `Modules/Tutorial/tests/Feature/SystemAdministrationScenarioTest.php`.

The map builder is [app/Features/SystemMap.php](../app/Features/SystemMap.php); the [page](../app/Filament/Pages/SystemMap.php), [Blade view](../resources/views/filament/pages/system-map.blade.php) and [scoped CSS](../resources/css/filament/admin/system-map.css) own presentation. Core summaries and the three interface headings are intentionally curated in the view and builder. New core capabilities may require updating them. The tutorial's first system-administration mission introduces the map.

Design decision: [ADR 0045](decisions/0045-live-system-map.md).
