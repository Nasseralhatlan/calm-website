# Finance module: invoices, movements & Qoyod e-invoicing

> Status: **built** (2026-07-03) on branch `feature/finance-qoyod`. Marketplace model per
> the implementation brief: **Calm → Guest** booking invoice + **Calm → Host** commission
> invoice (official tax documents, mirrored to Qoyod when enabled) and an internal **host
> payout settlement statement** (never an invoice). Qoyod ships **disabled** — everything
> works locally; flip the env when the Qoyod account exists.

## Money model (per booking, all halalas, frozen at creation)

| Field | Meaning |
|---|---|
| `host_gross_amount` | Stay value belonging to the host (= legacy `booking_amount`) |
| `guest_vat_rate/amount`, `guest_total` | The Calm → Guest invoice numbers (= legacy `vat_*`, `total`) |
| `guest_service_fee_amount` (+vat) | Optional Calm fee to guests — 0 until productized |
| `commission_amount_ex_vat` | Calm's commission excluding VAT (= legacy `commission_amount`) |
| `commission_vat_rate/amount`, `commission_total` | **15% VAT ON TOP of commission** — new bookings only; historical rows backfilled with 0 so old payouts never change |
| `host_payout_amount` | `host_gross − commission_total` — THE payout number everywhere |

`BookingPricingService` fills these at creation (also via a model `creating` hook, so
seeders/tests/every path agree). `Booking::hostNetMinor()` returns `host_payout_amount`.

## Document flow (brief §11)

1. **Guest pays** → movement `guest_payment` (Moyasar ref). No documents yet.
2. **Checkout + N hours** (`FINANCE_INVOICE_ISSUE_HOURS`, default 4) → the
   `FinalizeBookingFinances` sweep (every 15 min) issues, idempotently, per booking:
   - `guest_booking_invoice` (tax) + `host_commission_invoice` (tax) +
     `host_payout_statement` (internal), with printable lines;
   - movements `commission_withheld` (succeeded) + `host_payout_payable` (pending);
   - stamps `*_issued_at` + `financial_completed_at`. Unique
     `(source, subtype)` makes double-issuing impossible.
3. **Admin marks payout paid** → movement `host_payout` (bank ref); payable → succeeded.
   Undo reverses both. **Cancellations** (§14): before payment = nothing; paid-but-not-
   invoiced = `guest_refund` movement; after invoicing = credit notes for both invoices
   (originals flip to `credited`, never edited) + refund movement + reversals.

## Qoyod sync (flag `QOYOD_ENABLED`, default off)

When enabled, tax documents are created as `pending_provider` and every sweep pushes
pending/failed ones: customer create/reuse (guests → `users.qoyod_customer_id`, hosts →
`host_tax_profiles.qoyod_customer_id`), `POST /2.0/invoices` (SAR decimal strings,
`tax_percent` from the booking snapshot — 0 pre-VAT-registration, 15 after),
`POST /2.0/invoice_payments` (guest → Moyasar clearing account, commission → settlement
offset account), credit notes on cancellation. Failures mark the document `failed` and
retry next sweep — an outage delays, never loses. PDF links come from
`GET /2.0/invoices/{id}/pdf` (expiring) — fetched fresh per request, never stored.

## API (mobile)

- `GET /api/finance-documents` — the viewer's OWN documents (guest: booking invoices /
  credit notes; host: commission invoices + payout statements), paginated
  (`items` + `pagination`). Fields incl. `document_subtype`, amounts, `booking_reference`,
  `has_pdf`.
- `GET /api/finance-documents/{id}/pdf-url` → `{url}` — fresh expiring Qoyod link.
  Someone else's document → **404** (existence never leaks; admin allowed).
  Not synced / statement → **409**.

## One-time Qoyod setup (when going live)

1. Paid Qoyod plan → General Settings → generate API key.
2. Create 3 service products (stay, service fee, commission) + note ids; note the two
   account ids (Moyasar clearing + commission settlement) from the chart of accounts.
3. Env: `QOYOD_ENABLED=true`, `QOYOD_API_KEY`, `QOYOD_PRODUCT_STAY_ID`,
   `QOYOD_PRODUCT_SERVICE_FEE_ID`, `QOYOD_PRODUCT_COMMISSION_ID`,
   `QOYOD_MOYASAR_ACCOUNT_ID`, `QOYOD_SETTLEMENT_ACCOUNT_ID`.
4. `php artisan optimize:clear` — the next sweep syncs everything pending.

VAT posture: rates are snapshotted per booking from `FINANCE_VAT_ENABLED`/`FINANCE_VAT_RATE`
— set enabled=false while unregistered (docs then carry 0% VAT) and flip on registration;
historical documents keep their issued rates untouched.

## Tests

`tests/Feature/Finance/`: `FinanceFinalizerTest` (snapshot math, issuance + idempotency,
due window, unpaid/expired exclusion, payout trail, cancellation cases B/C),
`QoyodSyncTest` (disabled = zero HTTP, full mirror with SAR decimals, failure retry,
expiring pdf links), `FinanceDocumentAccessTest` (owner-only lists, 404 for others,
admin pass, 409 pre-sync, 401).
