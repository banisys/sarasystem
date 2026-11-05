# 0062 — Each tenant's public site has its own primary colour

- **Status:** accepted
- **Date:** 2026-11-05

## Context
An organization's tenants can address different audiences, e.g. a girls' institute in pink and a boys' one in blue. The user asked for a colour chosen on the tenant form and used on that tenant's public site. Until now the public pages used fixed violet Tailwind classes, in components they share with the user panel (`JalaliDateInput`, `VerifiedMobileInput`, `CustomFieldInput`).

## Options
- **Override `--color-violet-*` inside the public layout:** no class changes, but `violet` would stop meaning violet and the tutorial bar would change colour too.
- **A stylesheet generated per tenant, or shades chosen and stored by hand:** more moving parts, and more for the person choosing a colour to do.
- **Named `brand-*` colours, worked out in CSS from one stored colour** (chosen).

## Decision
- `tenants.primary_color`: nullable `#rrggbb`, set in the tenant form with a colour picker, shown as a swatch in the list. Empty means the default colour.
- `PublicSite::layoutProps()` passes `site.tenant.color` (`primary`, `contrast`). [PublicLayout.vue](../../resources/js/Layouts/PublicLayout.vue) sets it as `--brand` and `--brand-contrast` on its root with the class `site-brand`.
- [app.css](../../resources/css/app.css) defines `brand-50` to `brand-950` and `brand-contrast`. They are violet by default, so pages outside the public layout look as before. Under `.site-brand` they are mixed from `--brand` with `color-mix`; `brand-600` is the chosen colour itself.
- Text on the colour is white while its contrast is at least 3:1, otherwise near-black (`Tenant::primaryColorContrast()`). The lower button threshold keeps white text on common colours like pink.
- A strip in the colour runs along the top of the header and footer, so a page without buttons still shows it.

## Consequences
- Anything rendered inside `PublicLayout` follows the tenant by using `brand-*` instead of `violet-*`. A new shared or public component must do the same.
- The admin panel, the user panel and the login page keep their colours: they are not a tenant's site.
- HTML written into a Site widget keeps its own colours.
- A very light colour gives weak links on white, because only the text on the colour adapts. The form does not warn about this.
