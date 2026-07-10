# Booking & Payment Test Cases — expected records in every system

Every booking/payment scenario: how to trigger it, what should happen by
correct business logic, and EXACTLY what should exist afterwards in the local
DB (bookings row, financial_documents, financial_movements, notifications),
in Moyasar, and in Qoyod. Use it as the checklist for manual test rounds and
as the reference when something looks wrong.

**Local-testing context:** OTP mock code `111111` (mock SMS driver) · Moyasar
sandbox test card `4111 1111 1111 1111` (any future expiry/CVV) · SMS visible
in `storage/logs/laravel.log` as `[SMS MOCK]` · local scheduler runs every
pipeline job each minute (`$fast` in routes/console.php) · Moyasar sandbox
payouts settle in ~5 min.

**Money model (one rule):** `_amount` columns are ex-VAT components, totals
include VAT, `_rate` columns are percentages. Invariant on every paid booking:
`total_amount = host_payout_amount + commission_total + vat_amount` (halalas).

---

## 1. Golden path — book → pay → stay → automatic payout

**Trigger:** guest registers, picks dates, pays with the test card; checkout
passes (backdate if needed); scheduler does everything else.
**Host bank details:** IBAN (`SA` + 22 digits) + optional **account-holder
name** — the transfer's beneficiary name uses the holder name when set,
otherwise the profile name (Moyasar documents no name-vs-IBAN rule; accuracy
avoids bank-side returns).

| Stage | bookings row | financial_documents | financial_movements | Notifications | Moyasar | Qoyod |
|---|---|---|---|---|---|---|
| Created | `pending_payment`, `payment_status=initiated`, money snapshot frozen (stay/vat/commission/host net), `expires_at` ≈ +10 min | — | — | — | Invoice `initiated`, metadata `booking_id` + `booking_reference`, expires 1 min before the hold | — |
| Paid | `confirmed`, `payment_status=paid`, `confirmed_at` set, `expires_at` NULL | — | `guest_payment` ✓succeeded (provider moyasar, txn = invoice id) | guest "booking confirmed" + host "new booking" | Invoice `paid` + one payment under it | — |
| Invoiced (checkout + issue-gate) | `completed`, `financial_completed_at` set | `guest_booking_invoice` issued · `host_commission_invoice` issued · `host_payout_statement` issued (internal) | + `commission_withheld` ✓succeeded · `host_payout_payable` pending | — | — | Guest + host contacts; invoice `CB-x-G` (stay + 15% VAT) + **سند قبض** into the Moyasar clearing account; invoice `CB-x-C` (commission + VAT on top) + **سند قبض** into the settlement offset account |
| Payout settled | `payout_status=paid`, `payout_paid_at`, `payout_reference` = 16-digit sequence | + `host_payout_voucher` issued | `host_payout` ✓succeeded (provider **moyasar**) · payable → succeeded | host "your payout was sent (SR X)" | Payout `paid`, metadata `booking_id`/`booking_reference`/`attempt` | **سند صرف** (`kind=paid`) from the Moyasar clearing account → host contact, bank ref in description |

**End census per successful booking:** Local = 1 booking · 4 documents ·
4 movements · 3 SMS. Moyasar = 1 invoice + 1 payment + 1 payout. Qoyod =
2 contacts · 2 invoices · 2 سند قبض · 1 سند صرف.
**Reconciliation rule:** the Moyasar clearing account in Qoyod nets to exactly
Calm's commission+VAT per booking (guest total in − host share out − offset).

## 2. Never paid — hold expires
**Trigger:** create a booking, close checkout, wait ~10 min.
**Logic:** a non-event — no tax point, no money, no accounting anywhere.
- bookings: `expired`, payment_status `initiated`/`expired`, dates free.
- Documents/movements: NONE. Notifications: none (abandoned cart ≠ cancellation).
- Moyasar: invoice expires unpaid. Qoyod: nothing.

## 3. Guest aborts payment (cancel the pending hold)
**Trigger:** cancel button on the pending booking.
Same as #2 but instant; Moyasar invoice `canceled`; dates free immediately.

## 4. Card declined, then retried successfully
**Trigger:** fail the payment (declined test card / fail 3DS), retry with the
good card on the SAME checkout link within the hold.
**Logic:** failed attempts leave no trace; ends exactly like #1-Paid.
- Moyasar: ONE invoice, failed attempt(s) + one `paid` payment under it.

## 5. Paid at the last second / webhook missed
**Trigger:** pay ~30s before the hold expires (or webhook unreachable).
**Logic:** a paid booking is NEVER expired — every path (webhook, app poll,
expiry sweep) re-fetches the invoice from Moyasar as source of truth.
End state identical to #1-Paid.

## 6. Paid amount ≠ quoted amount (tamper/bug)
**Trigger:** not reachable from the UI — covered by automated tests.
**Logic:** money we never agreed to take → auto-refund IN FULL, never confirm.
- bookings: `expired`; no docs/movements; loud log line.
- Moyasar: refund on the payment. Qoyod: nothing.

## 7. Double payment attempt
**Trigger:** reopen the same checkout URL after paying.
**Logic:** Moyasar shows it paid; no second charge; no duplicate records anywhere.

## 8. Race — two guests, same dates
**Trigger:** two accounts book the same dates simultaneously.
**Logic:** the row lock serializes them: the second gets "dates unavailable"
while the first hold lives; after an unpaid hold expires the dates free again.
Only ever 1 booking + 1 Moyasar invoice for the winner.

