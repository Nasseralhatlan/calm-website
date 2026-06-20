# Reviews — Frontend Integration Guide

Guest reviews on completed bookings, with admin moderation. All API responses use the standard
envelope `{ status, message, data }`. Authenticated endpoints require the `Authorization: Bearer <token>` header.

---

## Concept & states

- A guest can review a place **only after a `completed` booking** on it.
- **One review per guest per place** — a 2nd completed booking on the same place does NOT unlock another.
- Status flow: **`under_review`** (on submit) → admin sets **`published`** or **`blocked`**.
- The guest can **delete** their own review while it's `under_review` or `published` (then re-submit).
  A **`blocked`** review is locked — can't delete, can't re-submit.
- **Public visibility:** the place page + rating count **`published` only**. The guest sees their own
  review (any status) **from their booking**, not the public place page.
- Reviewer is shown by **first name only**.

---

## Endpoints

### 1. Submit a review
```
POST /api/bookings/{bookingId}/reviews        (auth)
Body: { "rate": 1..5 (required int), "comment": "..." (optional, max 1000) }
```
**201** → `data` = the Review object (`status: "under_review"`).

| Status | When | message |
|--------|------|---------|
| 422 | validation (rate missing / not 1–5) | "Validation failed." (+ `data.errors.rate`) |
| 422 | booking not `completed` | "You can only review a completed stay." |
| 422 | place already reviewed by this guest | "You have already reviewed this place." |
| 403 | booking isn't the caller's | — |

### 2. Delete own review
```
DELETE /api/reviews/{reviewId}                (auth)
```
**200** → `{ "message": "Review deleted." }` (soft delete; frees the place to be reviewed again).

- **403** if not the owner **or** the review is `blocked`.
- **404** if already deleted.

### 3. Host's reviews (published only)
```
GET /api/host/reviews                          (auth)
```
**200** →
```jsonc
{ "data": {
    "items": [ Review (incl. "place" block) ],
    "pagination": { "page", "per_page", "total", "last_page", "has_more" }
} }
```
Only `published` reviews across the host's places, newest first.

### 4. Bookings carry review state — `GET /api/bookings`
Each booking item gains:
```jsonc
"review": {                      // the guest's review for THIS booking's place, or null
  "id": "...", "rate": 5, "comment": "...", "status": "under_review", "created_at": "..."
},
"can_review": true               // true only when booking is completed AND review === null
```
Same place across two of the guest's bookings → both show the same `review`.
**These two fields appear on `GET /api/bookings` only** (not on create/cancel/payment-status responses).

### 5. Place detail — `GET /api/places/{place}`
- `reviews_recent`: up to 10 **published** reviews (Review object, `place` omitted).
- `rating`: `{ "avg": 4.7|null, "count": 9 }` — **published only**.

---

## Data shapes

**Review object**
```jsonc
{
  "id": "019ee0…",
  "rate": 5,
  "comment": "Loved it",
  "status": "under_review | published | blocked",
  "reviewer_name": "Nasser",          // first name only; may be null
  "place": {                           // ONLY on GET /api/host/reviews (whenLoaded)
    "id": "...", "title": "Chalet C5", "cover_photo_url": "https://…"
  },
  "created_at": "2026-06-20T10:00:00+00:00"
}
```
- On the **public** place page, `status` is always `published` and `place` is omitted.
- On the **booking** `review` field, only `id / rate / comment / status / created_at` are included.

---

## Error envelope (handle both)

- **Field validation (422):**
  `{ "status":422, "message":"Validation failed.", "data":{ "errors":{ "rate":["..."] } } }`
- **Business rule (422 / 403):**
  `{ "status":422, "message":"You have already reviewed this place.", "data":null }`

Read `data.errors` first; if absent, show `message`.

---

## UX wiring

