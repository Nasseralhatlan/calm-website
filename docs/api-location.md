# Place location link — frontend integration

The host adds a **location link** (a Google Maps / Apple Maps / any URL) to their place. It is
**private**: it is only returned to a guest once their booking is a real, paid stay.

## Where it appears

`location_url` lives on the **`place` object inside a booking** — never on the public place
endpoints. It is included by:

| Endpoint | Auth | Notes |
|---|---|---|
| `GET /api/bookings` | guest (Bearer) | The guest's "My bookings" list — primary place to read it. |
| `GET /api/host/bookings` | host (Bearer) | Bookings on the host's own places. |

It is **NOT** present on `GET /api/places/{place}`, search, most-liked, or any browse screen — by
design (exact address stays hidden until a booking is confirmed).

> The booking **create** response (`POST /api/places/{place}/bookings`) does not embed the place, so
> it has no `location_url`. Read it from `GET /api/bookings` after payment confirms.

## Visibility rule

`place.location_url` is a **string** only when the booking status is `confirmed` or `completed`.
For every other status it is **`null`** (even though the place actually has a link saved).

| booking `status` | `place.location_url` |
|---|---|
| `pending_payment` | `null` |
| `confirmed` | **the URL** ✅ |
| `completed` | **the URL** ✅ |
| `canceled_by_host` / `canceled_by_guest` | `null` |
| `expired` | `null` (these are also excluded from the guest list entirely) |

Also `null` if the host simply never added a link.

## Response shape

`GET /api/bookings` →

```json
{
  "status": 200,
  "message": "Bookings fetched.",
  "data": {
    "items": [
      {
        "id": "019ee0...",
        "reference": "CB-7K9P2Q",
        "status": "confirmed",
        "start_date": "2026-07-01",
        "end_date": "2026-07-03",
        "place": {
          "id": "019ee35b-...",
          "title": "شاليه عصري بتصميم فاخر في الرياض",
          "cover_photo_url": "https://.../cover.jpg",
          "location_url": "https://maps.app.goo.gl/tSBJUq7mDVTkD3yD6",
          "type": { "name_en": "Chalet", "name_ar": "شاليه", "icon": "🏡" },
          "city": { "name_en": "Riyadh", "name_ar": "الرياض" },
          "city_area": { "name_en": "Al Narjis", "name_ar": "النرجس" }
        }
      }
    ],
    "pagination": { "total": 1, "per_page": 15, "current_page": 1, "has_more": false }
  }
}
```

For a `pending_payment` booking the same shape comes back but `place.location_url` is `null`.

## Frontend handling

1. Show an **"Open location" / "الاتجاهات"** button on the booking only when
   `place.location_url` is a non-empty string. Hide it when `null`.
2. On tap, open the URL **externally** (system browser / maps app) — e.g. `Linking.openURL(url)`
   in React Native. Don't try to parse coordinates; it's an opaque link the host pasted.
3. Don't cache and show the link from a previously-confirmed booking against a different
   (unconfirmed) one — always read it from the current booking object.

```ts
// React Native example
{booking.place.location_url && (
  <Button title="الاتجاهات" onPress={() => Linking.openURL(booking.place.location_url)} />
)}
```

## Host side (for reference)

Hosts paste the link in the place wizard (web), on the **area** step — required for new places and on
every edit. Stored as-is; the only validation is that it's a valid URL.
