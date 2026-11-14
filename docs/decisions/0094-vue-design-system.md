# 0094 — Shared design system for Vue pages

- **Date:** 2026-11-13
- **Status:** accepted

## Context

The public site and user panel share Vue, Inertia and Tailwind. They need consistent Persian typography and controls, with room for tenant branding on the public site and denser screens for daily work.

## Options considered

- Keep styling each page with its own Tailwind classes. This has a low initial cost and repeats decisions about focus, spacing, contrast and form states.
- Use a complete themed component kit. It would supply more screens quickly and would require adapting its form, calendar and navigation conventions.
- Define shared Tailwind tokens and local Vue controls. Use headless primitives for complex interactions, following the shadcn-vue approach to local component ownership.

## Decision

- [app.css](../../resources/css/app.css) defines the shared canvas, surface, text and field styles. The public site and user panel use Shabnam Light from the same local font files as the admin panel.
- Use the existing [public layout](../../resources/js/Layouts/PublicLayout.vue) for roomier pages and tenant colour, and the [user layout](../../resources/js/Layouts/AppLayout.vue) for the compact product interface. Choose the more contrasting of white and black for text on the tenant colour. Tint or shade the hover background away from the selected foreground.
- Shared Vue controls live under [Components/Ui](../../resources/js/Components/Ui). Use Inertia links for navigation, Reka UI for the modal mobile menu and native form controls for ordinary input. Keep the existing Jalali and mobile verification components.
- Apply the design to the public layouts and content surfaces, the preregistration form and the My Classes calendar first. The Filament theme has its own CSS entry.
- The My Classes calendar, session detail, session exams and question library headers share the dark plum background and light text.

## Consequences

The user panel uses [UiPageHeader](../../resources/js/Components/Ui/UiPageHeader.vue) for page titles, context and related actions. Lists use keyboard-focusable cards and explain empty states. The class session groups teaching tasks, and the timed exam keeps progress and save status visible below the mobile header. Account and contract code forms move focus to the code field after sending. Grade entry and report cards use responsive rows and tables.
