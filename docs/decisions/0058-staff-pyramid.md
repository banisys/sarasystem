# 0058 — A tenant's staff as a pyramid of power

- **Date:** 2026-11-05
- **Status:** accepted

## Context
After the roles' pyramid (ADR 0057), the user asked for one on the employees list. Employees have no manager or reporting line, a tenant may have hundreds of them, and a person's power differs per tenant. Campus and Employee must work without each other (ADR 0041).

## Options considered
- **An org chart with a manager per employee** — shows who reports to whom / new data to enter and keep up, and it says nothing about what people can do in the system.
- **Power from each person's permissions in the tenant** — nothing to enter, always true / it isn't the org chart.
- **A column per distinct set of permissions, names in a card** — tidy at any size / it is about role combinations, not the people. This was built first; the user asked for the focus to be on the employees.
- **A figure per employee with their name** — the person is what is seen / names crowd with very many.
- **Where it lives: in Employee** — simple / all 3D stays under one switch (ADR 0051), and Employee would need Campus.

## Decision
**Power from permissions, a figure per employee, in Campus with people from a core hook.**
- [StaffHierarchy](../../Modules/Campus/app/Services/StaffHierarchy.php) takes the current tenant's active employees through [StaffPeople](../../app/Campus/StaffPeople.php), filled by Employee's [EmployeePeople](../../Modules/Employee/app/Services/EmployeePeople.php). The button comes through [PersonListActions](../../app/Filament/Support/PersonListActions.php).
- A person's power is the permissions of their roles and their direct permissions **in this tenant**. A super admin is above all (ADR 0007). Someone without `panel_user` here can't enter the panel, so counts as having none, whatever their roles say.
- The order is the same as the roles' (ADR 0003), in the shared [PowerOrder](../../Modules/Campus/app/Services/PowerOrder.php), and the modal is shared. Levels are worked out among the sets present, and people with the same set stand together.
- [staff-scene.js](../../Modules/Campus/resources/js/staff-scene.js) draws a figure per employee, however many, the taller and darker the stronger, with a name; names are off at first above 40 people (hovering still shows them). A click opens that person's card and marks them in orange.
- It shows to those who may list employees (`ViewAny:Employee`), like the list itself. A name links to the person's page when the viewer may open it.

## Consequences
- A change of roles moves a person by itself; nothing to enter.
- Someone who may list employees also sees the level and areas of access of everyone in the tenant, which they couldn't see before. If that's too much, limit the button to managers.
- Two employees on one floor may have different powers: a floor is the length of a chain.
- It must not be read as an org chart: the page says so.
- With Campus or Employee off there is no button.
