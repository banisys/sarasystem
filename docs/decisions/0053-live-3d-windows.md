# 0053 — The 3D building and map update themselves

- **Date:** 2026-11-04
- **Status:** accepted

## Context
The windows of the building and the map (ADR 0051, ADR 0052) showed the state when the page opened, so a page left open went out of date. The state changes slowly: a session starts or ends, attendance doesn't affect it. The site must stay light.

## Options considered
- **WebSockets (Reverb)** — instant / a new server process and package, for something that changes a few times an hour.
- **`wire:poll`** — one attribute / renders the whole page every time, and can't stop while the tab is hidden without more code.
- **A timer calling a renderless Livewire method** — little data, no render, the panel's existing authentication and tenant / a delay of up to the interval.

## Decision
**The renderless call.**
- `liveData()` on `Building` and `OrganizationMap` (`#[Renderless]`) returns what the first render uses. Filament's access check runs on each call, so a right that was taken away ends the updates.
- `keepLive()` in `blocks.js` runs the timer: every `CAMPUS_LIVE_SECONDS` (default 60; 0 turns it off), one request at a time, none while the tab is hidden, one at once when it shows again.
- `scene.update()` recolours the windows in place, with one frame drawn, so the camera doesn't move and nothing is drawn between changes.
- When the classes or tenants themselves changed, `update()` says so and the scene is built again, keeping the options; the camera returns to its first view. A building whose rooms all ended reloads the page, for its empty message.
- The open card, legend and totals follow the new data; a card whose room is gone closes.
- A failed fetch keeps the last state and says «به‌روزرسانی نشد» with its time, and clears at the next success.

## Consequences
- Each open page makes one small request a minute; the map's runs the room queries once per tenant, so the interval is the setting to raise for a large installation.
- A session started just now can take up to the interval to turn yellow.
- A session's 3D view isn't covered: attendance is only taken with the person at the form, and redrawing figures needs more than a recolour.
