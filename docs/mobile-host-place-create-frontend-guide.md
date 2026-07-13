# Mobile guide: Host place create/edit — APIs + how the web wizard works

> Audience: the frontend (Expo / React Native) developer or AI implementing the host
> "add place" and "edit place" flows in the mobile app.
> Everything below is live on the backend and covered by feature tests. The mobile flow
> should be a faithful port of the web wizard, which this doc describes in detail.

---

## 1. Big picture

A **place** (listing) is created by a host through a multi-step wizard. The lifecycle:

```
draft ──(final submit)──► pending_review ──(admin approves)──► approved (+ status: active)
  ▲                                        └─(admin rejects)─► rejected (+ rejection_reason)
  └── auto-saved on every step advance          rejected places are re-editable & resubmittable
```

Two independent columns drive visibility:

| Field | Values | Meaning |
|---|---|---|
| `review_status` | `draft`, `pending_review`, `approved`, `rejected` | Admin review pipeline |
| `status` | `active`, `inactive` | Live visibility. Only `active` + `approved` places appear in guest search |

Key facts the mobile app must respect:

- **Drafts are server-side.** Every step advance POSTs the whole current wizard state to
  `/api/host/places/draft`. The first call returns an `id`; every later call sends that
  `id` back as `draft_id` so the same row keeps being updated.
- **Photos are uploaded before submit**, directly to S3 via presigned PUT URLs. The API
  only ever receives string *paths* — never file bytes.
- **Final submit** promotes the draft to `pending_review`. It requires ≥ 5 photos and the
  full required field set.
- **Editing an existing listing** always resubmits it: the service forces
  `review_status = pending_review` and `status = inactive` (offline until re-approved) and
  clears any old `rejection_reason`.

---

## 2. API conventions

- Base path: `/api`. All host endpoints require the JWT from the OTP login flow:
  `Authorization: Bearer <token>`, plus `Accept: application/json`.
- Rate limits: public endpoints 30/min per IP, authenticated 120/min per user.
- **Every response uses one envelope:**

```json
{ "status": 200, "message": "Host place fetched.", "data": { ... } }
```

- **Validation failure (422):**

```json
{
  "status": 422,
  "message": "Validation failed.",
  "data": { "errors": { "price": ["The price field is required."], "images": ["A place must have at least 5 images."] } }
}
```

- Other errors: `401` unauthenticated, `403` not the owner, `404` unknown/soft-deleted
  place. Same envelope, `data` is null.

---

## 3. Endpoint catalog

### 3.1 Reference data (public, load once when the wizard opens)

| Endpoint | Purpose |
|---|---|
| `GET /api/place-types` | Step 1 cards: `{id, name_ar, name_en, icon}` |
| `GET /api/cities` | Step 3/4 picker: cities with nested `areas` (eager-loaded) |
| `GET /api/attribute-groups` | Steps 6–8 amenity catalog (below) |
| `GET /api/settings` | `support_phone`, `support_email`, **`commission_percentage`**, **`vat_percentage`** (strings, may be null) |

`GET /api/attribute-groups` → `data` is an array of groups, both ordered by the
admin-controlled `sort_order` (then `name_en`):

```json
[
  {
    "id": "uuid", "name_ar": "…", "name_en": "Facilities", "sort_order": 1,
    "attributes": [
      {
        "id": "uuid", "group_id": "uuid",
        "name_ar": "مسبح", "name_en": "Pool", "icon": "pool",
        "question_ar": "…", "question_en": "…",
        "type": "boolean|number|text|select|multi_select",
        "photo_rule": "none|optional|required",
        "is_highlighted": true,
        "options": ["indoor", "outdoor"],
        "sort_order": 2
      }
    ]
  }
]
```

How the web uses this catalog (mirror it):

- `type` — in practice `boolean` (presence chip: WiFi, TV…) or `number` (countable:
  bedrooms, bathrooms…). The wizard renders all of them as toggle chips; `number` ones get
  a +/− count stepper on the configure step.
- `photo_rule` — `required`/`optional` attributes each get their own photo section on the
  photos step; `required` means at least 1 photo in that section before submit.
- `is_highlighted` — these attributes are ALSO shown in a separate "Highlights" section
  at the top of the amenities step (they stay in their group below too).

