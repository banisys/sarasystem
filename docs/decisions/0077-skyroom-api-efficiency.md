# 0077 — Reuse Skyroom login URLs and select the purchased service

- **Date:** 2026-11-09
- **Status:** accepted (updates ADR 0076)

## Context
The user requested a review against the [official Skyroom documentation](https://data.skyroom.online/help/webservice.html). Skyroom recommends retaining login URLs to avoid unnecessary calls and documents 500 requests/minute. Its room creation also requires `service_id` when an account has multiple active services. ADR 0076 generated a URL on every entry and could not select a service.

## Options considered
- Generate every URL anew / **reuse briefly through Laravel's existing cache** / pre-generate URLs for all participants with a job. The cache avoids repeat entry calls without another schema, scheduler or credentials workflow. Pre-generation could reduce bursts of first entries but needs separate planning.
- Cache solely per user and room / **key by the complete login context**. A key must separate privileges and reflect updated credentials/settings.
- Add a service-discovery dropdown / **optional service ID with validation during the connection check**. The latter works without API requests while typing in the form.

## Decision
Keep Skyroom's five-minute login TTL. Cache a successfully validated URL for 240 seconds, with a 60-second margin for transport time and navigation. A nonblocking 30-second atomic cache lock prevents concurrent generation of the same URL; a competing request receives a Persian retry message. Errors and invalid URLs are never cached. Use the configured Laravel cache (currently database; array in tests), without new infrastructure.

The SHA-256 cache key includes tenant, local room, session, server, endpoint, API key and every login parameter (identity, nickname, role, device count, language and TTL). No secret is placed verbatim in the key. `OnlineEntry` still checks authorization, time and cancellation before the driver, and the driver still checks server activation before consulting the cache. The nickname is limited to 128 characters, matching Skyroom's user field limit.

Add optional `settings.service_id` to the Skyroom server form, positive integer when provided. Pass it on room creation only when set. The connection check verifies a list of active services: no active service is a failure; a selected service must exist and be active; several active services require a selection. A single active service needs no ID. Editing this setting affects new rooms only.

Translate documented API error codes 10–13 and 15 to safe Persian messages. Log only a numeric error code with the existing server/action context. HTTP 503 explains temporary unavailability or the rate limit, without assuming that every 503 is throttling. Unknown errors keep the generic refusal and raw server messages remain private.

## Consequences
- Re-entering a session does not repeatedly call Skyroom while the link is reusable. Cold entry by many distinct people still requires one login API call each; this is not a global rate limiter or pre-generation system.
- Reusing a link never extends its Skyroom TTL. Existing revocation and already-connected-user limitations in ADR 0076 remain.
- Concurrent generation is shared across application processes only when the configured cache/lock store is shared, as with the current database store. Cache loss leads to generating another URL.
- `getServices` validates API access and service selection, not the ability to create an actual room or its purchased capacity. No remote room resizing or service migration is performed.
- The tutorial's optional setup tip teaches service selection; its automated in-person missions are unchanged.
