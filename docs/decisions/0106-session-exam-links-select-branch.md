# 0106 — Open session exam links in their branch

- **Date:** 2026-11-16
- **Status:** accepted

## Context
The admin panel puts the branch in the URL, while the user panel stores its branch in the browser session. A link to «آزمون‌های جلسه» could therefore return 404 when the user panel had a different branch selected, even for someone authorized to manage the session.

## Options considered
- Ask the user to switch branches first. Direct links and bookmarks still fail if opened from another branch.
- Resolve the branch for the direct exam list visit and select it only after validating access.

## Decision
The session exam list first tries the current branch. If necessary, it asks the context source to locate the session among active branches the user may enter. The existing context authorization runs in that branch with its own roles. Only a successful check selects the branch in the user panel session.

## Consequences
Direct links work for users authorized in the session's branch, including when no branch was selected. Failed lookups return 404 and do not switch a valid selected branch. Exam actions and other user-panel routes still use the active branch.
