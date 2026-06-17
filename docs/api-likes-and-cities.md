# Calm API — Likes & Favorites + Cities/Areas

Frontend reference for the like/unlike ("heart") feature and the city → area picker.

## Base

```
Base URL:   https://api.calmapp.co     (replace with your env URL)
Content:    application/json
Auth:       Authorization: Bearer <token>   (JWT from POST /api/auth/otp/verify)
```

Every response — success or error — uses the same envelope:

```json
{ "status": 200, "message": "…", "data": … }
```

`*_minor` money fields (if present) are in halalas (× 100). `is_liked` is `false` for anonymous requests.

---

# Likes & Favorites

| # | Action | Endpoint | Auth |
|---|--------|----------|------|
| ① | Like | `POST /api/places/{place}/like` | required |
| ② | Unlike | `DELETE /api/places/{place}/like` | required |
| ③ | My favorites | `GET /api/favorites` | required (paginated) |
| ④ | `is_liked` | field on every place card | — |

## ① Like a place

```
POST /api/places/{place}/like
Auth: required
```

Idempotent — liking twice stays liked (no duplicate row).

```json
{ "status": 200, "message": "Place liked.", "data": { "is_liked": true } }
```

**Errors:** `401` not authenticated · `404` place not found or not publicly visible (draft/pending/rejected/inactive).

## ② Unlike a place

```
DELETE /api/places/{place}/like
Auth: required
```

Idempotent — unliking something not liked still succeeds. Always allowed (so a user can remove a place that later went private).

```json
{ "status": 200, "message": "Place unliked.", "data": { "is_liked": false } }
```

**Errors:** `401` not authenticated.

## ③ My favorites (the user's liked places)

```
GET /api/favorites?per_page=20&page=1
Auth: required
```

The authenticated user's liked places, **newest-liked first**, **paginated**. Each item is the canonical **Place card** shape (identical to the home feed — render with the same component) and always `is_liked: true`. Only currently-visible places are returned.

- `per_page` — optional, default `20`, max `50`
- `page` — optional, default `1`

```json
{
  "status": 200,
  "message": "Liked places fetched.",
  "data": {
    "items": [
      {
        "id": "9b1f…",
        "title": "Lakeview Chalet",
        "description": "…",
        "price": 1000,
        "per_day_prices": { "sunday": 1000, "monday": 1000, "…": 0 },
        "check_in_time": "15:00",
        "check_out_time": "12:00",
        "max_guests": 6,
        "cover_photo_url": "https://…",
        "photos": [ { "id": "…", "url": "…", "attribute_id": null, "sort_order": 0, "featured_order": 0 } ],
        "featured_photos": [ { "id": "…", "url": "…", "attribute_id": null } ],
        "type": { "id": "…", "name_en": "Chalet", "name_ar": "شاليه", "icon": "🏖️" },
        "city": { "id": "…", "name_en": "Riyadh", "name_ar": "الرياض" },
        "city_area": { "id": "…", "name_en": "Al Olaya", "name_ar": "العليا" },
        "likes_count": 12,
        "rating": { "avg": 4.5, "count": 8 },
        "is_liked": true,
        "created_at": "2026-06-01T…"
      }
    ],
    "pagination": { "page": 1, "per_page": 20, "total": 37, "last_page": 2, "has_more": true }
  }
}
```

Infinite-scroll by requesting `?page=` until `pagination.has_more` is `false`. A heart turned off (`DELETE …/like`) drops the place from this feed on the next fetch.

**Errors:** `401` not authenticated.

## ④ `is_liked` on place cards

Every place object — home feed, `/places/most-liked`, detail, lists, favorites — carries:

| Field | Type | Meaning |
|-------|------|---------|
| `is_liked` | bool | `true` iff the **authed viewer** liked this place. `false` for anonymous requests. |
| `likes_count` | int | Total likes across all users. |

So you never need a separate "is this liked?" call — read it off the card. Public list/detail endpoints are **auth-aware**: send the Bearer token and `is_liked` populates; omit it and it's `false`.

## Frontend usage

```ts
// Heart toggle (optimistic)
async function toggleLike(place) {
  const liked = !place.is_liked;
  place.is_liked = liked;
  place.likes_count += liked ? 1 : -1;
  try {
    liked ? await api.post(`/places/${place.id}/like`)
          : await api.delete(`/places/${place.id}/like`);
  } catch {
    place.is_liked = !liked;                 // revert on failure
    place.likes_count += liked ? -1 : 1;
  }
}

// Favorites tab (infinite scroll)
async function loadFavorites(page = 1, acc = []) {
  const { data } = await api.get(`/favorites?page=${page}&per_page=20`);
  const all = [...acc, ...data.items];
  return data.pagination.has_more ? loadFavorites(page + 1, all) : all;
}
```

---

# Cities & Areas

## Get cities (with their areas)

```
GET /api/cities
Auth: not required (public)
```

Active cities, ordered by name, **each with its areas** — everything needed to render the city → area picker in one call.

```json
{
  "status": 200,
  "message": "Cities fetched.",
  "data": [
    {
      "id": "…",
      "name_en": "Riyadh",
      "name_ar": "الرياض",
      "avatar": "🏙️",
      "country_id": "…",
      "areas": [
        { "id": "ar_1", "name_en": "Al Olaya", "name_ar": "العليا" },
        { "id": "ar_2", "name_en": "Al Malqa", "name_ar": "الملقا" }
      ]
    }
  ]
}
```

| Field | Notes |
|-------|-------|
| `avatar` | Emoji/icon for the city card. |
| `country_id` | The city's country. |
| `areas[]` | Areas in the city, ordered by `name_en`; `[]` if the city has none. |
| `areas[].id` | **This is the `city_area_id`** you send when creating a place or requesting a quote/booking. |

```ts
const { data: cities } = await api.get('/cities');
// cities[i].areas[j].id  →  use as city_area_id elsewhere
```

> Related pickers: `GET /api/countries`, `GET /api/place-types`.
