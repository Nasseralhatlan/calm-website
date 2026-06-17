# Calm Home-Screen APIs

Endpoints powering the mobile app's home screen. All responses share the same envelope; every endpoint that returns a list of places returns the exact same `Place` object shape — render with one card component.

---

## Base

```
Base URL:   https://api.calmapp.co   (replace with your env URL)
Content:    application/json (request + response)
Locale:     send Accept-Language: ar or en  (responses include both ar/en strings — you choose which to display)
```

## Auth

JWT bearer. Obtain via `POST /api/auth/otp/verify` (existing endpoint). Attach to authed calls:

```
Authorization: Bearer <token>
```

Endpoints marked **public (auth-aware)** work without a token AND with one. Anonymous → `is_liked` is always `false`. Authed → `is_liked` reflects that viewer's likes.

## Response envelope

Every response — success or error — looks like:

```json
{
  "status": 200,
  "message": "OK",
  "data": { ... }    // object, array, or null
}
```

Treat `status` as the source of truth (matches the HTTP status code). On errors, `data` may carry a `{ "errors": { field: [msg] } }` object for validation failures.

---

## 0. List countries

```
GET /api/countries
Auth: public
```

Active countries. Use for the dial-code dropdown in the login flow and any country picker.

**Response**

```json
{
  "status": 200,
  "message": "Countries fetched.",
  "data": [
    {
      "id": "019eb8...",
      "country_code": "SA",
      "dial_code": "+966",
      "name_en": "Saudi Arabia",
      "name_ar": "السعودية",
      "avatar": "🇸🇦"
    }
  ]
}
```

---

## 1. List cities

```
GET /api/cities
Auth: public
```

Active cities. Use to render the city picker / city filter chips.

**Response**

```json
{
  "status": 200,
  "message": "Cities fetched.",
  "data": [
    {
      "id": "019eb8...",
      "name_en": "Riyadh",
      "name_ar": "الرياض",
      "avatar": "🌆",
      "country_id": "019eb8..."
    }
  ]
}
```

---

## 2. List place types

```
GET /api/place-types
Auth: public
```

Active place types — the icon grid above the listings ("Chalet", "Apartment", "Villa", etc.).

**Response**

```json
{
  "status": 200,
  "message": "Place types fetched.",
  "data": [
    {
      "id": "019eb8...",
      "name_en": "Chalet",
      "name_ar": "شاليه",
      "icon": "🏡"
    }
  ]
}
```

---

## 3. Curated lists (home-screen sections)

```
GET /api/place-lists
Auth: public (auth-aware)
```

Admin-curated lists. Each entry is one home-screen section row: section metadata + ordered member places already hydrated with the canonical `Place` shape.

