# 0071 — A teaching plan as a picture

- **Date:** 2026-11-08
- **Status:** accepted

## Context
The user asked for an action on «کلاس‌ها → طرح‌های درس» that shows a whole teaching plan in a modal, visually and as a schema. The list only gives counts (topics, sessions, schedules), and the edit form shows the topics as a repeater, one by one. It doesn't say which sessions each topic takes, or how far the classes following the plan have got.

## Options considered
- **A diagram drawn with a library** (Mermaid, a canvas): it would need a new package, and a plan is a straight line of topics, which needs no layout engine.
- **A 3D view** like the workflows' (ADR 0064): heavy for a list of topics, and not what was asked.
- **Plain HTML in the modal (chosen):** Tailwind blocks in a Blade view, light and dark, readable at phone width and printable, with no script.

## Decision
- Row action «نمای طرح» (first in the row's menu) opens a wide modal with only a close button. Its view is `class::filament.teaching-plan-overview`, and its data comes from [TeachingPlanOverview](../../Modules/Class/app/Services/TeachingPlanOverview.php).
- **Facts:** subject, number of topics and sessions, classes following it, active or not, description.
- **«نقشه‌ی جلسه‌ها»:** a cell per session numbered from 1, grouped and coloured by topic, in order.
- **«سرفصل‌ها به ترتیب»:** a numbered path of the topics in the same colours, each with its session count, the sessions it takes («جلسه‌های 1 تا 2») and its description.
- **«کلاس‌هایی که از این طرح پیروی می‌کنند»:** every class of every tenant with a schedule following the plan (plans are the organization's, ADR 0025). Each shows its tenant, teachers and progress («پیشرفت: X از Y سرفصل», as on a class's «برنامه»), and a bar with a segment per topic, sized by its sessions and coloured when covered. It also shows the schedules' sessions that aren't cancelled and how many were held, with a warning when they are fewer than the plan needs.
- Anyone who can see the list can open it; it changes nothing.

## Consequences
- Covered topics come from `PlanForecast::coveredTopicIds()`, so the picture agrees with the class pages.
- It shows the plan's own order, not the dates of each class's sessions; the forecast per session stays in the session lists.
- Colours repeat after eight topics. It runs a few queries per class following the plan, which is fine for tens of classes.