## 9. Admin cancels PAID booking ≥ 4 days before check-in (Case B)
**Trigger:** book + pay with far-out dates → admin cancel (either actor).
**Logic:** full refund fires BEFORE the cancel commits; nothing was invoiced,
so there is NOTHING to reverse in Qoyod.
- bookings: `canceled_by_admin`/`canceled_by_host`, `canceled_at` set; dates free.
- Documents: 2 internal vouchers — `guest_payment_receipt` + `guest_refund_voucher`
  (never shown on mobile). Movements: `guest_refund` ✓succeeded (full total).
- Notifications: guest + host cancellation SMS.
- Guest API: the booking now carries `refund: {refunded: true, amount, amount_minor}`
  — the app shows "SR X was refunded to your card".
- Moyasar: refund (full) on the payment — verify in the dashboard.
- Qoyod: NO tax documents (correct — no tax point), but BOTH cash legs are
  mirrored on the guest contact: **سند قبض** (payment in) + **سند صرف** (refund
  out), same amount, Moyasar clearing account → nets to zero, and the account
  matches the bank statement line-by-line.

## 10. Cancel attempt < 4 days before check-in
**Trigger:** paid booking, check-in ≤ 3 days away → admin cancel card.
**Logic:** refused — UI shows the disabled window notice; server 422s.
ZERO records change in any system. (Setting: `refund_days_before_checkin`.)

## 11. Cancel an unpaid-confirmed booking
**Trigger:** rare state (support-staged) → admin cancel.
**Logic:** cancels + notifies both parties, no refund call, no finance records.

## 12. Cancellation AFTER invoicing (Case C — defensive path)
**Trigger:** not reachable via admin UI (refund window closes before checkout);
exercised via the finalizer directly.
**Logic:** documents exist → the reversal must be complete in BOTH ledgers.
- Documents: + `guest_booking_credit_note` (full total) · `host_commission_credit_note`
  (full commission) · `guest_refund_voucher` (full total); original invoices → `credited`.
- Movements: `guest_refund` ✓succeeded; `commission_withheld` + `host_payout_payable` → reversed.
- Moyasar: refund on the payment.
- Qoyod: credit notes ×2 **plus سند صرف (`kind=paid`) to the GUEST contact**
  from the Moyasar clearing account for the refunded amount — the account
  still reconciles after the refund.

## 13. Payout — host has no IBAN (wait state, NOT a failure)
**Trigger:** complete a booking for a host without bank details.
**Logic:** money waits for the HOST, not for an admin.
- bookings: stays `not_paid`, **`payout_failure` NULL** (sweep keeps re-checking free).
- Notifications: host gets "add your IBAN to receive SR X" — at most ONCE per
  day, repeats daily until fixed.
- Admin: payout state shows "Waiting for the host to add bank details"; NO Retry button.
- After the host saves the IBAN: pays automatically on the next sweep — zero admin action.
- Moyasar/Qoyod: nothing until actually settled.

## 14. Payout — bank rejects/returns the transfer
**Trigger:** hard to force in sandbox; happens live.
**Logic:** money returned to the Moyasar balance; needs a human decision.
- bookings: back to `not_paid`, `payout_failure` = bank reason, `payout_attempts`+1.
- Admin: red failure box + **Retry** (re-fires with a FRESH sequence number —
  the consumed one is burned; double-pay impossible).
- No voucher, no `host_payout` movement (money never moved). Qoyod: nothing.

## 15. Payout below Moyasar minimum (SR < 1)
**Trigger:** booking whose host net < 100 halalas.
**Logic:** local failure "below the Moyasar minimum", no API call; admin
Retry (or manual mark-paid) after resolving. Data anomaly — needs eyes.

## 16. Payout — host account deleted
**Logic:** hard failure `Host account missing.` — human must resolve.

## 17. Manual mark-paid (Moyasar Payouts unavailable / manual mode)
**Trigger:** admin transfers from the company bank, then on the booking page
enters the bank reference → **Mark paid manually**.
**Logic:** IDENTICAL finance trail to an automatic settle, different provider.
- Guards: booking must be completed + invoiced + `not_paid`; REFUSED while a
  Moyasar transfer is `processing` (double-pay protection); allowed on failed
  rows (clears the failure). Works in manual mode — that's its purpose.
- bookings: `payout_status=paid`, `payout_reference` = the entered bank ref.
- Documents: + `host_payout_voucher` issued. Movements: `host_payout`
  ✓succeeded with **provider `bank`** + the bank ref; payable → succeeded.
- Notifications: host "your payout was sent".
- Moyasar: NOTHING (the transfer happened outside it). Qoyod: سند صرف as in #1.

## 18. Reviews gate (post-completion)
**Logic:** review allowed only on a COMPLETED stay by ITS guest; one review
per guest per place; new reviews land `under_review` (moderated).

## 19. Reconciliation sweep (run after any test round)
Check in Qoyod: per fully-settled booking the clearing account movements are
`+total_amount` (guest receipt) and `−host_payout_amount` (سند صرف) — net =
commission_total + guest VAT, which is exactly what Calm retains before
remitting VAT. Any drift = a missing document; find it via the booking's
finance panel in admin.

---

*Spec decided by business/accounting logic (2026-07-08) and verified to match
the system as built (459 automated tests + live Qoyod/Moyasar sandbox runs).
Policy items pending accountant sign-off: tax-point timing (invoice at
completion vs at payment) and the commission offset-account treatment — see
`feature-finance-qoyod.md`.*
