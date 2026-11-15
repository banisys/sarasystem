# 0101 — User-panel dark theme

- **Date:** 2026-11-15
- **Status:** accepted

## Context
People using the user panel want to choose a dark appearance across branches and pages.

## Options considered
- Follow the operating system alone. No preference to store, but users cannot choose a different appearance for the panel.
- Store a choice on the account. It follows users across devices, which also applies the same choice to devices they use under different lighting.
- Follow the device by default and remember an explicit choice in the current browser. This keeps appearance independent of the signed-in account or branch.

## Decision
Use the third option. [AppLayout](../../resources/js/Layouts/AppLayout.vue) provides a «تم تیره» button in the desktop sidebar and mobile header. The browser stores the choice; it is not saved to the server. The panel scopes its [palette](../../resources/css/profile.css) to the layout and mounts Element Plus dropdowns inside it so they inherit the active palette.

## Consequences
The public site, login, Filament admin and printable documents keep their own appearance. New user-panel components should use the shared colour tokens for surfaces and text, and the scoped Tailwind variant where a different dark accent is needed. The theme choice does not gate tutorial missions.

Filled primary buttons use white labels on the teal accent in both modes.
