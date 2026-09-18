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


## Status

**Web: implemented** (2026-09-18). Mobile emits the same names. If this doc and
`resources/js/analytics.js` disagree, the code wins — update this doc.

## The contract

Names and property names are **identical across web and mobile** — that's the
whole point; different names mean two funnels and nothing to compare.

- **SDK:** `posthog-js` · **Host:** `https://eu.i.posthog.com` (EU; the US endpoint rejects the key)
- **Super property:** `platform = "web"` (mobile: `app_ios` / `app_android`). The only value that differs.
- `person_profiles: 'identified_only'`, `autocapture: false`, `capture_pageview: false`
- **Arrays are comma-joined strings** (`place_type_ids: "a,b"`). `clean()` in
  `analytics.js` does this centrally — call sites pass real arrays.

### Must-match funnel spine

```
view:home → search:submit → view:results → view:place → click:reserve → view:checkout → click:pay
```

plus the full `occasion:*` funnel.

## The events

### Home / search / results
| Event | Properties |
|---|---|
| `view:home` | — |
| `click:category` | `type` |
| `click:view_all` | `section` (`home_list`, `description`, `amenities`, `reviews`, `photos`) |
| `search:open` | `source` (`nav`, `category`) |
| `search:submit` | `city_id, place_type_ids, city_area_ids, amenity_ids, price_min, price_max, guests, check_in, check_out` |
| `search:close` | — (suppressed when the sheet closed via apply) |
| `view:results` | `city_id, check_in, check_out, guests, results_count` |
| `results:back` | — |
| `filter:open` / `filter:close` | — (close suppressed when it closed via apply) |
| `filter:apply` | `city_id, place_type_ids, city_area_ids, amenity_ids, price_min, price_max, guests` |
| `scroll:results` / `end:results` | — |
| `click:like` | `place_id, liked` |

### Place / photos
| Event | Properties |
|---|---|
| `view:place` | `place_id, price_sar, source` (`home` \| `results` \| `external`) |
| `scroll:photos` | `place_id, surface` (`card`, `detail`) |
| `end:photos` | `place_id` |
| `photo:open` | `place_id` |
| `click:share` | `place_id` |
| `click:reserve` | `place_id, price_sar` |
| `view:dates` | `place_id` |
| `end:place` | `place_id` |

### Checkout / auth
| Event | Properties |
|---|---|
| `view:checkout` | `place_id, total_sar, nights` — fired after the quote resolves, not on paint |
| `checkout:cancel` | `place_id` |
| `click:continue_checkout` | `place_id` |
| `click:pay` | `booking_reference, total_sar` |
| `login:open` | `reason` (`occasion`, `generic`) |
| `login:otp_sent` / `login:otp_submit` | — |

### Tabs / lists
| Event | Properties |
|---|---|
| `view:favorites` · `view:bookings` · `view:account` | — |
| `view:status` | `booking_reference` |
| `view:occasions_list` | — (only when the list is non-empty) |

### Occasions wizard
| Event | Properties |
|---|---|
| `occasion:start` | — |
| `occasion:type` | `type` |
| `occasion:needs` | `needs` |
| `occasion:guests` | `range` (`1-10`, `11-25`, `26-50`, `51-100`, `101-200`, `201-500`, `500+`) |
| `occasion:submit` | `occasion_type, needs, guests, guests_range, venue_status, has_notes` |

### Deliberately not on web

- `scroll:feed` — a mobile feed diagnostic; no equivalent surface.
- `view:occasion_detail` — there is no occasion detail screen on web.
- `payment_completed` — **backend only**, from the Moyasar webhook (the source
  of truth). Never emit it from a client; a client can't know a payment settled.

## Identity

- `distinct_id` = **the Calm user UUID**, both platforms. Never a phone or email.
- `identify(uuid)` on OTP verify **and** on every authenticated page load (from
  the `calm-user-id` meta tag).
- `reset()` on logout — otherwise the next person on a shared device inherits
  the last one's identity. Wired as a delegated submit listener in `app.js`.
- Web-browse-then-app-book must merge into **one** person. Only the shared UUID
  makes that work.

## Once-per-session flags

`scroll:*` and `end:*` fire at most once per scroller per session, keyed on the
qualifying properties (`place_id`, `surface`, `direction`), so two carousels each
report once. Untamed, scroll alone would be most of our volume.

## `source` on `view:place`

How they reached the listing. Web derives it from `document.referrer`; mobile from whether the
screen came from a deep link or in-app navigation.

Three values only, matching mobile — `direct` was dropped, since a cold open
with no referrer is the same acquisition story as an external link.

| Condition | `source` |
|---|---|
| Same-origin referrer carrying `?city=` | `results` |
| Any other same-origin referrer | `home` |
| Another origin (wa.me, google…), or no referrer at all | `external` |

A session with `view:place { source: "external" }` and no `view:results` is a shared-link
visitor who never searched — a completely different acquisition story, and possibly our best
channel. The share button exists (`sharePlace()` in `resources/views/places/show.blade.php`), so
this case is real.


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

