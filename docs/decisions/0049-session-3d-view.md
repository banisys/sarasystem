# 0049 — 3D view of a session

- **Date:** 2026-11-04
- **Status:** accepted (prototype: phase 1 of a possible series)

## Context
The user wants parts of the system shown in 3D to make it more attractive: a map of tenants as buildings, a class as a room, an empty seat for an absent learner. It must not slow the site down. The data for one session's attendance already exists; floors, rooms, seats and map positions don't. So the first step is one session in 3D, to measure the real cost before deciding on the rest.

## Options considered
- **A — three.js with shapes built in code** — real 3D with a moveable camera, no model files / a new dependency of about 140 kB gzip.
- **B — CSS 3D or isometric SVG** — no dependency / a moveable camera and depth sorting get hard quickly.
- **C — Babylon.js, Unity or Spline** — more features than a few simple rooms use, and much heavier.

Where it lives:
- **In the Class module for now** — one use, no hook to design yet.
- **A new `Campus` module with a core hook** — sold separately and shared with later views / a hook with one user, built before the prototype has shown its worth.

## Decision
**A, in the Class module.** With a second use, the shared part moved to the core and the switch to the Campus module ([ADR 0051](0051-campus-building.md)); this view's room and data stay in Class.

- «نمای کلاس» on the session's page ([ADR 0050](0050-session-page-in-new-tab.md)) has a button «نمای سه‌بعدی» that opens the view in a full-page modal; [session-scene.js](../../Modules/Class/resources/js/session-scene.js) is its own Vite entry, imported only when the button is pressed. `preserveEntrySignatures: 'exports-only'` in `vite.config.js` keeps the entry's export; without it the build drops the whole file.
- **Full control:** dragging turns the camera all the way round, down to just above the floor; the wheel or two fingers zoom toward the pointer; a right-drag, two fingers or the arrow keys pan, held around the room so it can't be lost. Buttons: ready-made views («سه‌رخ», «از بالا», «از جلو», «از عقب»), closer / farther, «چرخش خودکار», «نام همه» (a name over every seat), «دیوارها». A wall between the camera and the room hides itself, so every angle sees in.
- The modal is teleported to `<body>`, because the dark theme's backdrop filter would otherwise cut off a fixed element. Like the panel's other modals it closes with its × button only. It holds the keyboard focus and stops the page scrolling behind it.
- [SessionScene](../../Modules/Class/app/Services/SessionScene.php) gives one seat per person on the session's attendance list, with their status: present and late are coloured figures, absent an empty chair with a red marker, excused a grey empty chair, unrecorded a pale figure. An online class gets a dark room with a screen and no windows; a blended class looks in person.
- **Seats are symbolic:** in the list's order, since seats aren't recorded. The view says so.
- **Who sees it:** those who may view the class or take its attendance (`canSee`), not everyone who may list its sessions, because it names people with their attendance. Not in the user panel, so learners and guardians don't see it. A cancelled session has none.
- **Names are HTML over the canvas**, so the browser handles Persian shaping, right-to-left text and the panel's font.
- **Cost:** frames are drawn only while something moves (a drag and its glide, a camera move, turning by itself); repeated furniture and figures are `InstancedMesh`; no real-time shadows; pixel ratio at most 1.5. Closing the view or leaving the page disposes the scene and gives the WebGL context back (Alpine's `destroy()`).
- **Without WebGL** (or if the script can't load) the same people show as a list, which is always there for screen readers.
- Attendance is still taken in the existing form; the view only shows it.

## Measured (2026-11-04, headless Chrome, software rendering)
| What | Result |
|---|---|
| Script, production build | 567 kB, 140 kB gzip; `app.js` unchanged |
| Requests before the button is pressed | none for the script, also with the modal open |
| 10 / 35 / 300 seats | 17 / 18 / 27 draw calls, 2.9k / 7.7k / 63k triangles |
| Draw calls in 2 s without moving | 0, also after auto-rotate and its glide end |
| Canvases after closing the view | 0 |

No frame time on real graphics hardware was measured; the test machine rendered in software.

## Consequences
- Other screens load nothing new; the admin panel's own files don't change apart from a few theme classes.
- No model files, so the look is simple and stays that way unless we accept heavier assets.
- A member joining or leaving moves the seats after them, since seats follow the list.
- Every future view needs the same care: its own entry, a fallback, a check of who may see it.
- On touch screens, dragging on the view turns the camera and doesn't scroll the page (`touch-action: none` from OrbitControls).
