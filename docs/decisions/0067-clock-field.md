# 0067 — Clock field in the admin panel

- **Date:** 2026-11-08
- **Status:** accepted

## Context
Times are entered in several admin forms: a class's schedule, a session's move and its make-up / extra sessions, and the sample-data form. Since 2026-11-07 every `TimePicker` used Filament's non-native panel, so that clocks are 24-hour whatever the operating system. That panel is slow to use. The field is read-only, so you can't type in it. A click opens a small box with two number inputs that you change one step at a time or type into. There is no quick choice of common times.

## Options considered
- **Browser's native time input:** fast to type, but it shows AM / PM when the operating system does. This is why we dropped it on 2026-11-07.
- **Plain text input with a mask (`99:99`):** typed, 24-hour. But it can't pick a time, and a mask fights a one-digit hour («9:30»).
- **Our own field replacing `TimePicker` through the container:** typed and picked, 24-hour. Forms keep writing `TimePicker::make()`. It overrides Filament's rendering, so an upgrade can need changes to it.

## Decision
Our own field, [ClockPicker](../../app/Filament/Forms/ClockPicker.php). It extends `TimePicker` and is bound in its place in `AdminPanelProvider`, so every `TimePicker::make()` gives one. Its Alpine side is in [clock-picker-script.blade.php](../../resources/views/filament/admin/clock-picker-script.blade.php) and its styles in `resources/css/filament/admin/clock-picker.css`.
- **Typing:** an LTR text field. You type «930», «9:30», «9.30», «17» or Persian digits, and the colon is added as you type. A complete time is saved as soon as it is typed. On leaving the field, something that isn't a time goes back to the saved one.
- **Panel:** opens on focus, below the field or above it when there's no room below. It has 24 hours and the minutes in steps of `minutesStep` (default 5), and marks the current hour. Picking an hour keeps the minute; picking a minute closes the panel. It also has «اکنون» and «پاک کردن».
- **Keys:** ↑ / ↓ move by the minute step, snapping to it. Shift (or Page Up / Down) moves by an hour. Enter saves and Escape closes.
- **State:** «HH:MM», as a native input would give it. A field has no seconds by default; one that asks for seconds (`->seconds()`) gets Filament's own panel.
- `DateTimePicker` (date and time, e.g. an exam's window) keeps Filament's non-native panel.

## Consequences
- Times are quicker to enter. Any minute can still be typed; the panel offers only the steps.
- `toEmbeddedHtml()` uses Filament's internal `wrapEmbeddedHtml()` / `wrapInputHtml()`, so check the field after a Filament upgrade.
- `minDate` / `maxDate` aren't shown in the panel; the server still checks them, and so does `->after()`.
- The panel is positioned inside the form. In a container that clips its content it can be cut off; none of the current forms does that.
