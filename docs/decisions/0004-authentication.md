# 0004 — Authentication: one login for everyone, mobile / national code / email, password or SMS code

- **Date:** 2026-10-23
- **Status:** accepted

## Context
In legacy LMS, most users have no email. About 28k log in with a national code and about 33k with a mobile number (SMS), while only about 6k have an email. Users are global and hold roles per tenant ([ADR 0002](0002-tenancy-model.md)). The same person may be staff (admin panel) and a parent (user panel). Filament's own login only accepts email.

## Options considered
- **Laravel Fortify:** headless and well tested, but it is built around one username field and would need overriding for multi-identifier and OTP login anyway.
- **Custom controllers** (chosen, at the user's request): small and fully under our control.
- **Identifier:** email only (doesn't fit the users), mobile only (parents' mobiles are shared between siblings), or any of mobile / national code / email (chosen).

## Decision
- **One login page** (`/login`, Inertia) for everyone. The Filament panel has no login page of its own: unauthenticated panel requests are redirected to `/login` and come back to the intended URL.
- **Identifier:** a single field that accepts a **mobile, national code or email**. Persian/Arabic digits are converted, and mobiles are normalized to `09xxxxxxxxx`.
  - `users.email` becomes nullable. `users.national_code` is unique. `users.mobile` is **not unique**, because siblings can share a parent's mobile.
  - An identifier must resolve to exactly one user. If a mobile belongs to several accounts, the user is asked to log in with their national code.
- **Two ways to prove identity:** a password, or a **6-digit one-time code sent by SMS** to the user's mobile. The code is stored hashed in the cache, expires after 2 minutes, allows 5 wrong tries, and a new one can be requested after 60 seconds.
- **SMS** goes through an `SmsSender` interface. The current driver is `log` (it writes to the Laravel log), and a real provider (Kavenegar, Ghasedak, …) is a driver added later.
- **Rate limiting:** 5 failed attempts per identifier and IP, then lockout.
- **After login:** tenant resolution follows ADR 0002. With several tenants and none chosen, the user goes to the tenant picker.

## Consequences
- Every user needs at least one of mobile, national code or email. A user without a mobile cannot use SMS login.
- Sending a code to an unknown identifier looks like a successful send (no account enumeration). Messages do reveal an account in two cases: a shared mobile ("use your national code") and an account without a mobile. This is accepted for usability.
- Password reset is "log in with an SMS code, then set a new password on `/profile` within 15 minutes, without the old one". There is no email reset flow for now. A user without a mobile who forgets their password needs staff to reset it.
- Choosing a real SMS provider, and its credentials, remains open.
