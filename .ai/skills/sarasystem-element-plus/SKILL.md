---
name: sarasystem-element-plus
description: Build or review Element Plus interfaces in SaraSystem's Inertia Vue user panel. Use for /profile layout, controls, and Tailwind styling. Use the installed Element Plus version and official documentation to verify component APIs.
---

# SaraSystem Element Plus

- Check the resolved Element Plus version in [package-lock.json](../../../package-lock.json). Use the [official component docs](https://element-plus.org/en-US/component/overview) and [release notes](https://github.com/element-plus/element-plus/releases). For version-sensitive props, events, slots, and styles, confirm against the installed package or the matching release tag.
- Start from [AppLayout.vue](../../../resources/js/Layouts/AppLayout.vue), [shared panel components](../../../resources/js/Components/Ui), and [profile.css](../../../resources/css/profile.css). Reuse the panel components where they fit and import only the Element Plus components and styles the page needs. Keep Inertia navigation, forms, and server validation.
- Use Element Plus Container components for the shared panel layout and Tailwind for page styling. Keep Persian RTL, responsive layouts, and both themes. Use logical spacing and the panel's CSS variables. Append teleported dropdowns to `#profile-overlays` so they inherit the panel theme.
- Check layout changes at desktop and mobile widths, including the sticky header and the navigation drawer.
