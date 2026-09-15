# Feature plan: Behavioural event tracking (web + mobile)

> Status: **specified, not built** (2026-09-15). Single source of truth for BOTH platforms —
> web and mobile must send identical event names and properties or the funnels can't be
> compared. Tool: **PostHog Cloud (EU region)**. Session replay: **web only** for now.

## Context — the question this exists to answer

People install the app and don't book. We don't know why, and each possible cause needs a
completely different response:

| Stage | Symptom | Cause | What we'd do about it |
|---|---|---|---|
| **Traffic** | Almost nobody arrives | Nobody has heard of us | Marketing — adding listings changes nothing |
| **Selection** | They leave without opening a listing | Too few options where they're looking | Recruit hosts in specific cities |
| **Appeal** | Listings opened, no booking started | Price, photos, quality | Work with hosts on pricing/photos |
| **Trust** | Booking started, abandoned | New brand, no reviews, no reassurance | Reviews, guarantees, clearer pricing |
| **Checkout** | Payment attempted, failed | Technical | Fix it |

Diagnose **top-down and stop at the first break** — fixing Appeal is pointless if Traffic is 30
people a week.

### Three of the five are already answerable with no tracking

- **Traffic** → App Store Connect / Play Console install counts.
- **Trust** → `bookings` rows are created BEFORE payment, so `pending_payment` / `expired`
  rows literally are the abandoned checkouts:
  `select booking_status, count(*) from bookings group by booking_status;`
- **Checkout** → rows that got a `payment_id` (reached Moyasar) but still expired.

Tracking exists mainly to answer **Selection** and **Appeal**, and to make all five continuous.

## Design principles

1. **Record observations, never conclusions.** `view:results { results_count: 0 }`, never
   `search_failed`. The moment a property encodes an opinion (`results_were_few: true`) we've
   frozen today's analysis into data we'll keep for years.
2. **Properties are measurements, not judgements.**
3. **Derive meaning at query time.** Most answers come from what follows what, not from single
   events. That's the deliberate trade: more work per question, far more flexibility.
4. **Never trust the client about money.** `payment_completed` is server-side only.
5. **If tracking breaks, nothing breaks.** Fire-and-forget, short timeout, never block the UI.

## Vocabulary — 4 types

| Type | Meaning | Rule of thumb |
|---|---|---|
| `view` | a screen was shown | anything with its own URL / deep-linkable screen |
| `click` | the user did something | actions inside a screen (buttons, modals) |
| `scroll` | they scrolled a list at all | **binary flag**, no depth |
| `end` | they reached the end of a list | **binary flag** |

Event name = `type:target`, e.g. `view:place`. Works for both vertical lists and horizontal
carousels — the target says which.

## The events

Twelve total. Eleven from the client, one from the server.

| Event | Properties | Web trigger | Mobile trigger |
|---|---|---|---|
| `view:home` | — | load of `/` | home screen shown |
| `view:results` | `city_id`, `check_in`, `check_out`, `guests`, `results_count` | results mode / `/?city=…` | results screen shown |
| `view:place` | `place_id`, `price_sar`, `source` | load of `/places/{id}` | listing screen shown |
| `view:checkout` | `place_id`, `total_sar`, `nights` | load of `/book/{place}/checkout` | checkout screen shown |
| `view:status` | `booking_reference` | load of `/book/status/{booking}` | payment-return screen |
| `click:reserve` | `place_id`, `price_sar` | «احجز الآن» opens the booking sheet | same |
| `click:pay` | `booking_reference`, `total_sar` | «متابعة للدفع», leaving for Moyasar | same |
| `scroll:results` | — | scrolled the results list | same |
| `end:results` | — | reached the bottom / no more pages | same |
| `scroll:photos` | `place_id` | swiped the photo carousel | same |
| `end:photos` | `place_id` | reached the last photo | same |
| `payment_completed` | `booking_reference`, `total_sar`, `place_id` | — | — |

`payment_completed` fires **server-side** from the Moyasar webhook path in
`app/Services/Booking/BookingService.php`, where the booking flips to Confirmed. Use the guest's
user UUID as `distinct_id` so it joins the client journey. Fire-and-forget with a short timeout —
it must never slow a webhook.

### Deliberately NOT events

- `click:place` — `view:place` fires on the page/screen itself, so it catches shared links and
  deep links too. A card tap that never lands is not interesting.
- `click:dates` — confirming dates IS what produces `view:checkout`.
- `view:login` — derivable: if the user was anonymous at `click:pay`, the login modal appeared.
- `dates_unavailable` — that's a conclusion. `click:reserve` with no following `view:checkout`
  records the same fact without deciding the reason.

## `source` on `view:place`

How they reached the listing. Web derives it from `document.referrer`; mobile from whether the
screen came from a deep link or in-app navigation.

| Condition | `source` |
|---|---|
| Same origin, session already had a `view:results` | `results` |
| Same origin, no results this session | `home` |
| No referrer | `direct` |
| Any other origin (wa.me, google…) | `external` |

