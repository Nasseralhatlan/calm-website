# Calm Booking & Payment Flow APIs

Everything the mobile app needs to take a guest from picking dates to a confirmed, paid booking. Payments go through **Moyasar** (hosted invoice opened in a WebView). All responses share the standard envelope.

The whole journey, in order:

```
①  Calendar         GET  /api/places/{place}/unavailable-dates     public
②  Summary page     GET  /api/places/{place}/quote                 public
③  Go to payment    POST /api/places/{place}/bookings              auth
④  WebView          open data.payment.url → pay → Moyasar redirects to
                       /calm-after-payment  (done)   or
                       /calm-back-payment   (backed out)
⑤  Check status     GET  /api/bookings/{booking}/payment-status    auth (owner)
⑥  Cancel on back   POST /api/bookings/{booking}/cancel            auth (owner)

My bookings        GET  /api/bookings                             auth (paginated)
```

---

## My bookings (list)

```
GET /api/bookings?per_page=20&page=1
Auth: required
```

The authenticated guest's own bookings — **newest first**, **paginated**. Each item carries the booking status, dates, price, and a **place summary** (title, cover photo, city, type) so you can render the list without extra calls. `per_page` defaults to 20 (max 50).

```json
{
  "status": 200,
  "message": "Bookings fetched.",
  "data": {
    "items": [
      {
        "id": "9c2f…",
        "place_id": "9b1f…",
        "place": {
          "id": "9b1f…",
          "title": "Lakeview Chalet",
          "cover_photo_url": "https://…",
          "type": { "name_en": "Chalet", "name_ar": "شاليه", "icon": "🏖️" },
          "city": { "name_en": "Riyadh", "name_ar": "الرياض" },
          "city_area": { "name_en": "Al Olaya", "name_ar": "العليا" }
        },
        "status": "confirmed",
        "start_date": "2026-06-16",
        "end_date": "2026-06-17",
        "check_in_time": "15:00",
        "check_out_time": "12:00",
        "guests": 2,
        "currency": "SAR",
        "pricing": { "subtotal": 2000, "vat_percentage": 15, "vat": 300, "total": 2300, "total_minor": 230000 },
        "payment": { "id": "inv_…", "method": "creditcard", "status": "paid", "url": "…" },
        "expires_at": null,
        "confirmed_at": "2026-06-13T…",
        "created_at": "2026-06-13T…"
      }
    ],
    "pagination": { "page": 1, "per_page": 20, "total": 9, "last_page": 1, "has_more": false }
  }
}
```

**Frontend:** powers the "My bookings" tab. Infinite-scroll via `?page=` until `pagination.has_more` is `false`. Use `status` for the badge (see status table below), `place.cover_photo_url`/`place.title` for the card, `pricing.total` for the amount. **Errors:** `401` not authenticated.

---

## Base

```
Base URL:   https://api.calmapp.co   (replace with your env URL)
Content:    application/json (request + response)
Auth:       Authorization: Bearer <token>   (JWT from POST /api/auth/otp/verify)
```

## Response envelope

Every response — success or error — looks like:

```json
{ "status": 200, "message": "…", "data": { } }
```

## Money

- Amounts are in **SAR**. Fields suffixed `_minor` are in **halalas** (× 100): `230000` = `2300.00 SAR`.
- The guest pays **`pricing.total`** (= `subtotal` + VAT). Calm's commission is host-side and never shown to the guest.

## Dates

- `check_in` / `check_out` (and `start_date` / `end_date`) are **inclusive**: `2026-06-16 → 2026-06-17` is **2 days**. A single-day stay has `check_in == check_out`.
- Format `YYYY-MM-DD`. `check_in` can't be in the past; max stay 365 days.

---

## ① Date picker — grey out unavailable days

```
GET /api/places/{place}/unavailable-dates?from=2026-06-13&to=2027-06-13
```

Public. `from`/`to` optional — default `[today, today + 12 months]`, capped at 18 months.

### `200 OK`

```json
{
  "status": 200,
  "message": "Unavailable dates fetched.",
  "data": {
    "place_id": "9b1f…",
    "from": "2026-06-13",
    "to": "2027-06-13",
    "unavailable_dates": ["2026-06-18", "2026-06-19", "2026-07-01"],
    "unavailable_ranges": [
      { "start_date": "2026-06-18", "end_date": "2026-06-19" },
      { "start_date": "2026-07-01", "end_date": "2026-07-01" }
    ]
  }
}
```

