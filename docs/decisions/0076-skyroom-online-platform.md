# 0076 — Skyroom as an online-class platform

- **Date:** 2026-11-09
- **Status:** accepted

## Context
The user wants Skyroom alongside Adobe Connect, with the Skyroom integration in `/home/sepehr/projects/tizhooshan-sara` as a read-only reference. That integration creates rooms and uses `createLoginUrl` to enter with a nickname, identity and access level. Class already has platform drivers, tenant-scoped rooms effective from a day and a shared entry authorization flow (ADR 0073).

## Options considered
- Create permanent Skyroom accounts and assign room membership — extra credentials and synchronization, unnecessary for direct entry.
- **Use `createLoginUrl`** — supplies identity and role on entry without separate accounts, as supported by the [official API](https://data.skyroom.online/help/webservice.html).
- Store an API key in settings / add a column / **reuse the encrypted password column** — each server has one platform, so the existing secret field can hold that platform's credential without a migration.
- A room per session / **one reusable room per class platform period** — follows Skyroom's recommendation to reuse rooms and keeps first-entry calls low. Adobe's optional per-session mode remains specific to Adobe.

## Decision
Add `OnlinePlatform::Skyroom`, [SkyroomApi](../../Modules/Class/app/Services/Online/SkyroomApi.php) and [SkyroomDriver](../../Modules/Class/app/Services/Online/SkyroomDriver.php) inside Class, without a package or another module dependency.

In «سرورهای کلاس آنلاین», choose Skyroom, enter the API endpoint **without the key** (normally `https://www.skyroom.online/skyroom/api`) and the API key in the masked secret field. It uses the existing encrypted/hidden `password` attribute; empty on edit keeps it. Adobe's administrator and settings are hidden. Skyroom settings are fallback room capacity (50), simultaneous devices per host (3), and optional `service_id` (ADR 0077). «آزمایش اتصال» calls `getServices` and validates the active service selection; it does not create anything. A server's platform cannot be changed once any tenant's class uses it; create another server instead.

The existing class platform choice, date-effective moves and entry route apply unchanged. On first authorized entry, lock the tenant-owned room, create a Skyroom room, and store its external ID. Its Latin name includes an installation URL hash and the local room ID; its title names the class. Guests are disabled and the operator must enter first. Capacity uses the class capacity, or the server fallback; the purchased Skyroom service must support it. Existing remote rooms are not resized or renamed by later local edits.

An entry creates or reuses a cached URL (240 seconds, ADR 0077) with a five-minute Skyroom TTL, identity `sara{user id}`, Persian nickname/language, access 3 (operator) for hosts or 1 for participants. Hosts use the configured device count; participants use one. Roles come from `OnlineEntry`, including session substitutes and class editors, rather than whether someone has a teacher record elsewhere. No permanent Skyroom accounts are stored. A link expires for new entry; TTL is not a limit on how long the person stays connected.

The JSON client has explicit timeouts and no automatic mutation retry or HTTP redirects. Network, HTTP, malformed response, API refusal, invalid ID and invalid entry URL become Persian refusal messages. Logs carry server ID, action and a numeric API error code only; remote error text and transport exceptions can expose the API key in the URL, so neither is reported.

## Consequences
- Several servers, historical platform moves, tenant isolation and existing time/role checks work for Skyroom through the same Class flow.
- The language-institute tutorial's class mission has an optional setup/entry tip; automated missions remain in person and need no live Skyroom credentials.
- Tested against HTTP fakes, not a paid/live Skyroom service. Connection checks confirm API access and active service selection, not room capacity or room-creation permission. No recordings or attendance synchronization.
- An entry with no reusable cached URL costs one API call, plus room creation on first entry. Skyroom documents a rate limit of 500 requests/minute; ADR 0077 adds brief caching, while pre-generation may still be needed for bursts of many distinct people.
- A previously issued link remains usable until its TTL expires, even if local access is revoked. Revoking local access does not disconnect someone already inside Skyroom.
- Remote rooms are retained when a local room/class is deleted. If remote creation succeeds but its response or local commit is lost, the deterministic name may already exist; reconciliation is currently manual. Deleted remote rooms are not recreated automatically.
