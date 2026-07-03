# Mobile guide: Calendar sync (iCal) — host app implementation

> Audience: the frontend (Expo / React Native) developer or AI building the host app's
> calendar-sync screen. All endpoints below are live and covered by 25 backend tests.
> General API conventions (envelope, JWT auth, 422 shape) are in
> `docs/mobile-host-place-create-frontend-guide.md` §2 and are not repeated here.

---

## 1. What this feature is

Hosts who list the same property on Airbnb/Gathern keep availability in sync by
exchanging **secret iCal links** — the standard "paste a link" mechanism those platforms
use with each other:

- **Export (Calm → them):** every Calm place has a secret URL like
  `https://…/ical/places/{placeId}/{token}.ics`. The host pastes it into Airbnb's
  *Import calendar*; Airbnb then polls it every few hours and blocks Calm's booked dates
  there. The app never fetches this URL itself — it only displays/copies/shares it.
- **Import (them → Calm):** the host pastes Airbnb's/Gathern's iCal link into Calm. The
  backend fetches it (immediately on add, then **hourly**, plus on-demand "Sync now") and
  turns its events into blocked dates here.

**Everything is per place** — each Airbnb listing has its own calendar link, so each Calm
place has its own export URL and its own list of imported feeds. The screen belongs on the
place's calendar/availability area.

Sync is **pull-based**: minutes-to-hours of lag is normal and matches how the platforms
behave with each other. The app never needs to poll anything in the background.

---

## 2. Endpoints (all auth + owner-only)

`403` if the place belongs to another host, `404` if the place (or feed) doesn't exist.

### 2.1 `GET /api/host/places/{placeId}/calendar-sync` — the screen's data

Fetch on screen open. Minting the secret token happens lazily on the first call — the
URL then **never changes** unless the host rotates it.

```json
{ "status": 200, "message": "Calendar sync fetched.", "data": {
    "place_id": "uuid",
    "export_url": "https://calm.example/ical/places/{placeId}/{40-char-token}.ics",
    "feeds": [
      {
        "id": "uuid",
        "place_id": "uuid",
        "name": "Airbnb",
        "url": "https://www.airbnb.com/calendar/ical/1234.ics?s=…",
        "last_synced_at": "2026-07-02T10:00:00+00:00",   // null until first sync
        "last_status": "ok",                              // "ok" | "error" | null
        "last_error": null,                               // human-readable, when status=error
        "created_at": "2026-07-01T08:00:00+00:00"
      }
    ]
} }
```

### 2.2 `POST /api/host/places/{placeId}/calendar-feeds` — connect a feed

```json
// request
{ "name": "Airbnb", "url": "https://www.airbnb.com/calendar/ical/1234.ics?s=…" }
```

- `name`: required, ≤ 100 chars (free label the host picks — "Airbnb", "Gathern"…).
- `url`: required, **http/https only**, ≤ 2048 chars.

The backend **syncs the feed immediately** before responding, so the response already
carries the first sync outcome:

```json
// 201
{ "status": 201, "message": "Calendar connected.", "data": {
    "id": "uuid", "name": "Airbnb", "url": "…",
    "last_synced_at": "2026-07-02T10:00:01+00:00",
    "last_status": "ok",            // or "error" — the feed is still saved!
    "last_error": null, "…": "…"
} }
```

Errors (`422`, `data.errors.url` or `data.errors.name`):
- invalid/non-http(s) URL;
- **feed cap: max 5 feeds per place** — message like "A place can import at most 5 calendars."

Important: a bad-but-valid URL (dead link, wrong page) is **not** a 422 — the feed is
created with `last_status: "error"`. Show the error state and let the host remove/retry.

### 2.3 `DELETE /api/host/places/{placeId}/calendar-feeds/{feedId}` — disconnect

Every date this feed was blocking is **freed instantly**. `200` with message only.
Confirm first — suggested copy: *"Remove this calendar? Every date it blocks becomes
available again."* / *"إزالة هذا التقويم؟ ستتحرر كل التواريخ المحجوبة عبره."*

### 2.4 `POST /api/host/places/{placeId}/calendar-feeds/sync` — Sync now

Re-fetches **all** of this place's feeds synchronously and returns them updated:

```json
{ "status": 200, "message": "Calendars synced.", "data": { "feeds": [ …updated feed objects… ] } }
```

Show a spinner — this does real network fetches server-side (up to ~15 s per feed worst
case; typically < 1 s each).

### 2.5 `POST /api/host/places/{placeId}/calendar-token/rotate` — regenerate the link

```json
{ "status": 200, "message": "Export link regenerated — update it on the other platforms.",
  "data": { "export_url": "https://…/ical/places/{placeId}/{NEW-token}.ics" } }
```

