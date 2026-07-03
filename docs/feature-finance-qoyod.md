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

## Automatic host payouts (Moyasar Payouts)

Payouts can execute automatically instead of by hand. Mode flag: `MOYASAR_PAYOUTS_MODE`
(`manual` default / `auto`). The rule is **documents before money** — a booking is
*payable* (`Booking::isPayable()`) only when: completed + `payout_status=not_paid` +
`financial_completed_at` set (invoices issued) + past `checkoutAt + payout_hold_hours`
(Setting, default 24h — the dispute window, admin-editable, not exposed to the app).
This gate applies to BOTH auto transfers and manual mark-paid (422 otherwise); rows
in the queue show "Awaiting invoices" / "In hold until …" badges.

Flow (auto mode): `ProcessDuePayouts` (15 min) → `HostPayoutService::executeDuePayouts`
→ `POST /v1/payouts` (amount = `host_payout_amount` halalas, destination = host IBAN
`users.bank_account` + name + mobile, city `MOYASAR_PAYOUT_DEFAULT_CITY`) → row goes
`payout_status=processing` with `payout_id`. `ReconcileMoyasarPayouts` (10 min) polls:
`paid` → settle (paid_out_at, `payout_reference` = sequence number, `host_payout`
movement provider `moyasar`, payable → succeeded); `failed/returned/canceled` → back to
`not_paid` with the bank's reason in `payout_failure` + a **Retry** button in the admin
queue (the sweep deliberately skips failed rows). Manual mark-paid stays as fallback;
it's blocked while a transfer is `processing`.

Double-pay protection: the 16-digit Moyasar `sequence_number` is derived
deterministically from (booking id, `payout_attempts`) — a crashed or concurrent
create resends the SAME number and Moyasar rejects the duplicate. `payout_attempts`
advances only on a CONFIRMED bank failure (that sequence was consumed without moving
money), never on ambiguous timeouts. If a failure message ever says "duplicate
sequence", the payout likely exists at Moyasar — check the dashboard before retrying.

Go-live checklist: (1) Moyasar activates Payouts on the account; (2) company bank
account at Al Rajhi or SNB with bank API credentials; (3) one-time
`POST /v1/payout_accounts` to register it → id into `MOYASAR_PAYOUT_ACCOUNT_ID`
(needs `account_type=bank`, `properties.iban`, and `credentials` with a 6–15 digit
`company_code` + X509 `cert` + RSA/EC `key` in PEM); (4) optionally adjust
`payout_hold_hours` in admin settings; (5) set `MOYASAR_PAYOUTS_MODE=auto` +
`php artisan optimize:clear`. Sandbox works with `sk_test_` keys for end-to-end
rehearsal (**live-verified 2026-07-04**: registered a sandbox payout account,
executed + reconciled a real transfer). Field notes from the live API:
`destination.mobile` is required, and the purpose must be `payment_to_merchant` —
the IPS channel rejects several enum-valid purposes (e.g. `expenses_services`)
after creation.

## One-time Qoyod setup (when going live)

> **Live-verified 2026-07-04** end to end against the production Qoyod org + Moyasar
> sandbox payouts: one SR-10 test booking → both invoices created + paid in Qoyod
> (refs `-G`/`-C`), expiring PDF fetched, SR 8.85 payout executed and reconciled to
> `paid` with the full movement trail. Test invoices/receipts were deleted afterwards
> (delete the *receipts* first — Qoyod 422s deleting an invoice that has payments).

Setup already done in the Qoyod org (ids to use in env):

| Thing | Qoyod id |
|---|---|
| Product: Accommodation stay (`CALM-STAY`) | 1 |
| Product: Calm service fee (`CALM-FEE`) | 2 |
| Product: Platform commission (`CALM-COMM`) | 3 |
| Moyasar clearing (Bank Current Account) | 8 |
| Commission settlement offset (Accounts payable) | 14 |
| Inventory (Main Branch) | 1 (default) |

1. Paid Qoyod plan → General Settings → generate API key (rotate if ever exposed).
2. Products + account ids above already exist; the accountant can remap the
   settlement account later (payments post fine to account 14).
3. Env: `QOYOD_ENABLED=true`, `QOYOD_API_KEY`, `QOYOD_PRODUCT_STAY_ID=1`,
   `QOYOD_PRODUCT_SERVICE_FEE_ID=2`, `QOYOD_PRODUCT_COMMISSION_ID=3`,
   `QOYOD_MOYASAR_ACCOUNT_ID=8`, `QOYOD_SETTLEMENT_ACCOUNT_ID=14`.
4. `php artisan optimize:clear` — the next sweep syncs everything pending.

Keep `QOYOD_ENABLED=false` everywhere except production — there is no Qoyod sandbox,
so an enabled dev environment writes to the real books.

VAT posture: rates are snapshotted per booking from `FINANCE_VAT_ENABLED`/`FINANCE_VAT_RATE`
— set enabled=false while unregistered (docs then carry 0% VAT) and flip on registration;
historical documents keep their issued rates untouched.

## Tests

`tests/Feature/Finance/`: `FinanceFinalizerTest` (snapshot math, issuance + idempotency,
due window, unpaid/expired exclusion, payout trail, cancellation cases B/C),
`QoyodSyncTest` (disabled = zero HTTP, full mirror with SAR decimals, failure retry,
expiring pdf links), `FinanceDocumentAccessTest` (owner-only lists, 404 for others,
admin pass, 409 pre-sync, 401), `AutoPayoutTest` (manual mode = zero HTTP, transfer +
settle with movements, bank-failure requeue + fresh sequence on retry, missing IBAN,
hold-window/documents gating, manual mark-paid guards, admin processing tab + retry).
