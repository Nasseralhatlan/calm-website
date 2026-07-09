# Finance module: invoices, movements & Qoyod e-invoicing

> Status: **built** (2026-07-03) on branch `feature/finance-qoyod`. Marketplace model per
> the implementation brief: **Calm → Guest** booking invoice + **Calm → Host** commission
> invoice (official tax documents, mirrored to Qoyod when enabled) and an internal **host
> payout settlement statement** (never an invoice). Qoyod ships **disabled** — everything
> works locally; flip the env when the Qoyod account exists.

## Money model (per booking, all halalas, frozen at creation)

The booking's own money first, then each party's cut. Components end `_amount`
(always ex VAT), totals include VAT, rates end `_rate`:

| Level | Fields | Meaning |
|---|---|---|
| Booking | `nights`, `stay_amount`, `vat_rate`, `vat_amount`, `total_amount` | The stay's value (ex VAT), its VAT, and the total the guest is charged (`stay + vat`) |
| Calm | `commission_rate`, `commission_amount`, `commission_vat_rate/_amount`, `commission_total` | Calm's cut of the stay, **VAT ON TOP** (historical rows carry commission VAT 0 so old payouts never changed) |
| Host | `host_payout_amount` | `stay − commission_total` — THE payout number everywhere |

Cross-check on every booking: `total_amount = host_payout_amount + commission_total + vat_amount`.

`BookingPricingService` fills the derived fields at creation (also via a model
`creating` hook, so seeders/tests/every path agree). `Booking::hostNetMinor()`
returns `host_payout_amount`. Per-document issue timestamps live on
`financial_documents.issued_at`; the booking only carries `financial_completed_at`
(the documents-before-money gate). A future guest service fee returns as a fourth
full item (`service_fee_amount/vat_rate/vat_amount/total`) — its half-built columns
were removed rather than left as zero-filled inconsistencies.

## Document flow (brief §11)

1. **Guest pays** → movement `guest_payment` (Moyasar ref). No documents yet.
2. **Checkout + N hours** (`FINANCE_INVOICE_ISSUE_HOURS`, default 4) → the
   `FinalizeBookingFinances` sweep (every 15 min) issues, idempotently, per booking:
   - `guest_booking_invoice` (tax) + `host_commission_invoice` (tax) +
     `host_payout_statement` (internal), with printable lines;
   - movements `commission_withheld` (succeeded) + `host_payout_payable` (pending);
   - stamps `*_issued_at` + `financial_completed_at`. Unique
     `(source, subtype)` makes double-issuing impossible.
3. **Payout settles** → movement `host_payout` (provider `moyasar` + sequence
   ref when automatic; provider `bank` + the entered transfer ref when the
   admin marks it paid manually); payable → succeeded; سند صرف + host SMS in
   both paths. **Cancellations** (§14): before payment = nothing; paid-but-not-
   invoiced = `guest_refund` movement; after invoicing = credit notes for both invoices
   (originals flip to `credited`, never edited) + refund movement + reversals.

## Qoyod sync (flag `QOYOD_ENABLED`, default off)

When enabled, syncable documents are created as `pending_provider` and every sweep
pushes pending/failed ones: customer create/reuse (guests → `users.qoyod_customer_id`,
hosts → `host_tax_profiles.qoyod_customer_id`), `POST /2.0/invoices` (SAR decimal
strings, `tax_percent` from the booking snapshot — 0 pre-VAT-registration, 15 after),
`POST /2.0/invoice_payments` (guest → Moyasar clearing account, commission → settlement
offset account), credit notes on cancellation. Failures mark the document `failed` and
retry next sweep — an outage delays, never loses. PDF links come from
`GET /2.0/invoices/{id}/pdf` (expiring) — fetched fresh per request, never stored.

**سند صرف (payout voucher):** when a host payout SETTLES, `recordPayoutPaid` mints a
`host_payout_voucher` document, and the sweep mirrors it as `POST /2.0/receipts` with
`kind=paid` — money OUT of the Moyasar clearing account to the host contact, amount =
`host_payout_amount`, reference `{CB-ref}-PAYOUT`, bank ref in the description. This is
what makes the Moyasar clearing account reconcile in Qoyod (guest total in, host share
out, commission offset). Receipts have no PDF endpoint (`pdfUrl` returns null). Qoyod
`kind` accepts only `received` (سند قبض) / `paid` (سند صرف); receipts delete via
`DELETE /2.0/receipts/{id}` during cleanup.

**Moyasar identifiers:** invoices carry `metadata.booking_id` + `metadata.booking_reference`;
payouts carry `metadata` {booking_id, booking_reference, attempt} plus the CB-ref in
`comment` — both searchable in the Moyasar dashboard and echoed in webhooks.

## API (mobile)