A session with `view:place { source: "external" }` and no `view:results` is a shared-link
visitor who never searched — a completely different acquisition story, and possibly our best
channel. The share button exists (`sharePlace()` in `resources/views/places/show.blade.php`), so
this case is real.

## Identity

- `distinct_id` = **the Calm user UUID**, on both platforms. Never the phone number.
- Anonymous before login; call `identify(uuid)` on OTP verify and on any authenticated page load.
- `reset()` on logout.
- A person who browses on web and books in the app must merge into **one** person. That only
  works if both platforms identify with the same UUID.

## Super properties

Registered once at init, attached to every event automatically:

```
platform = web_desktop | web_mobile | app_ios | app_android
```

PostHog's own `$device_type: "Mobile"` covers both mobile-web AND the native app — conflating
those would defeat the whole exercise, since the core question is app-vs-web conversion. Hence an
explicit property.

Identity is shared across platforms; **platform lives on the event**, so one person's timeline can
read `view:place` on `web_mobile` → three days later → `click:pay` on `app_ios`.

## Configuration

| Setting | Value | Why |
|---|---|---|
| Region | **EU** | PDPL comfort |
| `autocapture` | **off** | The single biggest cause of blown free tiers and unreadable data |
| `capture_pageview` | off (manual) | The guest web behaves like an SPA |
| `person_profiles` | `identified_only` | Anonymous events still record; no profile per bounce |
| Session replay | **web only** | Mobile replay is newer, heavier on data/battery, and needs extra store disclosure |

Free tier (~1M events/month) is many times our realistic volume.

## Scroll throttling — required

Scroll fires constantly, especially on photo carousels. Send **one** `scroll:<target>` per
scroller per session — set a flag in the buffer on first scroll, flush once. Untamed, scroll alone
would be most of our volume.

Same for `end:<target>`: once per scroller per session.

## Privacy

- **Mask the phone and OTP inputs** in replay before recording anything
  (`resources/views/partials/_web_login_modal.blade.php`). Mask all inputs by default.
- Payment happens on Moyasar's hosted page — a different origin, so card details are never
  recorded. Good by construction.
- No PII in properties. `user_id` is enough to join back to our own tables.
- **Mobile needs store disclosure** even without replay: App Store privacy labels and Google Play
  Data Safety must declare analytics/usage data. An undeclared SDK is a review-rejection risk.

## Reading the data

Every one of the five stages, from flags:

| Session shows | Diagnosis |
|---|---|
| `view:results` with `results_count: 0` | **Supply gap** — nothing to show for that city/date |
| `view:results`, no `scroll:results` | Didn't even look — first screen didn't appeal |
| `scroll:results`, no `end:results` | Browsed and stopped — selection was fine, something else stopped them |
| `end:results` | **Ran out of options** — Selection problem |
| `view:place`, no `scroll:photos` | Cover photo or price killed it instantly |
| `end:photos`, no `click:reserve` | Saw every photo and still said no — price or trust |
| `click:reserve`, no `view:checkout` | **Dates didn't work** |
| `view:checkout`, no `click:pay` | Price shock |
| `click:pay`, no `payment_completed` | Technical / payment failure |

Derived for free from timestamps and sequence, with no extra events:

- time on a listing = gap between `view:place` and the next event
- how many listings they tried = count of `view:place` in the session
- came back from a listing = `view:place` followed by another `view:place`, no `click:reserve` between

### Per-listing supply management

Because `view:place` carries `place_id`, every listing can be ranked by view → `click:reserve`
rate. At ~30 listings that's one screen you can act on:

> "40 views, 0 reserves" → that host's photos or price are wrong.
> "8 views, 3 reserves" → clone whatever it's doing.

## Naming discipline

**Keep the event names and target strings in one constants file per platform, mirrored.** If web
sends `view:place` and mobile sends `place_view`, there are two funnels and no way to compare
them. This is the single most likely way this goes wrong.

## Implementation order

| Phase | Work | Rough effort |
|---|---|---|
| 1 | Web: PostHog init, masking, `identify`, session replay | ~1 hr |
| 2 | Web: the eleven client events | ~2 hr |
| 3 | Mobile: the same eleven events | ~half day |
| 4 | Server: `payment_completed` from the Moyasar webhook | ~1 hr |

Web has a shortcut: the guest UI already dispatches semantic window events
(`calm-search-apply`, `calm-filters-apply`, `calm-open-booking`, `calm-open-login`), so one
listener bridges several of these without touching call sites.

## Open items

- Mobile framework confirmed? (React Native / native / Flutter — affects SDK choice only; events
  are supported everywhere.)
- Whether to add `scroll:place` (how far down the listing they read) — same shape, easy to add later.
- Whether to dual-write to our own `events` table for ownership. Not now; the calls should sit
  behind a thin wrapper so the vendor isn't hard-coded across the app, which makes this a later
  decision rather than a rewrite.
