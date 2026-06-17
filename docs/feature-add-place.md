# Add Place — Feature Spec (PRD + Technical)

Audience: mobile engineering team. This doc covers both **what** the feature is and **how the backend currently exposes it**, so mobile can build the iOS/Android equivalent.

The feature currently ships on the web (Blade wizard). The web implementation is the reference behaviour — same data model, same state machine, same review workflow.

---

## 1. Product overview

Hosts list a property through a guided wizard. The wizard auto-saves every step so users can leave and resume. The final submit puts the place into the admin review queue.

There are **two posters** of the same wizard:

| Poster | Where they enter | Who owns the resulting place |
| ------ | ---------------- | ---------------------------- |
| **Host** (self-service) | The wizard with their own login | Themselves |
| **Admin / sales** (onboarding) | The same wizard, with an extra "Attach to host phone" field at the top | The phone-resolved user (created on the fly if missing) |

Mobile should ship both. The admin/sales surface is critical — sales staff onboard hosts by phone over the call, so the input must be friction-free.

### 1.1 Why admin onboarding exists
Sales staff close hosts over phone calls and need to create the listing for them on the spot. The host might:
- Already have a Calm account → place attaches to that user
- Not have one yet → a minimal user row is created (`role=user`, only `phone` filled) and the place attaches to that. The host completes their profile next time they OTP-log-in.