| Field | Meaning |
|-------|---------|
| `unavailable_dates` | Every blocked day — **disable these in the calendar**. Includes both host blocks **and** already-booked dates. |
| `unavailable_ranges` | The same days folded into contiguous blocks (cheap to shade). Both ends inclusive. |

**Errors:** `404` place not visible.

---

## ② Summary page — availability + price for the chosen dates

```
GET /api/places/{place}/quote?check_in=2026-06-16&check_out=2026-06-17&guests=2
```

Public. `guests` optional (when sent, checked against `max_guests`). Read-only — no booking is created.

### `200 OK`

```json
{
  "status": 200,
  "message": "Quote calculated.",
  "data": {
    "place_id": "9b1f…",
    "check_in": "2026-06-16",
    "check_out": "2026-06-17",
    "days": 2,
    "guests": 2,
    "max_guests": 6,
    "currency": "SAR",
    "bookable": true,
    "dates_available": true,
    "guests_ok": true,
    "unavailable_dates": [],
    "breakdown": [
      { "date": "2026-06-16", "weekday": "tuesday",   "price": 1000, "available": true },
      { "date": "2026-06-17", "weekday": "wednesday", "price": 1000, "available": true }
    ],
    "pricing": {
      "subtotal": 2000,
      "vat_percentage": 15,
      "vat": 300,
      "total": 2300,
      "total_minor": 230000
    }
  }
}
```

| Field | Use |
|-------|-----|
| **`bookable`** | Enable **Go to payment** only when `true` (= `dates_available && guests_ok`). |
| `dates_available` / `guests_ok` | Explain *why* it's not bookable. |
| `unavailable_dates` | Which requested days are taken (highlight them). |
| `breakdown[]` | Per-day rows for the price list (each has its own `available`). |
| `pricing.subtotal` | Sum of nightly prices. |
| `pricing.vat` / `vat_percentage` | VAT amount + rate (label `"VAT 15%"`). |
| `pricing.total` | **Amount due** — show this big. |
| `pricing.total_minor` | The exact charge in halalas (informational; the server uses it for payment). |

**Errors:** `422` bad/past/reversed dates or stay > 365 days · `404` place not visible.

---

## ③ Go to payment — create the booking

```
POST /api/places/{place}/bookings          (Bearer token required)

{ "check_in": "2026-06-16", "check_out": "2026-06-17", "guests": 2 }
```

| Body field | Required | Notes |
|------------|----------|-------|
| `check_in` | ✅ | Inclusive first day. |
| `check_out` | ✅ | Inclusive last day, ≥ `check_in`. |
| `guests` | ✅ | ≥ 1, ≤ the place's `max_guests`. |

The server re-verifies availability + price (source of truth), **holds the dates for 10 minutes**, and opens a Moyasar invoice.

### `201 Created`

```json
{
  "status": 201,
  "message": "Booking created — proceed to payment.",
  "data": {
    "id": "9c2f…",
    "place_id": "9b1f…",
    "status": "pending_payment",
    "start_date": "2026-06-16",
    "end_date": "2026-06-17",
    "check_in_time": "15:00",
    "check_out_time": "12:00",
    "guests": 2,
    "currency": "SAR",
    "pricing": { "subtotal": 2000, "vat_percentage": 15, "vat": 300, "total": 2300, "total_minor": 230000 },
    "payment": {
      "id": "inv_xxx",
      "method": null,
      "status": "initiated",
      "url": "https://checkout.moyasar.com/invoices/inv_xxx"
    },
    "expires_at": "2026-06-13T10:10:00+00:00",
    "confirmed_at": null,
    "created_at": "2026-06-13T10:00:00+00:00"
  }
}
```

**Frontend:** store `data.id` (booking id), then **open `data.payment.url` in a WebView**. Optionally show a countdown to `expires_at`.

**Errors:**

| Status | Meaning → UI |
|--------|--------------|
| `401` | Not logged in → login. |
| `422` | Dates just taken **or** guests > max (message says which) → back to ① / ②. |
| `404` | Place no longer available. |
| `502` | Couldn't open payment → retry. |

---

## ④ Pay in the WebView, then return

Open `payment.url`. Sandbox test card: `4111 1111 1111 1111`, any future expiry, CVC `123`.

When the guest finishes (or backs out), **Moyasar redirects the WebView to one of two URLs** — watch `onNavigationStateChange` for these substrings:

| Redirect URL contains | Means | Do |
|-----------------------|-------|-----|
| `calm-after-payment` | Payment attempt finished | Close the WebView → go to ⑤ to confirm. |
| `calm-back-payment` | Guest backed out of the hosted page | Call the **cancel** endpoint (⑥) to release the hold, then close. |

