# 0064 — A workflow's 3D view

- **Date:** 2026-11-06
- **Status:** accepted

## Context
The user asked for a button on a workflow's page («نمایش فرایند», `workflows/{id}`) that shows the workflow as a 3D model, so that people really understand it. The flowchart on that page already lists every step, but it reads as a list: it doesn't show at a glance how a case passes between tenants, where it can end, or how far back it can go.

## Options considered
- **A flat flowchart tilted into 3D** — little to learn / adds nothing the page doesn't show.
- **A building, with a floor per step, like the other Campus views** — familiar / the path and its exits are hidden inside it.
- **A staircase in lanes, with a case played along it** — the height is progress, a lane is a tenant, the exits are visible paths, and the playback tells the story / a long workflow gets long.

## Decision
**A staircase in lanes**, in the Campus module so that one switch controls all 3D (ADR 0051). The core only offers a hook, `WorkflowResource::addViewPageActionsUsing()`, like the roles' and tenants' lists (ADR 0056, ADR 0057).
- [WorkflowScene](../../Modules/Campus/app/Services/WorkflowScene.php) builds on `WorkflowFlowchart::ofWorkflow()`, so the view can't disagree with the flowchart, and adds a lane per tenant, in the order they first appear, and each step's outcomes in words.
- [workflow-scene.js](../../Modules/Campus/resources/js/workflow-scene.js): each step is a platform one stair higher than the one before, from right to left since Persian reads that way. Green arrows are approvals, red chutes rejections, amber arcs returns.
- A step can return to any earlier step (`WorkflowEngine::returnTo()`). Drawing all those arcs at once hides everything, so at rest each step shows the arc to the step before, and clicking it shows all of its arcs.
- Clicking a step, lane, start or end opens a card and fades what isn't related.
- «پخش مسیر» moves a case along the approvals, stopping at each step with a caption. It only plays on the screen and changes no data. Frames are drawn only while it moves (ADR 0049); with reduced motion the case jumps from stop to stop.
- It shows nothing new: anyone who can open the page already sees the same steps, assignees and open-case counts in the flowchart, so it has no permission of its own. The data is built only when the modal opens, and three.js loads then too.

- People look like people ([person-figure.js](../../resources/js/scene/person-figure.js)): the user found stacked boxes unsuitable and asked for one figure everywhere, so the session's room and both pyramids use it too. It is in core beside `stage.js`, since a session's view is in the Class module and modules don't depend on each other (ADR 0041). Colour carries meaning in those views (status, power), so only the clothes take a colour; skin and hair stay. Learners in a session sit at their desks; the teacher stands. In the staff pyramid a stronger person is scaled as a whole, so also wider.

## Consequences
- The view follows the workflow's definition by itself; the designer needs no 3D settings.
- It shows the definition and the current open cases, not one case's history; that stays in the case's flowchart (ADR 0043).
- Beyond about 12 steps the path gets long and small, so names start off (hovering still shows them).
- Stairs show order, not importance or duration.
