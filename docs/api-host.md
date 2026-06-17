# Calm Host App APIs

Endpoints for a user acting as a **host**. All require auth (`Authorization: Bearer <token>`); they
operate on the authenticated user's own places — a user with no places just gets empty lists / zero
earnings. Standard envelope `{ status, message, data }`. Money is **SAR**; `*_minor` fields are halalas
(× 100).

```
Bookings   GET /api/host/bookings     auth (paginated)
Listings   GET /api/host/listings     auth (paginated)
Earnings   GET /api/host/earnings     auth
```

---

## 1. Bookings on my places

```
GET /api/host/bookings?per_page=20&page=1
```

Bookings guests placed on the host's places, **newest first**, **paginated**. Each item carries the
booking status, dates, price, the **place** summary, and the **guest** (name + phone, so the host can
contact them). `per_page` default 20 (max 50).

```json
{
  "status": 200,
  "message": "Host bookings fetched.",
  "data": {
    "items": [
      {
        "id": "9c2f…",
        "place_id": "9b1f…",
        "place": { "id": "…", "title": "Lakeview Chalet", "cover_photo_url": "…", "type": {…}, "city": {…}, "city_area": {…} },
        "guest": { "id": "…", "name": "Sara", "phone": "512345678" },
        "status": "confirmed",
        "start_date": "2026-06-16",
        "end_date": "2026-06-17",
        "check_in_time": "15:00",
        "check_out_time": "12:00",
        "guests": 2,
        "currency": "SAR",
        "pricing": { "subtotal": 2000, "vat_percentage": 15, "vat": 300, "total": 2300, "total_minor": 230000 },
        "payment": { "id": "…", "method": "creditcard", "status": "paid", "url": "…" },
        "confirmed_at": "…", "created_at": "…"
      }
    ],
    "pagination": { "page": 1, "per_page": 20, "total": 12, "last_page": 1, "has_more": false }
  }
}
```

> `pricing.total` is what the **guest paid**. The host's earnings on it are in the earnings endpoint
> (booking amount minus commission). Use `status` for the badge — see the status table in
> `api-booking-flow.md`.

**Errors:** `401` not authenticated.

---

## 2. My listings

```
GET /api/host/listings?per_page=20&page=1
```

The host's own places — **all of them regardless of status** (drafts, pending, rejected, live), **newest
first**, **paginated**. Each item exposes the host-only lifecycle fields and dashboard counts.

```json
{
  "status": 200,
  "message": "Host listings fetched.",
  "data": {
    "items": [
      {
        "id": "9b1f…",
        "title": "Lakeview Chalet",
        "cover_photo_url": "https://…",
        "price": 1000,
        "max_guests": 6,
        "type": { "id": "…", "name_en": "Chalet", "name_ar": "شاليه", "icon": "🏖️" },
        "city": { "id": "…", "name_en": "Riyadh", "name_ar": "الرياض" },
        "city_area": { "id": "…", "name_en": "Al Olaya", "name_ar": "العليا" },
        "status": "active",
        "review_status": "approved",
        "rejection_reason": null,
        "likes_count": 12,
        "bookings_count": 8,
        "rating": { "avg": 4.5, "count": 6 },
        "created_at": "2026-06-01T…"
      }
    ],
    "pagination": { "page": 1, "per_page": 20, "total": 3, "last_page": 1, "has_more": false }
  }
}
```

| Field | Type | Notes |
|-------|------|-------|
| `status` | string | `active` · `inactive` |
| `review_status` | string | `draft` · `pending_review` · `approved` · `rejected` |
| `rejection_reason` | string·null | Admin feedback when `review_status` is `rejected` |
| `likes_count` / `bookings_count` | int | Dashboard counts |
| `rating.avg` / `.count` | number·null / int | Average rating + review count |

> Use `status` + `review_status` to drive the listing's badge/CTA (e.g. "Pending review", "Rejected —
> edit & resubmit", "Live"). `price` is the base nightly price (SAR).

**Errors:** `401` not authenticated.

---

## 3. My earnings

```
GET /api/host/earnings
```

The host's earnings across all **confirmed/completed** bookings on their places. A host earns the
**booking amount minus Calm's commission** (VAT is the guest's and is remitted, not earned). Split by
payout settlement state.

```json
{
  "status": 200,
  "message": "Host earnings fetched.",
  "data": {
    "currency": "SAR",
    "bookings_count": 9,
    "total": 16200,        "total_minor": 1620000,
    "paid": 10800,         "paid_minor": 1080000,
    "not_paid": 5400,      "not_paid_minor": 540000
  }
}
```

| Field | Meaning |
|-------|---------|
| `total` | All earnings (`paid` + `not_paid`). |
| `paid` | Already paid out to the host (`payout_status = paid`). |
| `not_paid` | Earned but not yet paid out (`payout_status = not_paid`). |
| `bookings_count` | Number of confirmed/completed bookings counted. |
| `*_minor` | Same amounts in halalas (exact integers). |

Only **confirmed** and **completed** bookings count — pending/expired/cancelled are excluded.

**Errors:** `401` not authenticated.

---

```ts
// host dashboard load
const [{ data: earnings }, { data: listings }] = await Promise.all([
  api.get('/host/earnings'),
  api.get('/host/listings'),
]);
showEarnings(earnings.total, earnings.paid, earnings.not_paid);   // SAR
renderListings(listings.items);                                   // status drives the badge
```
