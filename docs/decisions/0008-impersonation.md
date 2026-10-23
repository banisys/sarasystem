# 0008 — Impersonation (log in as another user)

- **Date:** 2026-10-23
- **Status:** accepted

## Context
Support and testing need to see the system exactly as a given user sees it (their tenants, roles, menus), and then go back to the own account without logging out and in again.

## Options considered
- **A package** (`lab404/laravel-impersonate`, `stechstudio/filament-impersonate`) — ready-made, but a new dependency, and it does not know our rules: super admin as a role (ADR 0007), the web-side tenant in the session, `auth.session` and the SMS-login password-reset window (ADR 0004).
- **Our own small service** — about 100 lines, fits those rules exactly.

## Decision
Own service, [Impersonation](../../app/Auth/Impersonation.php):
- **Only super admins** may impersonate, and never another super admin or themselves, and not while already impersonating. Managers with `Update:User` may not: impersonation gives the actor every tenant the target belongs to, beyond the tenants the manager manages (compare ADR 0003).
- Started from the user edit page in the admin panel (action «ورود با این کاربر»). Staff land on the panel root (which picks one of *their* tenants), others on `/dashboard`.
- The super admin's id and the page to return to are kept in the session. A bar «بازگشت به حساب خودم» is shown on every page (panel render hook and `AppLayout.vue`); `POST /impersonation/leave` logs the super admin back in and returns to the edit page.
- On each switch, the web-side tenant choice and the `RecentCodeLogin` mark are dropped (so the actor can't change the target's password without the current one), and the session's password hash is set for the new user so `auth.session` does not log them out.
- Start and end are written to the application log.

## Consequences
- Everything done while impersonating is recorded as done by the target user; there is no audit trail per action yet, only the log lines.
- Logging out while impersonating ends the whole session (the super admin is logged out too).
- If later managers should impersonate within their own tenants, extend `Impersonation::canImpersonate()` and restrict the target's tenants; that needs a new decision.
