# Checkout (same-day vs next-day) — frontend integration

A booking can be **same-day** (day-use: check in and out on the same date) or **next-day**
(overnight: check out the morning *after* the last booked date). The API tells you which, and gives
you a ready-to-display checkout timestamp so the app does **no** date math.

## Where the fields are

These live on the **booking object itself** — top level, **siblings of `place`** (NOT inside the
nested `place` object). Returned by every endpoint that serializes a booking:

| Endpoint | Auth | Returns checkout fields |
|---|---|---|
| `GET /api/bookings` | guest (Bearer) | ✅ "My bookings" list |
| `GET /api/bookings/pending` | guest (Bearer) | ✅ |
| `POST /api/places/{place}/bookings` | guest (Bearer) | ✅ (on the created booking) |
| `GET /api/host/bookings` | host (Bearer) | ✅ bookings on the host's places |

## The fields

| Field | Type | Meaning |
|---|---|---|
| `check_in_time` | string `"HH:MM"` | Daily check-in time (e.g. `"15:00"`). |
| `check_out_time` | string `"HH:MM"` | Daily check-out time (e.g. `"03:00"`). |
| `checkout_next_day` | **boolean** | `true` = overnight, checkout is the morning **after** `end_date`. `false` = same-day. |
| `checkout_at` | ISO 8601 datetime | **Fully resolved** checkout instant. Already accounts for the +1 day when `checkout_next_day` is true — use this directly. |
| `start_date` | date `YYYY-MM-DD` | First booked night / check-in date. |
| `end_date` | date `YYYY-MM-DD` | Last booked date (the night before checkout when next-day). |

`checkout_next_day` is a **snapshot taken on the booking at booking time**, so it stays correct even
if the host later changes the place's setting.

## Example

`GET /api/bookings` → one item (real values for booking `CB-3QAKD4`):

```json
{
  "id": "019ee...",
  "reference": "CB-3QAKD4",
  "status": "confirmed",
  "start_date": "2026-06-29",
  "end_date": "2026-06-29",
  "check_in_time": "15:00",
  "check_out_time": "03:00",
  "checkout_next_day": true,
  "checkout_at": "2026-06-30T03:00:00+00:00",
  "place": { "id": "019ee35b-...", "title": "..." }
}
```

Interpretation: check in **Jun 29, 15:00** → check out **Jun 30, 03:00** (next morning).

A same-day (day-use) booking looks like:

```json
{
  "start_date": "2026-06-29",
  "end_date": "2026-06-29",
  "check_in_time": "09:00",
  "check_out_time": "18:00",
  "checkout_next_day": false,
  "checkout_at": "2026-06-29T18:00:00+00:00"
}
```

## Frontend handling

- **Check-in** = `start_date` + `check_in_time`.
- **Check-out** = `checkout_at` (don't recompute from `end_date` — that's the bug if checkout looks
  like the same day). Show a **"next day / اليوم التالي"** badge when `checkout_next_day === true`.

```ts
const checkIn  = `${booking.start_date} ${booking.check_in_time}`;     // 2026-06-29 15:00
const checkOut = booking.checkout_at;                                  // 2026-06-30T03:00:00+...
const nextDay  = booking.checkout_next_day;                            // true

// e.g.
// الدخول   29 يونيو · 3:00 م
// الخروج   30 يونيو · 3:00 ص  {nextDay && '(اليوم التالي)'}
```

## ⚠️ Timezone note

`checkout_at` currently serializes with a **`+00:00` (UTC)** offset because the app's timezone is UTC.
The clock value is the intended local time (`03:00`), so the safest display is to combine the **date
from `checkout_at`** with the **`check_out_time`** string, rather than locale-converting the full
timestamp (which could shift it by the UTC↔KSA offset). If the backend timezone is later set to
`Asia/Riyadh`, `checkout_at` will read `+03:00` and you can use it directly.