Both pages render a small "returning to the app…" placeholder (they exist so the redirect doesn't 404). Moyasar appends `?id=<payment>&status=<...>` — informational; you already hold the `booking.id`.

```ts
const onNav = (s: WebViewNavigation) => {
  if (s.url.includes("calm-after-payment")) {
    navigation.navigate(AFTER_PAYMENT, { bookingId: booking.id });
  } else if (s.url.includes("calm-back-payment")) {
    api.post(`/bookings/${booking.id}/cancel`).finally(() => navigation.pop());
  }
};
```

> The redirect is only a hint. **Always confirm via ⑤** — never mark success off the redirect alone.

---

## ⑤ Check status — confirm the booking

```
GET /api/bookings/{booking}/payment-status      (Bearer token, owner only)
```

Re-verifies against Moyasar and settles the booking. Returns the **same booking object** as ③, with `status` (and `payment.*`, `confirmed_at`) updated.

### `200 OK`

```json
{
  "status": 200,
  "message": "Payment status checked.",
  "data": {
    "id": "9c2f…",
    "status": "confirmed",
    "payment": { "id": "inv_xxx", "method": "creditcard", "status": "paid", "url": "…" },
    "confirmed_at": "2026-06-13T10:03:00+00:00"
  }
}
```

**Poll a few times after the WebView closes:**

| `data.status` | Action |
|---------------|--------|
| `confirmed` | ✅ Success screen. Stop polling. |
| `pending_payment` | Still processing → keep polling (~every 2s, up to ~10 tries / until `expires_at`). |
| `expired` / other | ❌ Failed or timed out → offer retry (retry = repeat ③, a fresh booking). |

**Errors:** `401` auth · `403` not your booking · `404` unknown booking.

> Even if the app is killed before polling, the booking still confirms server-side via the Moyasar webhook and a per-minute reconciliation job. On next open, one `payment-status` call (or your bookings list) shows the correct state.

---

## ⑥ Cancel on "back" — release a still-unpaid hold

```
POST /api/bookings/{booking}/cancel      (Bearer token, owner only)
```

Call this in the `calm-back-payment` branch when the guest abandons the hosted page, to free the dates immediately instead of waiting out the 10-minute hold.

It is **pending-only and safe to call anytime**: it re-verifies against Moyasar first, so

- a booking that was actually **paid** in a race → becomes **`confirmed`** (not cancelled),
- a still-unpaid pending hold → becomes **`expired`** and the dates free up now,
- an already-**`confirmed`** booking → **untouched** (no-op).

Returns the same booking object with the resulting `status`. **Errors:** `401` auth · `403` not your booking · `404` unknown booking.

---

## Booking `status` values

| Status | Meaning |
|--------|---------|
| `pending_payment` | Holding the dates, awaiting payment (until `expires_at`). |
| `confirmed` | Paid — booked. ✅ |
| `expired` | Not paid in time, or failed/cancelled at the gateway — dates released. |
| `canceled_by_host` / `canceled_by_guest` | Post-booking cancellation (lifecycle not built yet). |
| `completed` | Stay finished (lifecycle not built yet). |

---

## Reference implementation (pseudo)

```ts
// ① calendar
const cal = (await api.get(`/places/${placeId}/unavailable-dates`)).data;
const disabled = new Set(cal.unavailable_dates);

// user picks check_in / check_out → Next

// ② summary
const quote = (await api.get(`/places/${placeId}/quote`, {
  params: { check_in, check_out, guests },
})).data;
if (!quote.bookable) return showWhyNotBookable(quote);
renderSummary(quote.breakdown, quote.pricing);

// ③ go to payment
const booking = (await api.post(`/places/${placeId}/bookings`,
  { check_in, check_out, guests })).data;

// ④ pay
await openWebView(booking.payment.url);   // resolves when redirected/closed

// ⑤ confirm
let b = booking;
for (let i = 0; i < 10 && b.status === "pending_payment"; i++) {
  await sleep(2000);
  b = (await api.get(`/bookings/${booking.id}/payment-status`)).data;
}
b.status === "confirmed" ? showSuccess(b) : showFailedRetry();
```

---

## Webhook (backend/ops — not called by the app)

`POST /api/payments/moyasar/webhook` — register this URL in the Moyasar dashboard with `MOYASAR_WEBHOOK_SECRET`. On each payment event Calm verifies the secret, re-fetches the invoice from Moyasar (never trusts the body), and confirms the booking — so confirmation doesn't depend on the app polling.
