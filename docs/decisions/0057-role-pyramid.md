# 0057 — The roles' pyramid of power

- **Date:** 2026-11-05
- **Status:** accepted

## Context
The user asked for a 3D model of the roles on the roles list, in a modal, showing the hierarchy and how many permissions each has, so that a role's power and place are seen at a glance. Roles have no parent or level: their order exists only through the permission-subset rule (ADR 0003).

## Options considered
- **A level or parent entered per role** — any order can be drawn / one more thing to keep up, and it can disagree with what the roles can do. ADR 0003 rejected levels for this reason.
- **Order by number of permissions** — simple / a role with more permissions in another area would look above one it has no power over.
- **Levels worked out from the subset rule** — always true, nothing to enter, the same rule that decides who may assign what / unrelated roles can share a floor.

## Decision
**Worked out from the subset rule**, in [RoleHierarchy](../../Modules/Campus/app/Services/RoleHierarchy.php), in the Campus module so that one switch controls all 3D (ADR 0051).
- A role is above another when it has all its permissions and more. Its level is its longest chain below. Equal sets are peers. The super admin holds no permission rows but passes every check (ADR 0007), so it counts as having all of them and is above everything, even a role granted all.
- [role-scene.js](../../Modules/Campus/resources/js/role-scene.js): a floor per level, each wider than the one above; column height and colour by the square root of the share of permissions, since a linear scale makes most roles look alike beside the super admin; up to 10 little people for holders, each person counted once across tenants.
- Beams join a role to its nearest above only. Roles with no permissions get none, because everything is above them; the card still lists them.
- Clicking a role marks its chains up and down and fades unrelated ones.
- The modal is full screen (`Width::Screen`); the admin theme lifts the content area above the top bar while such a modal is open, otherwise the bar hides its heading and close button.
- The roles list is for super admins only (ADR 0003), so counts across tenants leak nothing. The data is built only when the modal opens, and three.js loads then too.

## Consequences
- A change of permissions moves the role by itself.
- Height is not a linear measure; the card gives the exact numbers.
- A floor means the length of a chain, not equal power: the page says two roles on one floor need not be peers.