### 3.2 Photo upload (auth)

**`POST /api/host/uploads/presign`** — body `{"filename": "IMG_1234.jpg", "mime": "image/jpeg"}`

```json
{ "status": 200, "message": "Upload URL minted.", "data": {
    "put_url": "https://<bucket>...&X-Amz-Signature=...",   // valid 15 minutes
    "path": "places/uploads/x7k2...f9q.jpg",                 // KEEP THIS — it is what you send in payloads
    "public_url": "https://cdn.../places/uploads/x7k2...f9q.jpg",  // for the local preview
    "mime": "image/jpeg"
} }
```

Then upload the bytes (no auth header — the URL is self-authorizing):

```
PUT {put_url}
Content-Type: {mime}        // must equal the mime you presigned with
x-amz-acl: public-read      // required — it is part of the signature
Body: the (compressed) image bytes
```

Notes:
- Only the *extension* of `filename` is used; the server mints a random key under
  `places/uploads/`. Missing extension defaults to `.jpg`.
- Expo upload body: `await (await fetch(localUri)).blob()`.
- Compress first with `expo-image-manipulator`:
  `manipulateAsync(uri, [{ resize: { width: 2048 } }], { compress: 0.7, format: SaveFormat.JPEG })`
  (JPEG — the manipulator's WebP support is inconsistent; skip re-compression for already-small files).
  The web equivalent targets a 2048px max edge / ~300 KB, and converts HEIC → JPEG first.
- Upload photos **as they are picked** (per file, in parallel), keep an in-memory row per
  upload with `status: 'uploading' | 'done' | 'failed'` and only ever put **done** paths
  into payloads. Failed uploads offer a retry and are never sent.

### 3.3 Host wizard endpoints (auth, owner-only)

| Endpoint | Purpose |
|---|---|
| `POST /api/host/places/draft` | Auto-save (upsert). Returns `{id, review_status}` |
| `POST /api/host/places` | Final submit → `201` + full `HostPlaceResource`, `review_status: pending_review` |
| `GET /api/host/places/{id}` | Full editable place (drafts included) — resume + edit hydration |
| `PUT /api/host/places/{id}` | Save an edit → resubmits for review, returns full resource |
| `PATCH /api/host/places/{id}/status` | Pause/unpause: body `{"status": "active"\|"inactive"}` → `{id, status, review_status}`. Pausing always works; **activating requires `review_status: approved`** (else `422` on `data.errors.status`). Never triggers re-review — use this, not PUT, for the toggle |
| `DELETE /api/host/places/{id}` | Soft-delete (archive). Place disappears from all lists |
| `GET /api/host/listings?status=` | "My places" cards. Optional tab filter: `draft`, `pending_review`, `approved`, `rejected`, `active` |

Ownership: any `{id}` route returns `403` if the place belongs to another host, `404` if
it doesn't exist or was deleted. There is **no** admin "attach to host phone" path on
mobile — the place always belongs to the authenticated user.

---

## 4. How the web wizard works (port this to mobile)

The web flow is a 9-step, one-question-per-screen wizard with a progress bar. State lives
client-side the whole time; the server draft is a mirror that enables resume.

### The 9 steps, their fields, and their "can advance" gates

| # | Step | Fields collected | Gate to advance (client-side) |
|---|---|---|---|
| 1 | **Type** | `place_type_id` (radio cards from `/place-types`) | type picked |
| 2 | **The basics** | `title_ar`, `title_en` (max 120 in UI), `description_ar`, `description_en` (max 5000), `max_guests` (1–50 stepper, defaults 1) | at least ONE title language non-empty |
| 3 | **City** | `city_id` (client-side only — never sent; it just scopes the area list) | city picked |
| 4 | **Area + location** | `city_area_id` (areas of the picked city), `location_url` (pasted Google-Maps-style link, shown to guests only after a confirmed booking) | area picked AND `location_url` parses as a valid http(s) URL |
| 5 | **Pricing** | `price` (base, integer SAR) + optional per-day overrides `price_sunday` … `price_saturday` (empty = "use base"; sent as `0` when empty). Shows a live preview: `commission = price × commission_percentage / 100`, `host take-home = price − commission` (rate from `/api/settings`, fallback 15) | `price > 0` |
| 6 | **Amenities (pick)** | toggle chips for every attribute in every group; a "Highlights" section (all `is_highlighted` attrs across groups) renders on top. Selecting stores `{count: 1, description: ''}` per attribute id | at least 1 attribute selected |
| 7 | **Configure** | for each selected attribute: a count (+/− stepper, min 1 — this becomes the string `value`) and a free-text `description` | every selected attribute has `count ≥ 1` |
| 8 | **Photos** | one upload section per selected attribute whose `photo_rule` is `required` or `optional`, plus a general "Other photos" section; per-section cap **10**; the host also picks up to **10** "featured" photos (ordered showcase, first = cover) and can reorder sections/photos | ≥ 5 photos uploaded (done) overall AND every `photo_rule: required` attribute section has ≥ 1 done photo |
| 9 | **Rules & timing** | `check_in_time` / `check_out_time` (HH:MM select), `checkout_next_day` (bool, default true), `rules_ar` / `rules_en` (textareas pre-filled with default house rules templates on a fresh create) | check-in and check-out non-empty. This gate also enables the final **Submit** button |

Notes:
- Re-picking the same city keeps the chosen area; switching cities clears the area.
- Deselecting an amenity on step 6 also drops its photos/config.
- Check-in/out defaults: `15:00` / `12:00`.

### Draft auto-save — exactly when and what

`next()` (advancing a step) in **create mode** first `await`s a draft save, then moves on.
(Going back does not save. Edit mode never auto-saves — see §6.) The Next button shows a
small spinner while the save is in flight; failures are **silent** (logged, wizard keeps
working from memory — the final submit still carries everything).

`POST /api/host/places/draft` payload (JSON):

```json
{
  "draft_id": null,                     // null on the very first save; then the returned id, every time
  "place_type_id": "uuid",              // the ONLY required field for a draft
  "title_ar": "…", "title_en": null,
  "description_ar": null, "description_en": null,
  "city_area_id": null,
  "price": 0,
  "price_sunday": 0, "price_monday": 0, "price_tuesday": 0, "price_wednesday": 0,
  "price_thursday": 0, "price_friday": 0, "price_saturday": 0,
  "check_in_time": "15:00", "check_out_time": "12:00",
  "checkout_next_day": true,
  "max_guests": 1,
  "rules_ar": "…", "rules_en": "…",
  "location_url": null,
  "last_step": 3,                       // the step the host is ON — powers resume

  // ▼ CONDITIONAL — see the two guards below
  "attributes": [ { "attribute_id": "uuid", "value": "2", "description": "…" } ],
  "attribute_image_paths": { "<attribute_id>": ["places/uploads/a.jpg"] },
  "extra_image_paths": ["places/uploads/b.jpg"],
  "featured": ["attribute_images.<attribute_id>.0", "extra_images.0"]
}
```

Response: `{"status":200, "message":"Draft saved.", "data": {"id":"uuid","review_status":"draft"}}` —
store `data.id` as `draft_id` for every subsequent call (draft saves AND the final submit).

**Two include-guards (important — the web does exactly this):**

1. `attributes` is only included once the host has selected ≥ 1 amenity. Sending an empty
   array is a server-side no-op for drafts, but omitting keeps the payload clean.
2. The three photo keys (`attribute_image_paths`, `extra_image_paths`, `featured`) are only
   included once **at least one upload is done**. When omitted entirely, the server leaves
   already-saved photos untouched, so a pre-photos-step auto-save can never wipe photos a
   resumed draft already had.

Draft validation is lenient: everything nullable except `place_type_id`; `location_url` is
not URL-validated on drafts (partial pastes shouldn't block auto-save); the min-5-photos
rule does NOT apply. Per-section photo caps (10) DO apply.

### Final submit

`POST /api/host/places` — **same payload shape** as the draft (including `draft_id`, so
the server *promotes the draft row* instead of creating a duplicate), minus `last_step`.
Stricter validation:

- ≥ 1 of `title_ar` / `title_en`
- `place_type_id`, `city_area_id`, `price`, `check_in_time`, `check_out_time`,
  `max_guests` (1–50) all required
- `location_url` required and must be a valid URL
- **≥ 5 total photos** across all sections (else `422` with `data.errors.images`)
- per-section cap 10, `featured` cap 10

Success: `201` with the full `HostPlaceResource` (§5) — `review_status` is
`pending_review`, `status` is `inactive`. Show the "submitted for review" state and
navigate to My Places. If the submitted draft had been `rejected`, the old
`rejection_reason` is cleared automatically.

### Photo grouping model & the `featured` marker format

Photos live in named groups:

- One group per attribute (keyed by `attribute_id`) → sent as
  `attribute_image_paths: { "<attribute_id>": [path, path, …] }` (order inside the array =
  display order the host arranged).
- One general group → `extra_image_paths: [path, …]`.

`featured` is an **ordered** list of composite string markers pointing at positions in
those arrays — the photos "shown outside" on the public place page; **the first marker is
the cover photo**:

- `attribute_images.<attribute_id>.<index>` → `attribute_image_paths[<attribute_id>][<index>]`
- `extra_images.<index>` → `extra_image_paths[<index>]`

So `featured: ["extra_images.2", "attribute_images.abc.0"]` = cover is the 3rd general
photo, second showcase photo is the 1st photo of attribute `abc`. Indexes refer to the
arrays *as sent in the same request* — recompute markers whenever the host reorders or
deletes photos. If the host picked no featured photos, the web defaults to featuring the
first photo so the listing always has a cover — do the same.

Gallery order: the server assigns `sort_order` walking your groups in the order sent
(attribute groups first, in the host's chosen section order; general group last), so the
order of keys/arrays in the payload is meaningful.

### Resume ("Continue" on a draft)

1. `GET /api/host/places/{id}` → hydrate all scalar fields (see the resource in §5).
2. Rebuild the amenity state from `attributes`: `selected[attribute_id] = { count: Number(value) || 1, description }`.
3. Rebuild photo groups from the **flat** `photos` array (already ordered by `sort_order`),
   exactly like the web does:

```js
const attributeUploads = {};   // attribute_id -> [{path, url, status:'done'}]
const extraUploads = [];
const sectionOrder = [];       // first-seen order of attribute ids = section order
const featuredRows = [];
for (const p of place.photos) {
  const entry = { path: p.path, url: p.url, status: 'done' };
  if (p.place_attribute_id != null) {
    (attributeUploads[p.place_attribute_id] ??= []).push(entry);
    if (!sectionOrder.includes(p.place_attribute_id)) sectionOrder.push(p.place_attribute_id);
  } else {
    extraUploads.push(entry);
  }
  if (p.featured_order != null) featuredRows.push({ entry, order: p.featured_order });
}
const featured = featuredRows.sort((a, b) => a.order - b.order).map(r => r.entry); // recompute markers from these
```

4. Jump to `last_step` (fallback: step 1).
5. Continue the normal flow — every save sends `draft_id`.

Which rows are resumable: the draft endpoints accept a `draft_id` matching one of the
host's own places in `draft` **or `rejected`** state (rejected listings are re-editable so
the host can fix the feedback and resubmit). A `rejected` place has `rejection_reason`
set — show it in a banner at the top of the wizard ("Reviewer feedback: …, fix and
resubmit"). Anything in `pending_review`/`approved` is not a draft — edits to those go
through the edit flow below.

---

## 5. `HostPlaceResource` — the editable shape

`GET /api/host/places/{id}`, and the body of successful `POST /api/host/places` /
`PUT /api/host/places/{id}` responses:

```json
{
  "id": "uuid",
  "status": "inactive",                 // active | inactive
  "review_status": "draft",             // draft | pending_review | approved | rejected
  "rejection_reason": null,
  "last_step": 5,

  "title_ar": "شاليه", "title_en": "Chalet",
  "description_ar": "…", "description_en": null,
  "rules_ar": "…", "rules_en": null,

  "place_type_id": "uuid",
  "city_id": "uuid",                    // derived from the area — pre-selects the city step
  "city_area_id": "uuid",

  "price": 900,
  "price_sunday": 0, "price_monday": 0, "price_tuesday": 0, "price_wednesday": 0,
  "price_thursday": 0, "price_friday": 1200, "price_saturday": 0,
  "check_in_time": "15:00", "check_out_time": "12:00",
  "checkout_next_day": true,
  "max_guests": 8,
  "location_url": "https://maps.google.com/?q=24.7,46.6",

  "attributes": [
    { "attribute_id": "uuid", "value": "2", "description": "Heated" }
  ],
  "photos": [
    { "place_attribute_id": "uuid", "path": "places/uploads/a.jpg", "url": "https://cdn…/a.jpg", "featured_order": 0, "sort_order": 0 },
    { "place_attribute_id": null,   "path": "places/uploads/b.jpg", "url": "https://cdn…/b.jpg", "featured_order": null, "sort_order": 1 }
  ],
  "created_at": "2026-07-02T10:00:00+00:00",
  "updated_at": "2026-07-02T10:05:00+00:00"
}
```

Notes:
- Day prices: `0` (or null on old drafts) means "falls back to the base `price`" — render
  those inputs as *empty* with the base price as placeholder, never as a literal 0.
- `photos` is intentionally FLAT; the client regroups (algorithm in §4). `place_attribute_id`
  holds the *attribute* id (matches keys of `attribute_image_paths`), or null for general photos.
- On a draft, most scalars can be null — hydrate defensively with the same defaults as a
  fresh wizard (`check_in 15:00`, `check_out 12:00`, `checkout_next_day true`, `max_guests 1`).

---

## 6. Edit flow (existing listing) — how the web does it

Editing reuses the **same wizard UI** with different mechanics:

| | Create mode | Edit mode |
|---|---|---|
| Entry | blank wizard | hydrate from `GET /api/host/places/{id}` |
| Navigation | linear, gated per step | **free jumping** between steps (a step-chip nav bar); starts at step 1 |
| Auto-save | on every `next()` | **none** — nothing persists until the explicit Save |
| Persist | `POST …/draft` each step, `POST /api/host/places` at the end | one `PUT /api/host/places/{id}` on Save |
| Extra UI | — | sticky Save / Discard (confirm + reset state) / Cancel bar |

`PUT /api/host/places/{id}` body = the same field set as the final submit (no `draft_id`,
no `last_step`). Validation is submit-strength (titles, required fields, valid URL).

**Critical semantics — the PUT is a full-state replace:**

- `attributes`: ALWAYS send the complete current selection. Sending none/empty **deletes
  every amenity** on the listing ("the edit is the full desired state").
- Photos: if you include `attribute_image_paths`/`extra_image_paths`, they **replace the
  entire gallery** (and the ≥ 5 total rule applies — `422` on `data.errors.images`
  otherwise). If the user never touched photos you MAY omit all three photo keys and the
  existing gallery is kept as-is — but since the mobile wizard hydrates the full photo
  state anyway, the simplest correct behavior is to always send the full photo payload,
  exactly like the web.
- After a successful save the listing is **`pending_review` + `inactive`** (offline until
  an admin re-approves) and any old `rejection_reason` is cleared. Tell the host this
  before saving ("Saving resubmits your place for review and takes it offline until
  approved") — the web copy does.

`DELETE /api/host/places/{id}` archives (soft-deletes) the listing — reversible only by
support. Confirm with the user first. Afterwards the place 404s everywhere.

---

## 7. My Places screen

`GET /api/host/listings?status=<tab>` — returns **ALL** the host's listings in one
response (deliberately unpaginated — hosts own a handful of places; no infinite scroll
needed). Note: the other host lists (`/host/bookings`, `/host/reviews`) ARE still
paginated with the `pagination` block.

```json
{ "data": {
    "items": [ {
      "id": "uuid", "title": "…", "title_ar": "…", "title_en": "…",
      "cover_photo_url": "https://…",
      "price": 900, "max_guests": 8,
      "type":      { "id": "…", "name_en": "…", "name_ar": "…", "icon": "…" },
      "city":      { "id": "…", "name_en": "…", "name_ar": "…" },
      "city_area": { "id": "…", "name_en": "…", "name_ar": "…" },
      "status": "inactive", "review_status": "draft", "rejection_reason": null,
      "likes_count": 0, "bookings_count": 0,
      "rating": { "avg": null, "count": 0 },
      "created_at": "…"
    } ]
} }
```

- Tabs map to `?status=`: `draft`, `pending_review`, `approved`, `rejected` filter
  `review_status`; `active` filters the live `status` column. No param = everything.
  Unknown values → `422`. (Tabs can also be done fully client-side from the one
  unfiltered response, since it's the complete set.)
- Card actions by state (mirror the web "My places" page):
  - `draft` → **Continue** (resume wizard with this id)
  - `rejected` → show `rejection_reason` badge + **Fix & resubmit** (resume wizard — the
    draft endpoints accept rejected rows)
  - `pending_review` → read-only "in review" badge (edits still allowed via edit flow)
  - `approved` + `status: active` → **Edit** (warn it goes back to review) + **Pause**
    (`PATCH .../status` with `inactive`) + **Delete**
  - `approved` + `status: inactive` → "Paused" badge + **Activate** (`PATCH .../status`
    with `active`). Only approved places can be activated — for any other review state the
    server answers `422`, so don't render the Activate action there.

---

## 8. Constraints cheat-sheet

| Thing | Limit |
|---|---|
| `title_ar` / `title_en` | ≥ 1 required on submit; server max 255 (web UI caps at 120) |
| `description_*`, `rules_*` | server max 10 000 (web UI caps at 5 000) |
| `price`, `price_<day>` | integer ≥ 0; day = 0 → falls back to base |
| `max_guests` | integer 1–50 |
| `check_in_time` / `check_out_time` | string max 8, use `HH:MM` |
| `location_url` | required + valid URL on submit/edit; free text ≤ 2048 on draft |
| `attributes.*.value` | string ≤ 255 (the web stores the count as a string) |
| `attributes.*.description` | ≤ 1000 |
| photos per section (each attribute AND the general section) | **10** |
| photos total on submit | **≥ 5** (draft: no minimum) |
| `featured` | ≤ 10 markers, each ≤ 255 chars, first = cover |
| photo `path` strings | ≤ 500 |
| presign URL validity | 15 minutes — presign right before each PUT, don't stockpile |

---

## 9. Calendar sync (iCal — Airbnb / Gathern style), per place

Hosts who also list on Airbnb/Gathern sync availability by exchanging secret iCal links,
one per listing. All endpoints are auth + owner-only, same conventions as above.

> **Full implementation guide** (request/response examples, screen spec, UX copy, edge
> cases, dev-testing recipe): `docs/mobile-calendar-sync-frontend-guide.md`. The table
> below is the summary.

| Endpoint | Purpose |
|---|---|
| `GET /api/host/places/{id}/calendar-sync` | The sync screen: `{place_id, export_url, feeds: []}` |
| `POST /api/host/places/{id}/calendar-feeds` | Connect an external link `{name, url}` → `201` + feed (synced immediately) |
| `DELETE /api/host/places/{id}/calendar-feeds/{feedId}` | Disconnect — every date it blocked frees instantly |
| `POST /api/host/places/{id}/calendar-feeds/sync` | "Sync now" → re-fetches all feeds, returns updated `feeds` |
| `POST /api/host/places/{id}/calendar-token/rotate` | New secret export URL (old link dies instantly) → `{export_url}` |

Feed shape: `{id, place_id, name, url, last_synced_at, last_status: "ok"|"error"|null,
last_error, created_at}`. Show name + last sync time + an ok/error badge (`last_error` in a
tooltip/detail).

**Export (`export_url`):** show it read-only with a Copy button and the instruction "paste
this into Airbnb/Gathern/Google Calendar's *Import calendar* — their side re-fetches it
every few hours". The secret token is minted on first `GET calendar-sync` and never
changes unless rotated (confirm before rotating: the pasted links stop working).

**Import:** the host pastes the other platform's iCal URL (`name` ≤ 100, `url` ≤ 2048,
http/https only). Max **5 feeds per place** (`422` on `data.errors.url`). Feeds re-sync
hourly server-side; adding one syncs immediately, and "Sync now" covers urgent cases.

**How it shows up elsewhere (already live, no extra wiring):**
- `GET /api/host/calendar` day flags now include `external_block: true` for imported dates
  (`manual_block` stays for the host's own blocks).
- `GET /api/host/places/{id}/blockings` items carry `source: "manual" | "ical"`.
- Imported (`source: "ical"`) blockings CANNOT be deleted via
  `DELETE /host/places/{id}/blockings/{blockingId}` — it returns `422`
  (`data.errors.blocking`): they're managed by their feed (cancel on the source platform or
  remove the feed). Hide the unblock button for them; show a "Synced" tag + feed name instead.
- Sync prevents *future* double-bookings only — it never cancels an existing confirmed
  Calm booking.

## 10. Implementation checklist / gotchas

1. **Always thread `draft_id`.** Losing it creates duplicate drafts. Persist it (and
   `last_step`) locally too, so an app kill can resume even before re-fetching.
2. **Guard the photo keys on draft saves** — never send `attribute_image_paths` /
   `extra_image_paths` / `featured` until ≥ 1 upload is done (§4). Same for `attributes`
   until ≥ 1 selection.
3. **Featured markers are positional** — recompute the whole `featured` array from current
   group state right before every request that includes photos.
4. **Send day prices as `0`, not null/empty-string**, when the host left them blank.
5. **`city_id` is client-side only** — the API takes `city_area_id`; `city_id` comes back
   in the resource purely to pre-select the picker.
6. **Edit = full-state PUT.** Omit `attributes` and you wipe the amenities. Include the
   full photo payload. Warn about the pending-review + offline flip before saving.
7. **422 handling:** errors sit at `data.errors.<field>`; the photo-minimum error uses the
   pseudo-field `images`. Map array errors like `attributes.0.attribute_id` back to the
   right step when jumping the user to fix things.
8. `value` for amenities is a **string** (`"2"`), not a number — the web sends the chip
   count stringified; hydrate with `Number(value) || 1`.
9. The `x-amz-acl: public-read` header on the S3 PUT is mandatory (it's signed).
   `Content-Type` must exactly match the presigned `mime`.
10. Draft-save failures should be **non-blocking** (toast/log and continue) — the web
    treats them as best-effort; the final submit carries the full state regardless.
11. All bilingual content is edited as `*_ar` / `*_en` pairs; the backend derives the
    canonical `title`/`description`/`rules` (Arabic wins, else English) — never send those
    base fields.
12. Fresh-wizard defaults to replicate: `check_in 15:00`, `check_out 12:00`,
    `checkout_next_day: true`, `max_guests: 1`, rules textareas pre-filled from a house-rules
    template (mobile can ship its own localized template).

---

## Addendum: place coordinates (map pin) — added 2026-07-13

**Write (wizard):** `latitude` + `longitude` (decimal degrees) are accepted on
draft, submit, and edit — always as a PAIR (`422` if only one is sent).
Bounds: lat ∈ [-90, 90], lng ∈ [-180, 180]. On submit, `location_url` is now
`required_without:latitude` — a pin fully replaces the pasted URL, and when
only the pin is sent the server derives
`location_url = https://maps.google.com/?q={lat},{lng}` automatically.

**Read — three privacy tiers:**
- `ApiHostPlaceResource` (edit/resume): `latitude`/`longitude` = the EXACT pin.
- Public `ApiPlace` / `ApiPlaceDetail`: `latitude`/`longitude` are
  **approximate by design** — rounded to 2 decimals (≈ ±1 km). Render the
  details map as an area circle, not a precise marker. Null until the host
  sets a pin (keep the current text fallback).
- Booking payload (`place` block): EXACT `latitude`/`longitude` appear only
  when the booking is confirmed/completed — same unlock as `location_url`.
  Use these for post-booking navigation instead of parsing the URL.

**Travel time:** client-side per your plan (Distance Matrix, origin = device,
destination = the public approximate coords pre-booking / exact post-booking).
Existing places with parseable pasted URLs were backfilled with coordinates.

## Addendum: standalone amenity sections — added 2026-07-13

Attribute groups now carry `is_standalone` (boolean) in BOTH payloads the app
reads: the wizard catalog (`GET /api/attribute-groups`, on each group) and the
place detail (`attributes[].attribute.group.is_standalone`). Rendering rule:
groups with `is_standalone: true` are rendered as their OWN section (own
header, outside the general amenities list); everything else stays in the
amenities list as today. Admin controls the flag per group.