Lists with zero currently-visible places are omitted (you won't get an empty section row).

**Response**

```json
{
  "status": 200,
  "message": "Curated lists fetched.",
  "data": [
    {
      "id": "019eb8...",
      "name_en": "Top Picks",
      "name_ar": "الأفضل",
      "description_en": null,
      "description_ar": null,
      "icon": "⭐",
      "sort_order": 0,
      "places": [
        /* Place objects — see "Canonical Place shape" below */
      ]
    }
  ]
}
```

---

## 3b. Place detail

```
GET /api/places/{place_id}
Auth: public (auth-aware)
```

Full data for a single place's detail screen. Returns the canonical `Place` shape (same as list endpoints) spread at the top level, plus four detail-only blocks: `photo_groups`, `attributes`, `reviews_recent`, `host`.

404 if the place isn't currently visible (drafts, pending review, rejected, or inactive — all 404 to avoid leaking work-in-progress via direct URL).

**Response**

```json
{
  "status": 200,
  "message": "Place fetched.",
  "data": {
    /* every field from the canonical Place shape (see end of doc) */
    "id": "019eb8...",
    "title": "...",
    "photos": [ ... ],
    "rating": { "avg": 4.5, "count": 12 },
    "is_liked": true,
    /* ... */

    /* Detail-only extras: */

    /* Grouped "view images" gallery — already grouped by amenity and ordered
       by min sort_order. `attribute` is null for the general group. */
    "photo_groups": [
      {
        "attribute_id": "019ebcf9-...",
        "attribute": { "id": "019ebcf9-...", "name_en": "Bedroom", "name_ar": "غرفة نوم", "icon": "🛏️" },
        "min_sort_order": 0,
        "photos": [
          { "id": "019ed2...", "url": "https://cdn.../b1.jpg", "sort_order": 0, "featured_order": null }
        ]
      },
      {
        "attribute_id": null,
        "attribute": null,
        "min_sort_order": 20,
        "photos": [
          { "id": "019ed2...", "url": "https://cdn.../g1.jpg", "sort_order": 20, "featured_order": 5 }
        ]
      }
    ],
    "attributes": [
      {
        "id": "019eb8...",
        "value": "1",
        "description": "Fast fiber.",
        "attribute": {
          "id": "019eb8...",
          "name_en": "WiFi",
          "name_ar": "واي فاي",
          "icon": "📶",
          "type": "boolean",
          "group": { "id": "...", "name_en": "Indoor", "name_ar": "داخلي" }
        }
      }
    ],
    "reviews_recent": [
      { "id": "...", "rate": 5, "comment": "Great stay.", "created_at": "2026-06-10T12:00:00+00:00" }
    ],
    "host": {
      "id": "019eb8...",
      "name": "Ahmed",
      "joined_at": "2026-04-12T10:30:00+00:00"
    }
  }
}
```

### Field notes — detail extras

| Field             | Type   | Notes |
| ----------------- | ------ | ----- |
| `photo_groups`    | array  | The grouped "view images" gallery, render-ready: photos grouped by amenity, **ordered within** a group by `sort_order`, and the **groups ordered** by each group's earliest `sort_order` (so the host's section arrangement is honoured). Each entry: `{ attribute_id, attribute, min_sort_order, photos[] }`; `attribute` is `null` for the general group. Full breakdown + the three sort levels in [api-place-photos.md](./api-place-photos.md). |
| `attributes`      | array  | Every facility/amenity the host picked. Each carries the host-typed `value` + optional `description` and the full attribute definition (icon, name, type, group). Use `attribute.group.name_en` to render section headings ("Indoor", "Outdoor", "Safety"). |
| `attributes[].attribute.type` | string | One of the AttributeType enum cases (`boolean`, `count`, `select`, etc.) — drives how the value renders ("Yes/No", "× 2", chosen option). |
| `reviews_recent`  | array  | At most **10** most-recent reviews. Anonymized — no reviewer identity exposed. Total review count is in `rating.count` at the top level. |
| `host`            | object | Public host profile. **Only** `id`, `name`, `joined_at` — phone, email, age, etc. never leave the server. |

### Why no host phone?

By design. The host's identity is shielded from public clients. When bookings ship, the booking flow will surface contact details to the booked guest only.

---

## 4. Most-liked places

```
GET /api/places/most-liked
Auth: public (auth-aware)
```

Top 20 Active+Approved places by like count (tie-break: avg rating, then recency). Returns the canonical `Place` shape.

**Response**

```json
{
  "status": 200,
  "message": "Most-liked places fetched.",
  "data": [
    /* Place objects — see below */
  ]
}
```

---

## 4b. Search places

```
GET /api/places/search
Auth: public (auth-aware)
```

The main discovery endpoint. **`city_id` is required**; every other parameter is optional and **narrows** the results (AND). Returns the canonical `Place` card, **paginated**, with `is_liked` reflecting the viewer when a token is sent.

| Param | Filter |
|-------|--------|
| `city_id` **(required)** | places in this city |
| `city_area_id` | narrow to one area |
| `q` | text match on title/description |
| `place_type_ids[]` | one or more place types (match any) |
| `price_min` / `price_max` | base nightly price range (SAR) |
| `guests` | capacity ≥ this many guests |
| `amenities[]` | has **all** of these amenity ids (`attribute_id`s, e.g. from `/place-types`-style catalogs) |
| `check_in` & `check_out` | `YYYY-MM-DD` (both together) — only places **free** for that range |
| `sort` | `most_liked` (default) · `price_asc` · `price_desc` |
| `per_page` / `page` | pagination (default 20, max 50) |

**Response**

```json
{
  "status": 200,
  "message": "Search results fetched.",
  "data": {
    "items": [ /* Place cards — see "Canonical Place shape" */ ],
    "pagination": { "page": 1, "per_page": 20, "total": 42, "last_page": 3, "has_more": true }
  }
}
```

**Example**

```
GET /api/places/search?city_id=…&place_type_ids[]=…&price_max=2000&guests=4&amenities[]=…&check_in=2026-07-01&check_out=2026-07-03&sort=price_asc
```

**Errors:** `422` when `city_id` is missing or any filter is malformed (e.g. unknown id, bad date, `check_in` in the past, only one of `check_in`/`check_out`).

---

## 4c. Filter options (for the filters page)

```
GET /api/places/filters?city_id=…
Auth: public
```

Returns the **available** filters for a city — computed from the visible places that actually exist there, so the UI only shows options that will return results. Feeds the search filters page (the ids here are exactly what `/places/search` accepts). `city_id` is **required**.

**Response**

