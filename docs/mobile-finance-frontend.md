# Mobile Finance — Frontend Integration Guide

Everything the app needs to build the money surfaces for hosts and guests.
All endpoints require `Authorization: Bearer <JWT>` and return the standard
wrapper `{ "success", "message", "data" }`. Money convention: fields ending
in `_minor` are **halalas** (integer); their float twins are SAR. Currency is
always `"SAR"`. Paginated lists share one shape:

```json
"pagination": { "page": 1, "per_page": 15, "total": 42, "last_page": 3, "has_more": true }
```

---

## 1. Host — the Finance tab

Layout: **summary cards always on top**, then a segmented control with two
sub-tabs: **Transfers** (التحويلات) and **Invoices** (الفواتير).

### 1.1 Summary cards — `GET /api/host/earnings`

```json
{
  "currency": "SAR",
  "bookings_count": 4,
  "total": 7080.0,               "total_minor": 708000,
  "paid": 1770.0,                "paid_minor": 177000,
  "not_paid": 5310.0,            "not_paid_minor": 531000,
  "processing": 1770.0,          "processing_minor": 177000,
  "upcoming": 1770.0,            "upcoming_minor": 177000,
  "awaiting_completion": 1770.0, "awaiting_completion_minor": 177000,
  "needs_bank_details": false
}
```

Card mapping:
| Card | Field | Meaning |
|---|---|---|
| تم تحويله · Paid out | `paid` | Money already in the host's bank |
| قيد التحويل · On its way | `processing` | Bank transfer in flight (settles ~same day) |
| قادم · Upcoming | `upcoming` | Stay done + invoiced; transfers after the hold window |
| إقامات جارية · Current stays | `awaiting_completion` | Guests booked/staying; earned when the stay completes |

`total = paid + processing + upcoming + awaiting_completion` — the cards
always sum to the ledger.

**IBAN banner:** when `needs_bank_details == true`, show a prominent banner
"أضف الآيبان لاستلام أرباحك" linking to the bank-details form
(`PATCH /api/user` with `bank`, `bank_account` = `SA`+22 digits,
`bank_account_name` = name as written at the bank — all optional-nullable).

### 1.2 Transfers sub-tab — `GET /api/host/payouts`

Paginated ledger, newest checkout first. Optional filter:
`?state=paid|processing|upcoming|awaiting_completion|awaiting_bank_details|failed`
(invalid value → 422). Each item carries everything the inline expansion needs:

```json
{
  "booking_id": "uuid",
  "booking_reference": "CB-8ZFXEJ",
  "place_title": "شاليه فاخر",
  "check_in": "2026-07-04",
  "check_out": "2026-07-05",
  "currency": "SAR",
  "gross": 500.0,      "gross_minor": 50000,
  "commission": 57.5,  "commission_minor": 5750,
  "net": 442.5,        "net_minor": 44250,
  "booking_vat": 75.0,    "booking_vat_minor": 7500,
  "commission_vat": 7.5,  "commission_vat_minor": 750,
  "payout_state": "paid",
  "payout_paid_at": "2026-07-06T18:47:06+00:00",
  "payout_reference": "8362513285481670",
  "expected_at": null,
  "documents": [
    { "id": "uuid", "document_type": "invoice", "number": "CB-8ZFXEJ-C",
      "total_amount": 5750, "has_pdf": true, "issued_at": "2026-07-05T16:00:00+00:00" },
    { "id": "uuid", "document_type": "settlement_statement", "number": null,
      "total_amount": 44250, "has_pdf": false, "issued_at": "2026-07-05T16:00:00+00:00" }
  ]
}
```

Collapsed row: place title + dates, **net** (headline), state chip — badge the
row when `documents` contains an `invoice` with `has_pdf`.
Expanded row: gross − commission = net breakdown, per-state extras below, and
the `documents` list — opening an operation needs NO extra request. Only the
host's own paper appears (`total_amount` in halalas); the expiring PDF link is
still fetched per tap via `GET /api/finance-documents/{id}/pdf-url`. Empty
array until the stay is invoiced.