- `GET /api/finance-documents` — the viewer's OWN documents (guest: booking invoices /
  credit notes; host: commission invoices + credit notes + payout statements),
  paginated (`items` + `pagination`). Fields incl. `document_subtype`, amounts,
  `booking_reference`, `has_pdf`. Voucher-type documents (سند صرف/قبض cash
  mirrors) are NEVER listed — internal bookkeeping only. Optional `?booking_id={uuid}` scopes to one booking — powers the
  "View invoice" button on the booking detail screen (someone else's booking id
  → empty list, never a leak).
- `GET /api/finance-documents/{id}/pdf-url` → `{url}` — fresh expiring Qoyod link.
  Someone else's document → **404** (existence never leaks; admin allowed).
  Not synced / statement → **409**.
- Booking payloads: a cancelled PAID booking carries
  `refund: {refunded, amount, amount_minor}` (full-refund policy — equals the
  guest total) so the app can show "SR X was refunded to your card".

## Automatic host payouts (Moyasar Payouts)

Payouts are automatic by default. Mode flag: `MOYASAR_PAYOUTS_MODE` (`manual`
default / `auto`); `manual` simply PAUSES the sweep — payouts queue safely
until Moyasar Payouts is activated in production. The rule is **documents before
money** — a booking is *payable* (`Booking::isPayable()`) only when: completed +
`payout_status=not_paid` + `financial_completed_at` set (invoices issued) + past
`checkoutAt + payout_hold_hours` (Setting, default 24h — the dispute window,
admin-editable, not exposed to the app).

**Manual settlement fallback** (Moyasar Payouts requires the company source
account at a supported bank — e.g. Al Rajhi; if ours isn't, or while activation
is pending): the admin transfers from the real company bank, then on the
booking page enters the bank reference and clicks **Mark paid manually**
(`HostPayoutService::markPaidManually`). Same trail as an automatic settle —
`host_payout` movement (provider `bank`), سند صرف voucher, host SMS. Guards:
completed + invoiced + `not_paid`; refused while a Moyasar transfer is
`processing` (double-pay protection). Allowed on failed rows (clears the
failure). The automatic queue/Retry behavior is unchanged.

Flow (auto mode): `ProcessDuePayouts` (15 min) → `HostPayoutService::executeDuePayouts`
→ `POST /v1/payouts` (amount = `host_payout_amount` halalas, destination = host IBAN
`users.bank_account` + beneficiary name (`users.bank_account_name` — the name as
written at the bank — falling back to the profile name; Moyasar documents NO
name-vs-IBAN rule, receiving banks screen at their own discretion) + mobile,
city `MOYASAR_PAYOUT_DEFAULT_CITY`) → row goes
`payout_status=processing` with `payout_id`. `ReconcileMoyasarPayouts` (10 min) polls:
`paid` → settle (paid_out_at, `payout_reference` = sequence number, `host_payout`
movement provider `moyasar`, payable → succeeded); `failed/returned/canceled` → back to
`not_paid` with the bank's reason in `payout_failure` (the sweep deliberately skips
failed rows — they wait for an explicit admin Retry).

Missing IBAN is a WAIT state, not a failure: the sweep skips the row without
setting `payout_failure` (the check is first, so re-checking costs nothing),
sends the host one "add your IBAN to receive SR X" notification per day
(`host_iban_needed`, deduped in `NotificationService::hostIbanNeeded`), and the
payout fires automatically on the first sweep after the host saves their IBAN —
no admin involvement. The admin finance panel shows "Waiting for the host to add
bank details". Only a missing host ACCOUNT (deleted user) records a failure.

Admin surface is BOOKING-CENTRIC (`/admin/bookings/{id}`): a finance panel shows the
payout state (upcoming / awaiting invoices / in hold until … / queued / processing /
paid / failed + **Retry**, which re-fires the automatic transfer with a fresh
sequence), the financial documents with status + fresh Qoyod **PDF** links
(`GET /admin/finance-documents/{id}/pdf`), and the money-movement trail. The bookings
list shows a red "Failed payouts" alert chip (`?payout_failed=1`) only when at least
one transfer needs a human. There is no payouts queue page.

Double-pay protection: the 16-digit Moyasar `sequence_number` is derived
deterministically from (booking id, `payout_attempts`) — a crashed or concurrent
create resends the SAME number and Moyasar rejects the duplicate. `payout_attempts`
advances only on a CONFIRMED bank failure (that sequence was consumed without moving
money), never on ambiguous timeouts. If a failure message ever says "duplicate
sequence", the payout likely exists at Moyasar — check the dashboard before retrying.

## Production go-live runbook (full launch, in order)

**Money in — Moyasar live**
1. Live `MOYASAR_SECRET_KEY` (+ live publishable key in the apps).
2. Set `MOYASAR_WEBHOOK_SECRET` and register the prod webhook URL
   (`/api/payments/moyasar/webhook`) in the Moyasar dashboard. The code logs a
   production warning if the secret is missing.

