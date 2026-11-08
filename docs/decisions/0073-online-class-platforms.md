# 0073 — Online classes on several platforms, starting with Adobe Connect

- **Date:** 2026-11-08
- **Status:** accepted (in part replaces the «only a meeting link» choice of [ADR 0022](0022-classes.md))

## Context
Online classes had one meeting link per class (ADR 0022). The organization runs its classes on several platforms (Adobe Connect, Skyroom, Google Meet, …), chosen per class, and a class may move to another platform in the middle of its term: for example Adobe Connect for the first sessions, then Skyroom. Saraye Danesh already runs its classes on its own Adobe Connect servers, and the company's older system (`/home/sepehr/projects/sarayedanesh`, `Modules/School/Http/Services/AdobeConnect`) talks to them. It makes an Adobe account for each person (`user_{id}@{host}`) with the administrator's own password, gives host or view access to each meeting, logs the person in through the API and sends them to the meeting with `?session=`.

## Options considered
- **Where the code lives:** a separate module that requires Class (could be sold and switched off on its own, ADR 0041) / **inside Class**. The user chose Class.
- **Adobe rooms** (later made a choice per class, [ADR 0074](0074-online-room-per-session.md)): **one meeting per class** (a fixed address, recordings collected in one place, few API calls; a move to another platform starts a new room) / one meeting per session as in sarayedanesh (recordings per session, but many more calls and meetings made ahead of time). The user chose one per class.
- **Server settings:** **an admin page** (several servers, chosen per class; the password kept encrypted) / `.env` (only the developer can change them). The user chose the admin page.
- **Account passwords:** the administrator's password for everyone (sarayedanesh: anyone who learns it can sign in as anyone) / a random password stored per person / **derived from the app key** (`HMAC(app key, server + user)`: nothing to store, different for each person and server).
- **The way in:** show the room's address / **a route in our app** (`/online-classes/sessions/{id}`) that checks who may enter and as what, makes what is missing on the platform and redirects.

## Decision
- **Platforms** are `OnlinePlatform` cases, each with a driver (`OnlinePlatformDriver`: `enter()` returns the address to send the person to, `problemWith()` checks a server). For now: «لینک» (`LinkDriver`, any address as it is: Google Meet, Skyroom, …) and «Adobe Connect» (`AdobeConnectDriver`). A new platform is a case and a driver.
- **Servers** (`online_servers`, `OnlineServer`, installation-wide, `IndependentOfTenant`): «کلاس‌ها ← سرورهای کلاس آنلاین», platform, host, administrator's login and password (encrypted cast; empty on edit keeps it), settings (Adobe: folder of meetings, `login_prefix`, SSL check), active. «آزمایش اتصال» logs in and finds the folder. A server used by a class can't be deleted, only deactivated. Default permissions go to `org_admin`.
- **Rooms** (`class_online_rooms`, `ClassOnlineRoom`, per tenant): a class's platform from a day on (`starts_on`, null = from the beginning). A session meets in the room in effect on its day ([OnlineRooms](../../Modules/Class/app/Services/Online/OnlineRooms.php)). The class form has «بستر کلاس آنلاین» (the link, or an active server) and the link; picking another platform for a class that has one asks «بستر تازه از تاریخ» (default today). Sessions before that day keep their room; rooms planned after it are dropped; a class with no session before the day just gets the new room from the beginning. The class's «اطلاعات» lists the platforms with their days. The old `classrooms.meeting_url` moved into a «لینک» room from the beginning (migration) and the column is gone.
- **Who enters** ([OnlineEntry](../../Modules/Class/app/Services/Online/OnlineEntry.php)): the class's staff (ADR 0070) and whoever may edit the class as **hosts**, at any time; attendees (members on the session's day) as **participants**, from `CLASS_ONLINE_OPENS_MINUTES_BEFORE` (15) minutes before the start until the end. Nobody enters a cancelled session. Guardians see the platform, not the way in. A refusal opens a page with the reason (`Class::OnlineRefused`).
- **The route** `class.online.enter` works from both panels: it makes the session's tenant current for the request (the person must belong to it, or be a super admin). «کلاس‌های من» shows «ورود به کلاس آنلاین» on a session's page; the admin session page shows the platform with the same link.
- **Adobe Connect** ([AdobeConnectApi](../../Modules/Class/app/Services/Online/AdobeConnectApi.php), [AdobeConnectDriver](../../Modules/Class/app/Services/Online/AdobeConnectDriver.php)), XML API over Laravel's HTTP client (no new package; sarayedanesh used `brunogasparetto/adobe-connect-client`):
  - sessions come from `common-info` and are logged in with `login`; the administrator's is cached for 10 minutes and made again on `no-login`;
  - on the first entry to a room, a meeting `«{class} ({code}) #{room}»` is made in the server's folder (or its «Shared Meetings») and closed to guests (`public-access` → `denied`);
  - on a person's first entry an account `{prefix}{user id}@{host}` is made (or an existing one with that login is taken over and given our password), its principal-id kept in `online_server_accounts`;
  - each entry gives the person `host` or `view` on the meeting, logs in as them and redirects to `{host}{url-path}?session=…`. A deleted account is made again; a failed login sets the password again once.

## Consequences
- A class can change platform mid-term without losing where its earlier sessions met; Adobe's earlier meeting and its recordings stay on the server.
- Skyroom, Google Meet and others work today as links; a real integration (accounts, host rights) is a driver away.
- Changing `APP_KEY` changes every derived password; the next entry resets it on the server, so nobody is locked out.
- Access is given when people enter, not taken away: someone who leaves the class keeps their `view` permission on the Adobe meeting (but can't get a session from us, and the meeting is closed to guests). A host's rights are not lowered if they later become an attendee.
- Not done: recordings (listing, passcodes, deleting old ones, as sarayedanesh does), attendance from Adobe's reports, several rooms per class (e.g. per sub-class they are separate classes anyway), choosing a server per tenant by default, Skyroom / BigBlueButton drivers. Adobe Connect was tested only against a faked server, not a real one.
- Every entry makes 3–5 calls to Adobe (fewer after the first); a busy start of many classes at once hits the server at the same time.