The old URL dies **instantly**. Always confirm first — suggested copy: *"The old link
stops working immediately and must be re-pasted on the other platforms. Continue?"* /
*"سيتوقف الرابط القديم فوراً وستحتاج لتحديثه في المنصات الأخرى. متابعة؟"*

---

## 3. How synced dates show up elsewhere (already live — no extra work)

- **Host calendar** `GET /api/host/calendar?from&to&place_id` — imported dates set
  `external_block: true` on the day objects (the host's own blocks stay `manual_block`).
  Render them visually distinct (web uses a link 🔗 icon + blue vs. red).
- **Blockings list** `GET /api/host/places/{id}/blockings` — each item carries
  `source: "manual" | "ical"`.
- **Imported blocks cannot be deleted manually**:
  `DELETE /host/places/{id}/blockings/{blockingId}` on a `source: "ical"` row returns
  `422` (`data.errors.blocking` — "managed by a connected calendar"). **Hide the unblock
  action** for them; show a "Synced" tag instead (web shows "Synced · via {feed name}").
  They're freed by cancelling on the source platform (next sync) or removing the feed.
- Guest-side search/quotes/unavailable-dates exclude imported dates automatically.
- Sync prevents **future** double-bookings only — it never cancels an existing confirmed
  Calm booking. If a conflict slips through the polling gap, both records coexist and the
  host resolves it manually (same behavior as Airbnb).

---

## 4. Screen spec (mirror of the web "Calendar sync" card)

**Section A — Export ("Send Calm's calendar to other platforms")**
- Read-only URL field + **Copy** button (use `expo-clipboard`; show "Copied ✓" for ~2 s)
  and ideally the native **Share** sheet — hosts often want to send the link to themselves
  to open Airbnb's dashboard on desktop.
- Helper text: *"Paste this link into Airbnb, Gathern or Google Calendar so your Calm
  bookings block those dates there automatically."*
- Overflow/secondary action: **Regenerate link** (confirm dialog from §2.5).

**Section B — Import ("Bring other platforms' bookings into Calm")**
- Feed list: name · status badge (`ok` → green "Synced", `error` → red "Error" with
  `last_error` on tap, `null` → grey "Waiting") · relative `last_synced_at` ("synced 12 min
  ago") · truncated URL · Remove.
- Add form: name + URL (paste-first UX; validate http/https client-side). After a
  successful POST, refresh the list from the response and — nice touch — refresh the
  calendar view so the host sees the new blocked dates instantly.
- **Sync now** button (only when ≥ 1 feed) with spinner; replace the list from the response.
- Disable Add when 5 feeds exist, with a hint about the cap.
- Helper text: *"Feeds re-sync automatically every hour."*

**In-app help worth including:** where hosts find their link on the other platforms —
Airbnb: *Calendar → Availability settings → Connect calendars → Export calendar*;
Gathern: unit calendar settings → iCal export.

---

## 5. Edge cases & rules checklist

1. `export_url` is a **secret** — don't log it in analytics/crash reports.
2. The URL is stable across `GET calendar-sync` calls; only `rotate` changes it. Safe to
   cache per place; refresh after rotate.
3. Feed with `last_status: "error"` still exists and still keeps its previously-imported
   blocks (a temporary outage on Airbnb's side never wipes availability). The badge is
   informational, not fatal.
4. Adding a feed can take a couple of seconds (server fetches the URL inline) — keep the
   submit button in a loading state.
5. Don't offer "unblock" on `source: "ical"` blockings anywhere in the app (server 422s it).
6. No background polling needed: hourly sync is server-side; "Sync now" covers impatience.
7. All five endpoints follow the standard envelope + error shapes from the main guide
   (`401` unauthenticated, `403` not the owner, `422` validation at `data.errors.<field>`).

---

## 6. How to test against the dev backend

Self-contained loop, no Airbnb account needed — sync one Calm place into another:

1. Pick two approved places A and B of dev host `501203845`.
2. `GET /api/host/places/{A}/calendar-sync` → copy A's `export_url` (A has seeded bookings,
   so its feed has real events).
3. `POST /api/host/places/{B}/calendar-feeds` with `{name: "Test A", url: <A's export_url>}`
   → expect `201`, `last_status: "ok"`.
4. `GET /api/host/calendar?place_id={B}&from=…&to=…` → A's booked dates appear as
   `external_block: true` days on B.
5. `DELETE` the feed → the dates free up again.
6. Error path: connect `https://example.com/nothing.ics` → feed saves with
   `last_status: "error"` and a `last_error` message.
