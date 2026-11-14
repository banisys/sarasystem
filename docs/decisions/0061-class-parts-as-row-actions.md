# 0061 — A class's parts are row actions, not tabs

- **Status:** accepted
- **Date:** 2026-11-05

## Context
A class's page (`classes/{id}`) had the tabs «اطلاعات», «اعضا», «برنامه» and «جلسه‌ها». The user asked for the tabs to go, with each part an action in the classes list.

## Options
- Modal for every part: the members table, the schedule form and the session calendar are too big for a modal.
- Keep one page and open a tab from the action: still tabs.
- **A page per part, opened by a row action** (chosen), and the details as the standard view modal.

## Decision
- «اطلاعات» is the list's view action in a modal (the existing infolist).
- «اعضا», «برنامه» and «جلسه‌ها» are row actions linking to `classes/{id}/members|schedules|sessions`. Each page ([ClassroomSectionPage](../../Modules/Class/app/Filament/Resources/Classrooms/Pages/ClassroomSectionPage.php)) shows one of the existing relation managers, so their tables, actions and calendar did not change.
- The `view` page is gone; links that pointed to it (a session's breadcrumb, the 3D building, the tutorial) go to `sessions` or `members`.
- Relation managers other modules could add to a class (`ModelRelationManagers`) are no longer shown; none exists today. Row actions from `ClassroomExtensions` stay as they were.

The «برنامه» action later became «برنامهٔ درسی» and «برنامهٔ امتحانی» gained its own action and page ([ADR 0096](0096-separate-class-schedule-pages.md)).

## Consequences
One more click from the list to the class's parts, but no tabs; the action menu of a row lists everything a class offers.
