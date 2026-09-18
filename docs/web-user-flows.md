# Web user flows — every path a person can take

> Derived from `routes/web.php` and the controller guards (2026-09-17). Covers the
> guest web, host self-service and admin. Mobile mirrors the guest flow but has its
> own navigation; see `docs/guest-web-spec.md`.

## 1. Entry points — how someone arrives

| URL | Who | Notes |
|---|---|---|
| `/` | anyone | Home. With `?city=…` it renders **results mode** instead of browse. |
| `/places/{place}` | anyone | The most shared URL (the place page has a share button). 404 unless Active+Approved — **except** the owner/admin, who see it with a status banner. |
| `/lists/{placeList}` | anyone | A curated home row's full set. 404 if the list is inactive or has no visible places. |
| `/book/{place}` | anyone | **Second booking entry**: the all-in-one link a host shares. 404 unless live. |
| `/favorites` `/trips` `/account` | anyone | Public on purpose — they render for guests with an inline sign-in prompt. |
| `/about` `/terms` `/privacy` `/cancellation-policy` `/community-standards` `/faqs` | anyone | Static; also opened inside the app's WebView. |
| `/support` | anyone | Redirects signed-in users to `/account/support` (same content, dashboard chrome). |
| `/calm-after-payment` `/calm-back-payment` | anyone | Moyasar's return URLs **for the mobile app's WebView**, not the web funnel. |
| `/ical/places/{place}/{token}.ics` | machines | Airbnb/Gathern/Google polling. `hash_equals` on the token, 404 on mismatch. |

## 2. The main booking flow (browse → paid)

```
  /  (browse)
   │  click:search  ─────────────────────────────┐
   ▼                                             │ desktop opens
  /?city=…  (results)                            │ results in a NEW TAB
   │  scroll ─► end of list = ran out            │
   ▼                                             │
  /places/{place}   ◄── shared link / list page ─┘
   │
   ├─ mobile : «احجز الآن» ─► dates sheet ─► «التالي» ─┐
   └─ desktop: sticky box, pick dates ─► «احجز الآن» ──┤
                                                       ▼
                                      /book/{place}/checkout
                                                       │
                              ┌── guest? ── yes ──► login modal (OTP)
                              │                     reloads the SAME url,
                              │                     resumes signed in
                              ▼
                        «متابعة للدفع»  →  POST /book/{place}
                                            │ creates booking (10-min hold)
                                            ▼
                                   Moyasar hosted page
                                    │              │
                            paid ───┘              └─── back/abandon
                                    ▼                        │
                        /book/status/{booking} ◄─────────────┘  (?back=1)
                         polls until the webhook confirms
```

**Guards along the way**

- `/book/{place}/checkout` **redirects to the place page** if `check_in`/`check_out` are missing, malformed, inverted, or in the past.
- `POST /book/{place}` needs the JWT cookie, re-quotes server-side, and refuses a date clash, over-capacity, or a host booking their own place.
- `/book/status/{booking}` is **404 for anyone but the guest who booked**.
- `?back=1` (Moyasar's in-page back) re-verifies with Moyasar, then either confirms a genuinely-paid booking or releases the hold — never a false "pending" spinner.
- Paid amount ≠ quoted amount → booking expires and the capture is auto-refunded.
- Hold lapses (`moyasar.hold_minutes`, default 10) → the `ExpireStaleBookings` job, scheduled every minute, frees the dates.

## 3. The host-shared booking link (`/book/{place}`)

A single page that does everything the flow above spreads over several screens: place summary, big calendar, guests, inline name+phone OTP, then pay. Same `POST /book/{place}` and the same `/book/status/{booking}` ending.

Use it when a host sends a customer straight to payment. It does **not** require the customer to browse or even have an account first.

## 4. Auth

Two surfaces, one mechanism (OTP → JWT cookie):

- **Login modal** (preferred) — `calm-open-login` opens it anywhere; on success it reloads the current page, so the user resumes exactly where they were. This is what checkout, the heart button and the gated tabs use.
- **`/login` → `/login/verify`** — full-page fallback, `guest`-only. `?next=` is honoured but `safeNext()` rejects anything that isn't a same-origin relative path.
- **Logout** — `POST /logout`, blacklists the token.

Signing in never changes which page you're on, which is why the funnel survives it.

## 5. Gated tabs (guest-visible)

```
/favorites   guest → sign-in prompt   |  signed in → the places they hearted
/trips       guest → sign-in prompt   |  signed in → their own bookings
/account     guest → sign-in pitch    |  signed in → identity card + menu + logout
             + «سجل كمضيف» upsell        + host/admin mode switch when applicable
```

`/trips` → tapping a booking → `/bookings/{booking}` (detail; **guest or host of that booking only**, else 404).

Two dashboard lists are easy to confuse — the names read backwards:

| Route | Whose bookings | For |
|---|---|---|
| `/my-bookings` | bookings **this user made** | guest |
| `/bookings` | bookings **on this user's places** | host |

## 6. Host flows

```
/account ─ «سجل كمضيف» ─► /host-register  (multi-step wizard: details → photos
                            → attributes → units → rules; drafts autosave,
                            photos presign straight to S3)
                                │ submit
                                ▼
                        pending review ──► admin approves ──► live
                                │
/my-places  ◄───────────────────┘
   ├─ /my-places/{place}/edit          (re-submits for review on save)
   ├─ /my-places/{place}/availability  (block/unblock dates)
   │     └─ calendar sync: add/remove iCal feeds, sync now, rotate the export token
   └─ delete
```

Every host route is owner-checked (`authorizeOwner`); admins may also pass.

A host's other surfaces: `/bookings` (who booked my places), `/bookings/{booking}` and
`/financials` (documents + payouts).

## 7. Admin flows

All under `/admin`, behind `auth:api` + `admin` (a signed-in non-admin is redirected to `/profile`, an API/JSON caller gets 403).

- Dashboard
- Geography: countries → cities → city areas
- Taxonomy: place types, attributes + groups (merged drag-sort page)
- **Place review**: `/admin/places/{place}/review` → approve / reject / skip
- Guest review moderation: publish / block
- Bookings: search, view, cancel (refund window enforced)
- **Payouts** on a booking: retry, mark paid (bank or cash), pay now (early release)
- Finance document PDFs (fresh Qoyod link)
- Curated place lists (the home rows)
- Settings, users, FAQs

## 8. Where a journey can end (the drop-off map)

Everything worth measuring, and what each means — mirrors `docs/feature-analytics.md`:

| Ends at | Meaning |
|---|---|
| Home, no search | Didn't engage |
| Results, `results_count: 0` | **No supply** for that city/date |
| Results, no listing opened | Cards didn't appeal |
| Results, scrolled to the end | **Ran out of options** |
| Listing, no photo swipe | Cover photo or price killed it |
| Listing, all photos seen, no reserve | Interested, still said no |
| Reserve clicked, never reached checkout | **Dates didn't work** |
| Checkout, never paid | Price shock or trust |
| Moyasar, never confirmed | Payment failure — also visible as `pending_payment`/`expired` rows |

## 9. Desktop vs mobile differences

| | Mobile / tablet | Desktop (≥1024px) |
|---|---|---|
| Home chrome | app header + bottom tab bar | site header, no tab bar |
| Search results | 1–2 column grid, filters in a sheet | 2-column grid + sticky filters panel |
| Opening search/a place | navigates in place | **opens a new tab** |
| Place page | full-bleed hero, floating back/share/like, fixed reserve bar | site header, title row, two columns with a sticky booking box |
| Reserve | opens the dates sheet | picks dates inline in the box |