```json
{
  "status": 200,
  "message": "Filters fetched.",
  "data": {
    "city_id": "9b1f…",
    "currency": "SAR",
    "price":  { "min": 500, "max": 5000 },     // base nightly price range (SAR) → slider bounds
    "guests": { "min": 1,   "max": 20 },        // max_guests range
    "areas": [                                   // areas that have places
      { "id": "…", "name_en": "Al Olaya", "name_ar": "العليا", "places_count": 12 }
    ],
    "place_types": [                             // types in use
      { "id": "…", "name_en": "Chalet", "name_ar": "شاليه", "icon": "🏖️", "places_count": 30 }
    ],
    "amenities": [                               // amenities in use, grouped
      {
        "group": { "id": "…", "name_en": "Facilities", "name_ar": "مرافق" },
        "items": [
          { "id": "…", "name_en": "Pool", "name_ar": "مسبح", "icon": "🏊", "places_count": 8 },
          { "id": "…", "name_en": "WiFi", "name_ar": "واي فاي", "icon": "📶", "places_count": 22 }
        ]
      }
    ]
  }
}
```

| Field | Use |
|-------|-----|
| `price` / `guests` | `min`/`max` → range-slider bounds. |
| `areas[]` | `city_area_id` chips (with counts). |
| `place_types[]` | type chips → `place_type_ids[]`. |
| `amenities[]` | amenity chips, grouped by category; `items[].id` → `amenities[]`. |
| `*.places_count` | how many places match — show as "(12)" or to hide empty options. |

A city with no places returns empty arrays and `0` ranges. **Errors:** `422` if `city_id` is missing/unknown.

---

## 5. Like a place

```
POST /api/places/{place_id}/like
Auth: required
```

Idempotent — re-liking returns the same success body, no duplicate row.

**Response**

```json
{
  "status": 200,
  "message": "Place liked.",
  "data": { "is_liked": true }
}
```

**Errors**

| Status | Meaning                              |
| ------ | ------------------------------------ |
| 401    | Missing / invalid Bearer token       |
| 404    | `place_id` not found                 |

---

## 6. Unlike a place

```
DELETE /api/places/{place_id}/like
Auth: required
```

Idempotent — unliking something not liked still returns success.

**Response**

```json
{
  "status": 200,
  "message": "Place unliked.",
  "data": { "is_liked": false }
}
```

> Liking a place that isn't publicly visible (draft / pending / rejected / inactive) returns `404`. Unliking is always allowed (so a user can remove a place that later went private).

---

## 7. My favorites (the viewer's liked places)

```
GET /api/favorites?per_page=20&page=1
Auth: required
```

The authenticated user's own liked places, **newest-liked first**, **paginated**. Each item is the canonical `Place` shape (so render with the same card) and always has `is_liked: true`. Only currently-visible places are returned. `per_page` defaults to 20 (max 50).

**Response**

```json
{
  "status": 200,
  "message": "Liked places fetched.",
  "data": {
    "items": [ /* Place cards — see "Canonical Place shape" */ ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 37,
      "last_page": 2,
      "has_more": true
    }
  }
}
```

**Frontend:** this powers the "Favorites" tab. Infinite-scroll by requesting `?page=` until `pagination.has_more` is `false`. A heart tapped off here (`DELETE …/like`) removes the place from this feed on the next fetch.

---

## Canonical Place shape

Returned identically by **every** place-list endpoint (`/api/places/most-liked`, `places[]` inside `/api/place-lists`, and any future place endpoint). Render with one card component.

```json
{
  "id": "019eb8...",
  "title": "Cozy chalet by the lake",
  "description": "Long-form host description...",
  "price": 500,
  "per_day_prices": {
    "sunday":    0,
    "monday":    0,
    "tuesday":   0,
    "wednesday": 0,
    "thursday": 600,
    "friday":   750,
    "saturday": 750
  },
  "check_in_time":  "15:00",
  "check_out_time": "12:00",
  "max_guests": 4,
  "rules": null,
  "cover_photo_url": "https://cdn.../cover.jpg",
  "photos": [
    { "id": "019eb8...", "url": "https://cdn.../pool-1.jpg", "attribute_id": "019ec1...", "sort_order": 0, "featured_order": null },
    { "id": "019eb9...", "url": "https://cdn.../pool-2.jpg", "attribute_id": "019ec1...", "sort_order": 1, "featured_order": 1 },
    { "id": "019eba...", "url": "https://cdn.../cover.jpg",  "attribute_id": null,        "sort_order": 2, "featured_order": 0 }
  ],
  "featured_photos": [
    { "id": "019eba...", "url": "https://cdn.../cover.jpg",  "attribute_id": null },
    { "id": "019eb9...", "url": "https://cdn.../pool-2.jpg", "attribute_id": "019ec1..." }
  ],
  "type": {
    "id": "019eb8...",
    "name_en": "Chalet",
    "name_ar": "شاليه",
    "icon": "🏡"
  },
  "city": {
    "id": "019eb8...",
    "name_en": "Riyadh",
    "name_ar": "الرياض",
    "avatar": "🌆",
    "country_id": "019eb8..."
  },
  "city_area": {
    "id": "019eb8...",
    "name_en": "Diriyah",
    "name_ar": "الدرعية"
  },
  "likes_count": 12,
  "rating": {
    "avg": 4.5,
    "count": 8
  },
  "is_liked": true,
  "created_at": "2026-06-12T18:30:00+00:00"
}
```

