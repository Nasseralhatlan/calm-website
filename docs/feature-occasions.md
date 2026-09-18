# Occasions — backend + web

Lead capture for the events team: a guest describes an occasion they want us to
plan, we call them back. Built to answer one question while we test — *is there
demand?* — so the storage is deliberately one flat table.

Mobile implemented this first; the web is a 1:1 twin of that wizard. The app's
spec doc is the UI source of truth. **This file is the source of truth for the
API and the data.**

---

## Changes from the mobile spec — read this first

The app's spec was written before the backend existed. Four things differ:

| Spec said | Reality | Why |
|---|---|---|
| `auth: Sanctum Bearer` | **JWT** (`auth:api`) | There is no Sanctum in this codebase — it's `php-open-source-saver/jwt-auth`. No client change: still a Bearer token. |
| `Accept-Language: ar \| en` | **Not read** | Nothing in this app reads that header; locale comes from a cookie (web) or `users.locale`. Send it if you like — it's ignored. |
| status `new \| contacted \| in_progress \| scheduled \| completed \| canceled` | **`new \| under_processing \| ongoing \| completed`** | Four stages, per the product call. Anything else is rejected at the admin form. |
| One column per field | One `details` json blob | See below. |

Everything else — the endpoint, the request body, the option keys — is as specced.

---

## Storage: one table

```
occasion_requests
  id             uuid
  user_id        uuid  → users (cascade delete)
  occasion_type  string(64)      -- birthday | wedding | … | other
  details        json, nullable  -- everything else the wizard asked
  status         string(32)      -- new | under_processing | ongoing | completed
  created_at, updated_at
```

**Why `details` is a blob.** The wizard's questions will keep changing while we
test. Only `occasion_type` gets a column, because it's the one thing we slice
demand by. Everything else — guest count, needs, venue, notes — goes in as sent.
A new chip on mobile needs no migration and no web deploy to be captured, and
the admin table renders unknown keys as the raw key rather than dropping them.

Typical `details`:

```json
{ "guests": 200, "needs": ["coffee","styling"], "needs_other": null,
  "venue_status": "help", "notes": "Outdoor if possible" }
```

Answers the guest skipped are **stripped before saving**, so the blob only ever
contains questions they actually answered.

No contact columns by design: the lead is tied to the account and we call the
account's phone.

---

## API

Both routes are in the `['auth:api', 'throttle:authenticated']` group — a
signed-in user, 120 req/min. Responses use the standard envelope
`{ status, message, data }`.

### `POST /api/occasion-requests` → `201`

```jsonc
{
  "occasion_type": "wedding",          // required, ≤64
  "occasion_type_other": null,         // ≤80, expected when type = "other"
  "needs": ["coffee", "styling"],      // optional, ≤32 items, each ≤64
  "needs_other": null,                 // ≤300
  "guests": 200,                       // required, integer 1..99999
  "venue_status": "help",              // have | help | null
  "notes": "Outdoor if possible"       // ≤1000
}
```

`data` is the created request (see the resource below). `422` with
`data.errors.<field>[]` on validation failure; `401` when the token is missing
or expired.

**Option keys are not validated against a whitelist.** A lead is worth more than
a tidy enum — if the app ships a new chip before the web catches up, it still
lands. Keys are documented in `config/occasions.php`; the app mirrors them in
`constants/occasions.ts`. Add freely, **never rename or delete** — old rows
carry the old key and the admin table labels from this list.

### `GET /api/occasion-requests` → `200`

The signed-in guest's own requests, newest first, paginated 20/page (the client
cannot override the page size). Never another user's.

```jsonc
{ "items": [ … ], "pagination": { "page", "per_page", "total", "last_page", "has_more" } }
```

### Building the guest's "my requests" list

Call `GET /api/occasion-requests` with the user's Bearer token and render
`data.items`. Everything a row needs is in the response — **no second call, and
no client-side status dictionary**:

- `status_label` is already resolved to the request's locale. Show it as-is.
- `occasion_type` is a key; label it from your `constants/occasions.ts` copy of
  the catalogue, and fall back to the raw key when it isn't in your build yet.
- When `occasion_type === "other"`, show `details.occasion_type_other` instead
  of the label — that's the guest's own words.
- `details` keys are all optional. Read defensively; render what's present.

Status colours used on web, if you want to match:

| status | background | text |
|---|---|---|
| `new` | `#FEF4E6` | `#F59E0B` |
| `under_processing` | `#EAF1FE` | `#3B82F6` |
| `ongoing` | `#F2ECFE` | `#8B5CF6` |
| `completed` | `#E8F6EC` | `#16A34A` |

There is no endpoint to edit or cancel a request — the events team moves status
from the admin panel. Poll on screen focus; there is no push for status changes.

### Resource shape

```jsonc
{
  "id": "uuid",
  "occasion_type": "wedding",
  "details": { "guests": 200, "needs": ["coffee"] },   // verbatim, may be {}
  "status": "under_processing",
  "status_label": "قيد المعالجة",                       // resolved to the request locale
  "created_at": "2026-09-18T11:04:00+00:00"
}
```

`details` is echoed back as stored, so old rows keep rendering after the
catalogue changes. Render what's there; don't assume a key exists.

---

## Team alert

Creating a lead queues an `OwnerAlert` email to `config('owner.emails')` with
the occasion, guest count, needs, venue, notes and **the guest's name and
phone** — the guest is told we'll call within minutes.

⚠️ **`OWNER_EMAILS` must be set in production.** `OwnerNotifier::send()` returns
silently when the list is empty, so with it unset the lead is saved and nobody
is told. It is currently commented out in `.env`. A queue worker must also be
running (`QUEUE_CONNECTION=database` + `queue:work`) — the mailable is queued.

---

## Web

| Surface | Route |
|---|---|
| Entry card | home, under the greeting |
| Wizard | `/occasions` — public to fill |
| Guest's own requests | `/trips`, above the bookings list |
| Admin queue | `/admin/occasion-requests` |

`/trips` fetches `GET /api/occasion-requests` client-side — the same endpoint
the app uses, so the two can't drift. The section hides itself when the guest
has none, and a failed fetch stays silent rather than breaking the bookings
list beside it.

The wizard mirrors the app: 4 steps (type → needs → guests → venue+notes) →
confirmation, coral segment indicator, black CTA, selected controls get a dark
border + `#F7F7F7` fill + bold label.

**Auth resume.** Submitting signed-out parks the answers in `sessionStorage`
under `calm:occasion:pending` and opens the login modal. That modal reloads the
page, so on boot the wizard restores the stash and finishes the submit by
itself — the guest never re-enters anything.

**Admin.** Filter by stage, search by guest name or phone, tap the phone to
call, and move a lead between the four statuses. Need keys and venue answers are
rendered through `config/occasions.php` labels; unknown keys fall back to the
raw key rather than disappearing.

---

## Not built

- No status notification to the guest when the team moves a lead. The
  confirmation screen promises they can *follow* status — which `GET
  /api/occasion-requests` supports — not that they'll be told.
- No admin detail page; the queue row shows everything captured.
- The web entry card uses an emoji tile, not the app's fanned photo deck —
  those photos are bundled in the app and aren't in this repo.