**My bookings list** (the guest's hub for reviews):
- `can_review === true` → show **"Leave a review"** → rate(1–5) + optional comment → `POST`.
- `review !== null` → show it with a status chip:
  - `under_review` → "Pending review" + **Delete**.
  - `published` → "Published" + **Delete**.
  - `blocked` → "Removed" (muted) — **no delete, no re-submit** (locked).
- After submit/delete, re-fetch `/api/bookings` (or optimistically update `review` / `can_review`).

**Place detail page:**
- Render `rating.avg` (stars) + `rating.count`.
- List `reviews_recent` (stars, `reviewer_name`, date, comment). Already published — no status handling.

**Host app:**
- "Reviews on my places" → `GET /api/host/reviews`, paginate via `pagination.has_more`. Each item
  shows `place`, `reviewer_name`, stars, comment. Published only — hosts never see pending/blocked.

**Reporting:** out-of-band for now — show a "Contact support to report a review" hint (support
contact: `/support` page, or `GET /api/settings` → `support_phone` / `support_email`). Admin blocks
it; once blocked it drops out of public/host lists and the rating.

---

## Gotchas

1. **Pending reviews aren't public.** Right after submit, the guest sees the review only via their
   booking, not on the place page. Say so in the submit toast ("Your review is pending approval").
2. **Rating reflects published only** — a new review won't move the average until an admin publishes it.
3. **Re-submission creates a new review** (old one soft-deleted); the `review.id` changes — always
   use the latest from `/api/bookings`.
4. **`review` / `can_review` are only on `GET /api/bookings`.**
5. `comment` can be `null`/empty (rating-only reviews are valid).

---

## Sample requests / responses (Postman)

### Submit — success
```http
POST /api/bookings/019ee0.../reviews
Authorization: Bearer <token>
Content-Type: application/json

{ "rate": 5, "comment": "Loved it" }
```
```jsonc
// 201
{
  "status": 201,
  "message": "Review submitted — pending review.",
  "data": {
    "id": "019ee1...", "rate": 5, "comment": "Loved it",
    "status": "under_review", "reviewer_name": "Nasser",
    "created_at": "2026-06-20T10:00:00+00:00"
  }
}
```

### Submit — already reviewed
```jsonc
// 422
{ "status": 422, "message": "You have already reviewed this place.", "data": null }
```

### Submit — not completed
```jsonc
// 422
{ "status": 422, "message": "You can only review a completed stay.", "data": null }
```

### Submit — validation
```jsonc
// 422
{ "status": 422, "message": "Validation failed.",
  "data": { "errors": { "rate": ["The rate field must be between 1 and 5."] } } }
```

### Delete — success
```http
DELETE /api/reviews/019ee1...
Authorization: Bearer <token>
```
```jsonc
// 200
{ "status": 200, "message": "Review deleted.", "data": null }
```

### Delete — blocked review
```jsonc
// 403
{ "status": 403, "message": "A blocked review cannot be deleted.", "data": null }
```

### Bookings — completed, not yet reviewed
```jsonc
// GET /api/bookings  → data.items[0]
{ "id": "...", "status": "completed", "review": null, "can_review": true, "...": "..." }
```

### Bookings — after reviewing
```jsonc
{ "id": "...", "status": "completed", "can_review": false,
  "review": { "id": "019ee1...", "rate": 5, "comment": "Loved it",
              "status": "under_review", "created_at": "2026-06-20T10:00:00+00:00" } }
```

### Host reviews
```jsonc
// GET /api/host/reviews
{ "status": 200, "message": "Host reviews fetched.",
  "data": {
    "items": [
      { "id": "...", "rate": 5, "comment": "Loved it", "status": "published",
        "reviewer_name": "Nasser",
        "place": { "id": "...", "title": "Chalet C5", "cover_photo_url": "https://…" },
        "created_at": "..." }
    ],
    "pagination": { "page": 1, "per_page": 20, "total": 1, "last_page": 1, "has_more": false }
  } }
```