**Money out — Moyasar Payouts live**
3. Moyasar activates Payouts on the LIVE account (business approval), with the
   company source account at a SUPPORTED bank (e.g. Al Rajhi) — then one-time
   `POST /v1/payout_accounts` to register it → id into `MOYASAR_PAYOUT_ACCOUNT_ID`
   (`account_type=bank`, `properties.iban`, `credentials` = 6–15 digit
   `company_code` + X509 `cert` + RSA/EC `key` in PEM) → `MOYASAR_PAYOUTS_MODE=auto`.
   **Until then ship `manual`** — payouts queue safely and admins settle by
   hand via *Mark paid manually* (see fallback above). Field notes from the
   live API (**verified 2026-07-04**, sandbox transfer executed + reconciled):
   `destination.mobile` is required; purpose must be `payment_to_merchant`
   (IPS rejects several enum-valid purposes, e.g. `expenses_services`, after
   creation).

**Books — Qoyod**
4. Rotate the Qoyod API key (dev one was exposed during development) → prod env only.
5. Create the two products ONCE in the prod org (CALM-STAY / CALM-COMM) → ids
   into `QOYOD_PRODUCT_STAY_ID` / `QOYOD_PRODUCT_COMMISSION_ID`. Never delete
   them (Qoyod never reuses ids; a deleted product breaks every future invoice
   until reconfigured). Set the four account-mapping ids, `QOYOD_ENABLED=true`.
6. Accountant sign-offs before ZATCA onboarding: (a) tax-point timing —
   invoice at stay completion vs at payment (advance-receipt rule); (b) the
   commission offset-account treatment; (c) once ZATCA e-invoicing is live,
   approved invoices LOCK (credit notes only) — the flows already assume this.

**Comms**
7. `SMS_DRIVER` → real provider (creds already in env); the mock OTP `111111`
   disappears with the mock driver. `SMS_MOCK_PHONES` can keep a store-review
   account on the fixed OTP.

**Deploy infrastructure**
8. Prod `.env` from scratch — never copy the dev one. `APP_ENV=production`
   restores real job cadences; leave `FINANCE_INVOICE_ISSUE_HOURS` unset
   (defaults 4h); seeding gives `payout_hold_hours=24`.
   `QUEUE_CONNECTION=database` + supervised `queue:work` (notifications are
   queued jobs — sync would send SMS inline in requests), cron
   `* * * * * php artisan schedule:run`, and every deploy must restart
   queue/scheduler workers + `config:cache` (long-lived workers keep the env
   they booted with — hard-learned).
9. Merge to master, `php artisan migrate` (one prod-delta migration), then a
   real SR 1 smoke test: book → pay → webhook confirm + SMS → admin cancel
   (≥4 days out) → verify the real refund in the Moyasar dashboard.

## Refunds (automatic, full only)

Admin cancellation of a PAID booking refunds the guest IN FULL via Moyasar,
automatically — and is only allowed until `refund_days_before_checkin` (Setting,
default 4) days before check-in; after that the cancel is refused (422). No
partial refunds by design. Order of operations: Moyasar refund FIRST
(`MoyasarGateway::refundInvoice` — resolves the paid payment under the invoice,
skips if already refunded, so a crashed cancel can be retried safely), and only
on success the booking flips to cancelled + notifications + the Case B/C
bookkeeping (refund movement; credit notes when invoices were already issued).
A failed refund leaves the booking confirmed and untouched. Note: the window
means the admin flow can never cancel after checkout — the post-invoicing
credit-note path (Case C) remains as a defensive branch for future dispute
tooling.

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
| Product: Calm service fee (`CALM-FEE`) | 2 (dormant — created for the future service-fee item; not referenced by code today) |
| Product: Platform commission (`CALM-COMM`) | 3 |
| Moyasar clearing (Bank Current Account) | 8 |
| Commission settlement offset (Accounts payable) | 14 |
| Inventory (Main Branch) | 1 (default) |

1. Paid Qoyod plan → General Settings → generate API key (rotate if ever exposed).
2. Products + account ids above already exist; the accountant can remap the
   settlement account later (payments post fine to account 14).
3. Env: `QOYOD_ENABLED=true`, `QOYOD_API_KEY`, `QOYOD_PRODUCT_STAY_ID=1`,
   `QOYOD_PRODUCT_COMMISSION_ID=3`,
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
admin pass, 409 pre-sync, 401), `AutoPayoutTest` (paused mode = zero HTTP, transfer +
settle with movements, bank-failure requeue + fresh sequence on retry, missing IBAN,
hold-window/documents gating, booking-page payout states + retry + failed filter),
`AdminBookingFinanceTest` (finance panel documents/movements/badges, admin PDF
redirect + no-pdf flash, admin-only).
