# Promo codes (Calm-funded discounts)

Admin creates codes at **/admin/promo-codes**. Guests enter them at checkout
(mobile summary screen + web `/book/{place}/checkout`).

## Money rule

The discount is **Calm's money, never the host's**:

| | Without promo | With promo D (ex VAT) |
|---|---|---|
| Guest pays | stay + VAT(stay) | (stay − D) + VAT(stay − D) |
| Commission invoice to host | commission on stay | **unchanged** |
| Host payout | stay − commission_total | **unchanged** |
| Calm keeps (ex VAT) | commission | **commission − D** (`Booking::calmNetMinor()`) |

Cross-check: `total_amount = host_payout_amount + commission_total − discount_amount + vat_amount`.

The guest tax invoice is issued on `stay − D` (one line, unit = stay,
subtotal = after discount, code named in the description). Qoyod receives
the line with `discount` + `discount_type=amount` — **verify against the
Qoyod sandbox before enabling `QOYOD_ENABLED` with promo bookings.**

## "Exceeded Calm's percentage"

A code exceeds Calm's share when the discount can be bigger than the
commission (Calm then pays from its pocket). `PromoCode::commissionCheck()`:

- **Percentage** — `discount % > commission_percentage` setting.
- **Fixed** — loses money on stays below `amount ÷ commission rate`
  (break-even), unless the code's minimum stay is at least that.

Where it shows: live red/green box on the create/edit form (+ a confirm on
save), a flash warning after saving, a badge per code on the list, the
"Calm out of pocket" total per code, and "Calm keeps after promo" on the
admin booking page (red when negative). Exceeding is **allowed** — it warns,
it never blocks.

## Rules

- Codes are case-insensitive (stored uppercase), `A–Z 0–9 - _`.
- Optional: max discount cap (percentage codes), minimum stay (ex VAT),
  total uses, uses per guest, start/end day (inclusive, Asia/Riyadh), active.
- A use is held by confirmed/completed bookings and by live unpaid holds;
  expired holds and cancellations release it. Booking creation re-validates
  the code under a row lock (no double redemption of the last use).
- The guest always pays at least 1 SR of the stay.
- Bookings snapshot `promo_code_id`, `promo_code`, `discount_amount`; later
  edits to a code never touch existing bookings. A used code can't be
  deleted — Delete deactivates it.

## API

- `GET /api/places/{place}/quote?…&promo_code=X` → `pricing.discount` and
  `promo: {code, applied, reason, message, min_stay}`. `reason` is one of
  `not_found | inactive | not_started | expired | exhausted | already_used |
  min_stay` — clients localize by it. Signed-out guests can apply a code:
  the per-guest limit is skipped on an anonymous quote and enforced when the
  booking is created (after login), returning 422 `promo_code` if used up.
- `POST /api/places/{place}/bookings` accepts `promo_code`; 422 on
  `errors.promo_code` if it no longer applies.
- Booking payloads: `pricing.discount`, `pricing.promo_code`.