### Field notes

| Field            | Type           | Notes |
| ---------------- | -------------- | ----- |
| `id`             | UUID string    | Use as React key + for `/places/{id}/like`. |
| `price`          | integer        | Base nightly price in SAR. |
| `per_day_prices` | object         | Per-day overrides. **`0` means "fall back to `price`"** — do not show "0 SAR" in the UI; render the base price for that day. |
| `cover_photo_url`| string \| null | Convenience: the cover photo's URL — the first featured photo (`featured_order === 0`), falling back to the first gallery photo when the host hasn't featured any. Null when there are no photos — render a placeholder (use `type.icon` as fallback). |
| `photos`         | array          | Full gallery, ordered by `sort_order`. Each entry: `{id, url, attribute_id, sort_order, featured_order}`. `attribute_id` is non-null when the host uploaded the photo against a specific facility (e.g. pool, gym) — match it against the place's `attributes[].attribute.id` to group the photo under that amenity. Null means a general gallery photo. `sort_order` encodes **both** the amenity-section order and the order within a section; `featured_order` is the photo's slot in the place-page showcase (`0` = cover, `null` = not shown outside). See [api-place-photos.md](./api-place-photos.md). |
| `featured_photos`| array          | The curated "shown outside" showcase for the place page, **already ordered** (`[0]` is the cover), at most 10. Each entry: `{id, url, attribute_id}`. Empty when the host hasn't featured any. |
| `rating.avg`     | number \| null | `null` when the place has no reviews yet. |
| `rating.count`   | integer        | `0` when no reviews. |
| `likes_count`    | integer        | Total likes from all users. |
| `is_liked`       | boolean        | True iff the **authed viewer** has liked this place. Always `false` for anonymous clients. |
| `created_at`     | ISO 8601 UTC   | |

---

## Heart-icon UX flow

```
1. Anonymous user hits /api/places/most-liked
   → every place has is_liked: false
2. User logs in (existing /api/auth/otp/verify) → receives token
3. Re-fetch /api/places/most-liked with Authorization: Bearer <token>
   → is_liked now reflects the user's likes
4. User taps a heart → POST /api/places/{id}/like
   → optimistic UI: flip is_liked to true, likes_count + 1
   → on success: no-op; on error: revert
5. User taps a filled heart → DELETE /api/places/{id}/like
   → optimistic: is_liked false, likes_count - 1
```

The like/unlike endpoints are idempotent, so if the network drops and the request is retried, no harm done.

---

## Error shape

Validation error (422):

```json
{
  "status": 422,
  "message": "The given data was invalid.",
  "data": {
    "errors": {
      "field_name": ["Field is required."]
    }
  }
}
```

Auth error (401):

```json
{
  "status": 401,
  "message": "Unauthenticated.",
  "data": null
}
```

Not found (404):

```json
{
  "status": 404,
  "message": "Not found.",
  "data": null
}
```

---

## Quick cURL recipes

```bash
# Public reads
curl https://api.calmapp.co/api/countries
curl https://api.calmapp.co/api/cities
curl https://api.calmapp.co/api/place-types
curl https://api.calmapp.co/api/place-lists
curl https://api.calmapp.co/api/places/most-liked
curl https://api.calmapp.co/api/places/019eb8...      # detail screen

# Same, but viewer-aware (is_liked populated)
curl -H "Authorization: Bearer $TOKEN" \
     https://api.calmapp.co/api/places/most-liked

# Like / unlike
curl -X POST   -H "Authorization: Bearer $TOKEN" \
     https://api.calmapp.co/api/places/019eb8.../like
curl -X DELETE -H "Authorization: Bearer $TOKEN" \
     https://api.calmapp.co/api/places/019eb8.../like

# My favorites (liked places, paginated)
curl -H "Authorization: Bearer $TOKEN" \
     "https://api.calmapp.co/api/favorites?per_page=20&page=1"
```