**State → UI:**
| `payout_state` | Chip (suggested) | Expanded extra |
|---|---|---|
| `paid` | ✓ تم التحويل (green) | `payout_paid_at` + bank ref `payout_reference` |
| `processing` | ⏳ قيد التحويل (blue) | "يُسوّى تلقائياً خلال ساعات" |
| `upcoming` | 🕓 قادم (amber) | "متوقع {expected_at}" |
| `awaiting_completion` | 🛏 إقامة جارية (gray) | "يُستحق بعد انتهاء الإقامة" |
| `awaiting_bank_details` | ⚠ أضف الآيبان (amber) | CTA → bank-details form (transfers automatically after) |
| `failed` | 🔄 قيد المتابعة (neutral) | "فريق كالم يتابع التحويل" — do NOT show bank error text |

`expected_at` = when the payout unlocks (checkout + hold window); `null` once
paid. Empty ledger → "لا أرباح بعد — استقبل أول حجز لترى أرباحك هنا".

### 1.3 Invoices sub-tab — `GET /api/finance-documents`

Same endpoint both roles use; the host receives their paper only:
`host_commission_invoice` (فاتورة العمولة الضريبية), `host_commission_credit_note`
(إشعار دائن، إن وُجد), `host_payout_statement` (بيان المستحق). Item shape:

```json
{
  "id": "uuid",
  "document_type": "invoice",
  "document_subtype": "host_commission_invoice",
  "status": "issued",
  "is_tax_document": true,
  "number": "CB-8ZFXEJ-C",
  "currency": "SAR",
  "subtotal_amount": 5000, "vat_amount": 750, "total_amount": 5750,
  "booking_reference": "CB-8ZFXEJ",
  "issued_at": "2026-07-05T16:00:00+00:00",
  "has_pdf": true
}
```
(All three amount fields are halalas.)

**PDF flow:** if `has_pdf` → `GET /api/finance-documents/{id}/pdf-url` →
`{ "url": "…" }` — an **expiring** link: open immediately, never cache;
re-fetch on every tap. `409` = PDF not ready yet (still syncing) — show
"الفاتورة قيد الإصدار، حاول لاحقاً". Payout statements have no PDF
(`has_pdf: false`) — render them in-app from the JSON if desired.

---

## 2. Guest — money on the booking

### 2.1 What they paid — `pricing` on every booking (existing)
```json
"pricing": { "subtotal": 100.0, "vat_percentage": 15, "vat": 15.0,
             "total": 115.0, "total_minor": 11500 }
```

### 2.2 Refunds — `refund` block (presence = refunded)
On any CANCELLED booking the guest had paid:
```json
"refund": { "refunded": true, "amount": 115.0, "amount_minor": 11500 }
```
Key absent on all other bookings. Render: "تم استرداد SR 115.00 إلى بطاقتك".
(Refund policy is full-only, so `amount` always equals the total.)

### 2.3 "View invoice" on booking detail
`GET /api/finance-documents?booking_id={booking_id}` → that booking's
documents only (usually one `guest_booking_invoice`; plus
`guest_booking_credit_note` if refunded after invoicing). Same PDF flow as
§1.3. Empty list = no invoice exists (booking not completed yet, or it was
cancelled before invoicing — the `refund` block tells that story). Someone
else's `booking_id` also returns an empty list (never an error).

---

## 3. Errors & guarantees

- `401` unauthenticated · `422` invalid `state` / malformed `booking_id`.
- Users only ever receive THEIR documents; internal accounting vouchers are
  never present in any list.
- States/buckets are computed server-side from one source of truth — never
  derive payout status client-side from raw fields; use `payout_state`.
- All lists are paginated; request more with `?page=N` while
  `pagination.has_more` is true.