### 1.2 Out of scope (won't ship on first pass)
- Editing the place after submit — admin handles that via web admin UI for now
- Approving/rejecting from mobile — admin review stays on web
- Mobile-side "my listings" management view (we'll spec it next)

---

## 2. State machine

```
                           ┌─────────────────────────────┐
                           ▼                             │
   ┌──────────┐   submit  ┌────────────────┐   reject    │
   │  Draft   │ ────────► │ PendingReview  │ ──────────► │
   └──────────┘           └────────────────┘             │
        ▲                          │                     │
        │                          │ approve             │
   resume (host                    ▼                     │
   or admin)                ┌──────────┐                 │
        │                   │ Approved │                 │
        │                   └──────────┘                 │
        │                          │                     │
        │                   admin flips status           │
        │                          ▼                     │
        │                   ┌──────────┐                 │
        └────── reject ──── │  Active  │                 │
                            └──────────┘                 │
                                                         │
                                              ┌──────────┴─┐
                                              │  Rejected  │── resumable
                                              └────────────┘   (re-edit + resubmit)
```

Two orthogonal flags:
- `review_status` — `draft`, `pending_review`, `approved`, `rejected`
- `status` — `active`, `inactive`

A place is **publicly visible** only when `status=active AND review_status=approved`. Until then it's invisible to guests (mobile + web).

Fresh wizard saves create rows with `review_status=draft`, `status=inactive`. Final submit promotes to `review_status=pending_review`.

---

## 3. Data model

```
places
├── id (uuid)
├── host_user_id      → users.id     (whoever owns the listing)
├── place_type_id     → place_types.id
├── city_area_id      → city_areas.id
├── title
├── description
├── price             (base nightly, SAR integer)
├── price_sunday … price_saturday  (per-day overrides; 0 = fall back to base)
├── check_in_time     ("15:00")
├── check_out_time    ("12:00")
├── max_guests        (1..50, required on submit)
├── rules             (free text, optional)
├── status            (active | inactive)
├── review_status     (draft | pending_review | approved | rejected)
├── rejection_reason  (admin's note; visible to host on resume)
├── reviewed_at
├── last_step         (1..N — used to land the resumed wizard at the right step)
└── timestamps

place_attributes  (the facilities/features picker)
├── id, place_id, attribute_id, value, description
└── unique(place_id, attribute_id)

place_photos
├── id, place_id, place_attribute_id (nullable), path, sort_order, featured_order (nullable)
└── sort_order = gallery order (sections then within); featured_order = showcase slot
    (0 = cover, null = not shown outside, ≤10 featured)
```

The wizard sends attribute picks + photo paths as part of the same payload — the backend writes them in one transaction.

---

## 4. Mobile UX (recommended)

Replicate the web's step structure. Steps 1–9 (exact step boundaries are flexible — the backend doesn't enforce them, it just persists what's submitted):

1. **Place type** — single pick from `GET /api/place-types`. `place_type_id` required from this step onward.
2. **Basics** — title + description.
3. **City** — pick a city.
4. **Area** — pick a `city_area_id` inside that city.
5. **Base price + per-day overrides** — `price` plus optional per-day inputs.
6. **Attributes / facilities** — multi-select from `GET /api/attributes` (TBD — exposed if not already). Each picked attribute can carry a `value` (e.g. "2 bedrooms") and a `description`.
7. **Attribute-attached photos** — some attributes prompt photos (e.g. "pool" → photo of the pool). Uploaded via presigned PUT (§5.3).
8. **General photos** — non-attribute photos for the listing. Same upload flow. One is flagged as cover.
9. **Times + house rules** — `check_in_time`, `check_out_time`, `rules`. Final submit.

**Admin-only field**: an extra "Attach to host phone" input visible only when the logged-in user has `role=admin`. Place it above step 1. Required at final submit; optional during drafts (admins can save a partial draft before typing the phone).

### 4.1 Auto-save
After every step advance, fire the draft endpoint (§5.1). Server returns the `id` — store it locally and send it back as `draft_id` on every subsequent save.

### 4.2 Resume
- If the user leaves and comes back, mobile decides whether to surface "Continue draft":
  - Hit `GET /api/my-places` (TBD endpoint — see §6) and look for `review_status in ['draft', 'rejected']`
  - Tapping resumes the wizard with `draft_id` set + all fields hydrated from the response
- For a Rejected place: also surface the `rejection_reason` at the top so the host knows what to fix

### 4.3 Validation that should happen client-side
- Phone format `^5\d{8}$` (Saudi 9-digit national)
- OTP / cover image presence isn't enforced on submit by the backend — but the UI should encourage at least one photo + a cover before letting the user tap submit

---

## 5. API contract

All endpoints return the standard envelope where applicable:
```json
{ "status": 200, "message": "...", "data": ... }
```

The web wizard routes currently sit under `/host-register/...` (a leftover from when they were Blade-only). The JSON shapes below match what the backend accepts today — mobile can call these directly, OR backend will expose `/api/...` equivalents (call it `phase 1.5`).

### 5.1 Save draft

```
POST  /host-register/draft       (call this after every step advance)
Auth: Bearer <token>
Content-Type: application/json
```

**Body** (every key optional except `place_type_id`):

```json
{
  "draft_id":         "019eb8...",          // omit on first save; sent back on every subsequent one
  "host_phone":       "512345678",          // admin only; ignored for regular hosts
  "place_type_id":    "019eb8...",          // REQUIRED — the only required field on drafts
  "title":            "Cozy chalet",
  "description":      "Free text",
  "city_area_id":     "019eb8...",
  "price":            500,
  "price_sunday":     0,                    // 0 means "use the base price"
  "price_monday":     0,
  "price_tuesday":    0,
  "price_wednesday":  0,
  "price_thursday":   600,
  "price_friday":     750,
  "price_saturday":   750,
  "max_guests":       4,
  "check_in_time":    "15:00",
  "check_out_time":   "12:00",
  "rules":            "No smoking. No parties.",
  "last_step":        4,                    // for resume — UI step number the host is on

  "attributes": [                           // omit until the host has touched the attribute step
    { "attribute_id": "uuid", "value": "2", "description": "two bedrooms" }
  ],

  "attribute_image_paths": {                // omit until photos uploaded
    "<attribute_id>": ["places/uploads/abc.jpg", "places/uploads/def.jpg"]
  },
  "extra_image_paths":     ["places/uploads/ghi.jpg"],
  "featured":              ["extra_images.0"]  // ordered photo-keys, first = cover; see §5.3
}
```

**Response**:

```json
{ "id": "019eb8...", "review_status": "draft" }
```

The `id` is the draft's place row. Persist it client-side and ship it as `draft_id` on every subsequent draft save AND on the final submit, so the server promotes the same row instead of creating a duplicate.

### 5.2 Final submit

```
POST  /host-register                       (currently form-encoded; backend will add JSON variant)
Auth: Bearer <token>
```

Same body shape as §5.1, but these become **required**:
- `title`
- `place_type_id`
- `city_area_id`
- `price`
- `max_guests`
- `check_in_time`
- `check_out_time`
- `host_phone` (admin only)

**Behaviour**: if `draft_id` matches an existing Draft (or Rejected) row owned by the resolved host, that row is promoted to `pending_review`. Otherwise a fresh place row is created. Attributes and photos are synced (replace-all semantics).

**Current response**: HTTP redirect to `/my-places` (web flow). **Backend TODO**: ship a JSON variant under `/api/places` that returns the envelope `{ status, message, data: { ...PlaceResource } }` so mobile doesn't have to follow redirects.

### 5.3 Photo upload (presigned S3 PUT)

```
POST  /host-register/presign
Auth: Bearer <token>
Content-Type: application/json
```

**Body**:
```json
{ "filename": "IMG_2034.jpg", "mime": "image/jpeg" }
```

**Response**:
```json
{
  "put_url":     "https://calm.fra1.digitaloceanspaces.com/places/uploads/abc.jpg?X-Amz-...",
  "path":        "places/uploads/abc.jpg",
  "public_url":  "https://calm.fra1.cdn.digitaloceanspaces.com/places/uploads/abc.jpg",
  "mime":        "image/jpeg"
}
```

**Then**, from the client:
```
PUT  <put_url>
Content-Type: image/jpeg
[bytes]
```

No Authorization header on the PUT — the presigned URL embeds its own signature. The URL is valid for 15 minutes.

After upload succeeds, the client now holds the `path` (e.g. `places/uploads/abc.jpg`). Pack that into the wizard's draft/submit body as:

| Where the photo belongs | Where to put the path | Photo-key reference |
| ----------------------- | --------------------- | ------------------- |
| Attached to attribute `<id>` (e.g. pool photo) | `attribute_image_paths[<attribute_id>][<index>]` | `"attribute_images.<attribute_id>.<index>"` |
| General gallery (no attribute) | `extra_image_paths[<index>]` | `"extra_images.<index>"` |

**Ordering.** Photo order follows the order the paths appear in the payload: attribute groups are stored in the order their keys are sent, and photos within a group in array-index order. That order becomes each photo's `sort_order` (sections then within-section), which drives the grouped gallery — so send the groups/photos in the order the host arranged them.

**Featured ("shown outside") showcase.** The `featured` field is an **ordered array** of the photo-key strings (right column above) the host picked to appear on the place page — at most **10**, and **the first is the cover**. The backend matches each key against the synced paths and writes its position into `place_photos.featured_order` (`0` = cover, `1, 2, …`; `null` for non-featured). If `featured` is empty, the backend auto-features the first gallery photo so there is always a cover.

```jsonc
"featured": [
  "extra_images.0",                 // index 0 → featured_order 0 → cover
  "attribute_images.<attr_id>.1"    // index 1 → featured_order 1
]
```

### 5.4 List the user's places (TODO)

There is no JSON endpoint yet for "my places". Currently `GET /my-places` returns Blade.

**Backend TODO**:
```
GET  /api/my-places       (auth required)
```
Returns the canonical `PlaceResource` shape from [docs/api-home.md](api-home.md) for every place owned by the authed user, regardless of status (drafts + rejected + approved). Mobile needs this for both the "continue draft" UI and the eventual "my listings" tab.

### 5.5 Resume a draft (TODO)

Currently the web hydrates the wizard by reading the draft directly from the `Place` model with relationships. For mobile we need:

```
GET  /api/places/{place_id}/draft       (auth: must own this place OR be admin)
```

Returns the full Place + attributes + photos in a shape that maps to the wizard's local state. Backend will design this when mobile starts integrating.

---

## 6. Validation cheat sheet

| Field | Rule | Notes |
| ----- | ---- | ----- |
| `host_phone` | `^5\d{8}$` | Admin only. Strip leading 0 and `+966` client-side before sending. |
| `title` | string, max 255, required on submit | |
| `description` | string, max 10000, optional | |
| `place_type_id` | uuid, exists in `place_types`, required from step 1 | |
| `city_area_id` | uuid, exists in `city_areas`, required on submit | |
| `price` | integer ≥ 0, required on submit | SAR. |
| `price_<day>` | integer ≥ 0, optional | 0 means "fall back to `price` for that day". |
| `check_in_time` / `check_out_time` | string `HH:MM`, max 8 chars, required on submit | Wire format always 24-hour. |
| `max_guests` | integer 1..50, required on submit | Sleeping capacity. |
| `rules` | string, max 10000, optional | |
| `attributes[].attribute_id` | uuid, exists in `attributes` | |
| `attributes[].value` | string, max 255, optional | |
| `attributes[].description` | string, max 1000, optional | |
| `attribute_image_paths[].*` | string, max 500 | S3 path returned from presign. |
| `extra_image_paths.*` | string, max 500 | Same. |
| `featured` | array, max 10 items | Ordered photo-keys (§5.3) for the place-page showcase; `featured[0]` is the cover. Each item: string, max 255. Empty → backend auto-features the first photo. |

422 responses carry `data.errors.<field>` for each failed rule.

---

## 7. Edge cases worth thinking about

1. **Admin types their own phone in `host_phone`.** Allowed by the backend (creates a place for themselves). Mobile UI should probably guard against it with a confirmation dialog, but server-side it's fine.
2. **Admin saves a draft, walks away, comes back the next day with a phone in mind.** The draft was attached to whoever was resolved on the first save — if they typed the phone on save #1, the draft already belongs to that host. If they didn't, it belongs to the admin. Either way, the final submit re-resolves via the form's `host_phone` and (re-)attaches.
3. **Existing Approved place — host re-edits.** Out of scope for the wizard. Editing approved places is admin-only via web for now.
4. **Photo upload fails mid-PUT.** The presign is single-use but the path is still reserved (S3 won't have the bytes, so the listing will reference a missing object). Mobile should retry the PUT (same URL works as long as it's within 15 minutes), or call presign again to get a fresh URL.
5. **User submits with zero photos.** Backend doesn't enforce a minimum. The UI should encourage at least one + a cover, but the place will still go to PendingReview without photos. Admin reviewers reject these in practice.
6. **Featured set empty but photos exist.** The backend auto-features the first gallery photo (`featured_order = 0`), so there's always a cover and `cover_photo_url` resolves. The UI should still let the host curate the showcase (pick up to 10, first = cover), but it's no longer required to submit.

---

## 8. Backend TODO (gating mobile launch)

These don't exist yet and mobile will block on them:

- [ ] `POST /api/places` — JSON variant of the web's `POST /host-register` (returns the envelope with a `PlaceResource`, not a redirect).
- [ ] `POST /api/places/draft` — JSON variant of `/host-register/draft`. Same body, same response.
- [ ] `POST /api/places/presign` — JSON variant of `/host-register/presign`.
- [ ] `GET /api/my-places` — return the canonical `PlaceResource[]` for the authed user (all statuses).
- [ ] `GET /api/places/{id}/draft` — hydrate the wizard for resume.
- [ ] (Optional) `GET /api/attributes` — for the attributes step. May already be there under a different name; verify with backend.

The above keep the JSON contract identical to the existing web routes — backend just wraps the response in `ApiResponse::success(...)`.

---

## 9. References

- The web wizard implementation: [resources/views/host/places/create.blade.php](../resources/views/host/places/create.blade.php) — Alpine state machine is the source of truth for step ordering and draft-save cadence.
- Service code that the API endpoints will all call: [app/Services/Place/PlaceService.php](../app/Services/Place/PlaceService.php) (`createForHost`, `saveDraftForHost`, `upsertPlace`, `syncAttributes`, `syncPhotos`).
- Admin-only host resolution: [app/Http/Controllers/Host/PlacesController.php](../app/Http/Controllers/Host/PlacesController.php) (`resolveHost()`).
- Auth + token shape: [docs/api-auth.md](api-auth.md).
- Canonical Place response shape that listing endpoints return: [docs/api-home.md](api-home.md).
