# Calm — Guest Flow Web Port Spec

> A framework-agnostic specification for rebuilding the **guest** experience of the Calm mobile app
> (Expo / React Native) on web with visual + behavioral parity. Values are quoted from the mobile
> source (`constants/theme.ts`, `lib/*`, `data/*`, `app/*`). Map them to whatever web stack you use.
>
> **Scope:** guest only. Host mode is globally disabled in the app (`HOST_MODE_ENABLED = false`), so
> every host entry point is hidden — ignore host screens. The mobile app is **light-theme only**
> (`app.json` pins `userInterfaceStyle: "light"`).

---

## 1. Product & design principles

- **What Calm is:** a Saudi event-venue booking marketplace — chalets (شاليهات), rest houses
  (استراحات), camps (مخيمات), farms (مزارع) — rented for birthdays, gatherings, and parties.
- **Aesthetic:** an **Airbnb mobile clone, minus the Experiences and Services tabs.** Generous
  whitespace, large imagery, restrained typography.
- **Motion:** smooth and **bouncy** — spring physics for primary affordances, **never** linear
  ease-out. Press = scale-down spring; list items = translate-up + fade entrance.
- **Language:** **Arabic is the default locale**, English is a peer (not a fallback). The whole app
  is RTL-first.
- **Brand color:** coral `#F88379` (the one confirmed brand color).

---

## 2. Design tokens

Verified from `constants/theme.ts`. The app only ever renders the `light` palette
(`hooks/use-theme-color.ts` always returns `Colors.light`), so a pixel-match web build can ignore
dark values.

### 2.1 Colors

| Token | Value | Role |
|---|---|---|
| `coral` (brand) | `#F88379` | primary CTA, active tab, accents |
| `coralPressed` | `#E66E64` | pressed CTA |
| `coralDisabled` | `#FAB5AF` | disabled CTA |
| `text` | `#1A1A1A` | primary text |
| `textMuted` | `#6B7280` | secondary / muted text |
| `background` | `#FEFEFE` | app background |
| `surface` / `surfaceElevated` | `#FFFFFF` | cards, sheets |
| `border` | `#E5E7EB` | borders |
| `divider` | `#F3F4F6` | dividers **and** image placeholder bg |
| `success` | `#16A34A` | confirmed / positive |
| `warning` | `#F59E0B` | pending / amber |
| `danger` | `#DC2626` | destructive / errors |
| `info` | `#2563EB` | confirmed booking chip |
| `overlay` | `rgba(0,0,0,0.5)` | modal scrim |
| `tabIconDefault` | `#9CA3AF` | inactive tab icon/label |

**Literal values that recur outside the token set — replicate exactly:**

| Value | Where |
|---|---|
| `#000000` (pure black) | **card body text** (cards deliberately use pure black, not `text`) |
| `#F3F4F6` | image placeholder background on cards |
| `#EEEEEE` | generic skeleton fill |
| `#E5E7EB` | skeleton card/block pulse fill |
| `rgba(255,255,255,0.8)` | white tint over the blur on reserve bar + tab bar |
| `rgba(0,0,0,0.45)` | unliked heart fill |

### 2.2 Spacing (key = pixels)

The scale is **sparse** — only these steps exist (no 7, 9, 11, …). `Spacing[5]` (20px) is the
standard horizontal screen gutter.

| Key | 1 | 2 | 3 | 4 | 5 | 6 | 8 | 10 | 12 | 16 | 20 | 24 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| px | 4 | 8 | 12 | 16 | 20 | 24 | 32 | 40 | 48 | 64 | 80 | 96 |

### 2.3 Radius

| Name | xs | sm | md | lg | xl | xxl | pill |
|---|---|---|---|---|---|---|---|
| px | 4 | 8 | 12 | 16 | **24** | 32 | 999 |

All card images use `xl` = **24px**. RN applies `borderCurve: 'continuous'` (iOS squircle /
superellipse). CSS `border-radius` is a plain circular arc — to match the softer corner, either
accept the 24px arc or approximate a squircle (slightly larger radius / SVG mask).

### 2.4 Shadows (color `#000` unless noted)

| Name | CSS |
|---|---|
| card | `0 2px 8px rgba(0,0,0,0.06)` |
| sheet | `0 -4px 16px rgba(0,0,0,0.12)` |
| modal | `0 8px 24px rgba(0,0,0,0.18)` |
| bar (reserve + tab) | `0 0 25px rgba(0,0,0,0.05)` |
| coral CTA | `0 6px 12px rgba(248,131,121,0.3)` (colored) |

RN `shadowRadius` ≈ CSS blur; RN has no spread.

### 2.5 Springs

Reanimated `withSpring` presets (`damping` / `stiffness` / `mass`). These are physical spring params
and port **1:1** to any spring-based animation model (e.g. Framer Motion `{type:'spring', damping,
stiffness, mass}`). Initial velocity 0.

| Name | damping | stiffness | mass |
|---|---|---|---|
| `default` | 14 | 180 | 1 |
| `bouncy` | 10 | 200 | 1 |
| `gentle` | 22 | 200 | 1 |
| `snappy` | 30 | 400 | 1 |

---

## 3. Typography

### 3.1 Font families

- **Latin / English → Satoshi** (`.ttf`): Light, Regular, Medium, Bold, Black.
- **Arabic → thmanyahsans** (`.otf`): Light, Regular, Medium, Bold, Black.

The app selects a font by **named file**, not numeric `font-weight`. `fontFamilyFor(weight, locale)`
is a pure lookup: `locale === 'ar' ? ArabicFonts[weight] : Fonts[weight]`.

**Web:** register **10 explicit `@font-face` faces** (5 Satoshi + 5 thmanyahsans) and pick the face by
family name. Do **not** rely on `font-weight: 700` synthesis — synthetic bolding will drift from the
app. Font files are shared with web (mobile copies them from `../calm-web/public/fonts/`).

### 3.2 Type scale (`Type` / `typeFor`)

Size / line-height / letter-spacing per variant (weight is applied per-locale via `fontFamilyFor`):

| Variant | Weight | Size | Line-height | Letter-spacing |
|---|---|---|---|---|
| display | bold | 34 | 40 | -0.5 |
| title | bold | 28 | 34 | -0.3 |
| heading | bold | 22 | 28 | — |
| subheading | medium | 18 | 24 | — |
| body | regular | 16 | 22 | — |
| bodyMedium | medium | 16 | 22 | — |
| callout | medium | 15 | 20 | — |
| caption | regular | 13 | 18 | — |
| micro | medium | 11 | 14 | +0.4 |

### 3.3 Inline sizes used by high-traffic components

Many components set size/line-height inline rather than using a variant. Match these for a pixel port:

| Component | Sizes |
|---|---|
| Compact (square) card | all text 12 / 16 |
| Full listing card | title 15/20 bold, rating + description + specs 13/18, price 14/20 bold, stay-days line 12/16 |
| Section header | 18/24 bold |
| Reserve bar | price 17/22 bold, unit 12/16, weekend note 12/16 |
| Tab label | 11/14 medium |

---

## 4. RTL & i18n

### 4.1 How direction works (JS/style-driven — native RTL is OFF)

- The app **disables** native RTL at startup (`I18nManager.allowRTL(false); forceRTL(false)`), so the
  device language never flips layout and switching is instant with no restart.
- Direction is applied **once at the root** via a Yoga style: every screen gets
  `direction: isRTL ? 'rtl' : 'ltr'`.
  **Web equivalent: `<html dir="rtl" lang="ar">` at the root**, toggled to `ltr`/`en`.

### 4.2 Text alignment & logical properties

- `useRtlText()` returns a constant `{ textAlign: 'left', writingDirection: isRTL ? 'rtl' : 'ltr' }`.
  Because native RTL is off, RN resolves `'left'` relative to `writingDirection`, so `'left'` means
  **start**. **Web equivalent: `text-align: start` + `direction: rtl|ltr`** — never hardcode `left`.
- Positioning already uses logical props (`insetInlineStart/End`, `marginStart/End`) — these map 1:1
  to CSS `inset-inline-start`, `margin-inline-start`, etc. **Prefer logical CSS properties throughout.**
- Row layouts flip with `flexDirection: isRTL ? 'row-reverse' : 'row'`. **On web you usually do NOT
  need `row-reverse`** — `dir=rtl` flips normal flex `row` automatically. Watch for a few places that
  deliberately invert DOM order (e.g. home section header renders `[moreChip, title]` with fixed
  `row-reverse`); on web, order the DOM naturally and let `dir` do the work.

### 4.3 Elements pinned LTR regardless of locale

Wrap these in an explicit `dir="ltr"` subtree:
- **Reserve bar row** — price on the (visual) left, Reserve CTA on the right, both locales.
- **Photo carousels** — RTL uses a mirror trick (`direction:'ltr'` + `scaleX(-1)` on scroller, each
  image un-mirrored). On web, prefer native RTL scroll-snap or replicate the double-flip.

### 4.4 Strings & bilingual fields

- **Inline string pattern:** `t({ ar: '…', en: '…' })` returns the active locale's string. Every
  user-facing string flows through this — no bare literals.
- **Backend split fields:** models carry `*_ar` / `*_en` (e.g. `title_ar`, `title_en`). Use a
  `pickLang(ar, en, fallback?)` helper: in `ar` prefer `ar || en || fallback`; in `en` prefer
  `en || ar || fallback` (cross-language fallback so a missing translation still shows something).
- Default locale is `ar`; `isRTL = locale === 'ar'`.

### 4.5 Digits, money, dates

- **Digits:** `toLocaleDigits(v, 'ar')` maps ASCII `0-9` → Arabic-Indic `٠١٢٣٤٥٦٧٨٩`; English passes
  through.
- **Money keeps Latin digits even in Arabic.** The `" SR"`-suffixed helpers force `en-US`, so
  "1,250 SR" renders identically in both locales. Only full-currency `Intl` contexts (`ar-SA`/`en-SA`,
  currency SAR) localize digits.
- **Dates force the Gregorian calendar for Arabic** (`ar-SA-u-ca-gregory`) because `ar-SA` defaults to
  Hijri. (On web `Intl` is reliable; keep `-u-ca-gregory`.) Display timezone is **Asia/Riyadh**.

---

## 5. Shared components

### 5.1 Press-scale (the universal tap animation)

Every pressable wraps this. Press-in: `scale → scaleTo`; press-out: `scale → 1`; both with the
**bouncy** spring (damping 10, stiffness 200, mass 1). Per-use `scaleTo`:

| Target | scaleTo |
|---|---|
| default | 0.96 |
| compact cards | 0.98 |
| full listing card | 0.985 (with a 120ms press delay) |
| like button | 0.86 |
| floating circle button | 0.88 |

**Web:** `whileTap={{ scale }}` (or JS) with the bouncy spring. Native fires a haptic on press — skip
on web. This is the single most important interaction to get right for the "bouncy feel."

### 5.2 Entrance animation (lists / sections)

Start `opacity: 0`, `translateY: 10px`. After the splash is gone, run after a per-item `delay` (ms):
- opacity → 1 via timing **180ms, ease-out quad**
- translateY → 0 via spring **{ damping 14, stiffness 300, mass 0.6 }**

Stagger sibling items with an incrementing `delay`. (Home uses e.g. category tiles `140 + i*50`,
section headers `440 + idx*300`, cards `headerDelay + 40 + index*60`.)

### 5.3 Cards

**Compact square card** (home carousels, wishlist grid):
- Fixed **158×158** image, radius 24 continuous, `overflow:hidden`, bg `#F3F4F6`.
- Heart overlay absolute at `top:12`, `inset-inline-start:12`; optional badge at `inset-inline-end:12`.
- Meta: `padding-top:8`, `gap:3`; three lines all 12/16, color `#000`: title (medium), location/specs
  (light), price (medium).

**Full-width hero card** (results, place-list, wishlist list):
- Width = screen − 40 (20px gutters). Image `aspect-ratio: 1.15`, radius 24 continuous, bg `#F3F4F6`.
  Card `margin-bottom: 24`.
- Horizontal paging photo carousel; heart size 32 at top/inset-start 12; badge at inset-end 12.
- **Pagination dots:** inactive `width:5, opacity:0.55`; active `width:14, opacity:1`; `height:5,
  radius:3`, white, `gap:4`, centered, `bottom:12`. Animate width+opacity on page change.
- Meta: `padding-top:12`, `gap:4`. Title row `space-between`, center, `gap:8`. Title 15/20 bold `#000`;
  rating = star 13px `#1A1A1A` + `"4.9"` bold 13/18 + `(count)` regular 13/18 muted; description
  13/18 muted; specs 13/18 light; price 14/20 bold `margin-top:2`; stay-days 12/16 medium `#6B7280`
  `margin-top:1`.

### 5.4 Button

- `border-radius: 999` (pill), row, centered.
- Variants: **primary** bg coral (disabled → coralDisabled), fg `#FFFFFF`; **secondary** bg `#1A1A1A`
  fg white; **ghost** transparent, fg text, `1px` border `#E5E7EB`.
- Sizes: **lg** pad 16/24, font 17 · **md** pad 12/20, font 16 · **sm** pad 8/16, font 14.
- Disabled `opacity: 0.7`. Icons use `margin-inline-start/end: 8`.

### 5.5 Reserve bar (sticky listing CTA)

- Absolute bottom, full width. Background = **blur** + `rgba(255,255,255,0.8)` tint. Web:
  `backdrop-filter: blur(20px)` + that tint.
- Padding: inline 20, top 16, bottom `safeArea + 8`. Container shadow `0 0 25px rgba(0,0,0,0.05)`.
- Row **pinned LTR**, `gap:12`, price column `flex:1`: price 17/22 bold `text`; unit 12/16 muted
  `margin-top:2`; optional weekend note 12/16 medium coral `margin-top:3` (opens the day-prices sheet).
- **CTA:** bg coral, pad inline 48 / block 14.4, radius 15 continuous, coral shadow
  `0 6px 12px rgba(248,131,121,0.3)`, text 15/20 medium white; loading → white spinner size 20.

### 5.6 Tab bar (bottom)

- Absolute bottom, full width; blur + `rgba(255,255,255,0.8)` tint; shadow `0 0 25px rgba(0,0,0,0.05)`.
- Row, `padding-top:8`, inline 12, bottom `safeArea*0.75`. Each tab `flex:1`, centered,
  `padding-block:8`, `gap:4`. Icon 22. Active = coral `#F88379`; inactive = `#9CA3AF`. Label 11/14.
- Per-tab press spring: scale 0.94 with `{ damping 14, stiffness 380, mass 0.5 }`.
- **Four guest tabs:** Home (Explore) · Wishlist (Likes) · Bookings · Profile (Account).

### 5.7 Loaders

- **Skeleton pulse:** opacity oscillates `0.55 ↔ 1`, **850ms per half**. Web:
  `@keyframes pulse { 0%,100%{opacity:.55} 50%{opacity:1} }` `1.7s ease-in-out infinite`.
  Skeleton card mirrors the full card (image aspect 1, radius 24, bg `#E5E7EB`; three lines
  `height:14, radius:4`, widths 72% / 50% / 40%).
- **Spinner:** a thin ring — full faint circle + one solid colored arc (`border-top`), `border-width ≈
  12% of size`. Web: `@keyframes spin` `0.75s linear infinite`.

### 5.8 Other primitives

- **ThemedText** — base text; applies the variant scale + a tone color (default `text`, muted, coral,
  danger, success, link=coral).
- **Like button** — heart via press-scale 0.86; white stroke 1.8; fill coral when liked, else
  `rgba(0,0,0,0.45)`. Default size 30 (32 on full card).
- **Floating circle button** — glassy: blur + `rgba(255,255,255,0.15)` tint, radius = size/2, default
  size 38, press 0.88.
- **Section** — `padding: 24 20`; title 18/24 bold `margin-bottom:12`; optional top divider `1px`
  `#F3F4F6` with 20px inline margin.

---

## 6. Guest screen inventory

Route → file → purpose → UI (top to bottom) → actions/edges → API → states. Modal vs full is noted;
on web, "modal / sheet" = a dialog/drawer, "full" = a routed page.

### 6.1 Tabs

**Home / Explore** — `/(tabs)` · `app/(tabs)/index.tsx` · full
- Landing/discovery feed. Consumes the bootstrap payload (`useHomeData()`) — no direct fetch.
  Pull-to-refresh re-runs bootstrap + clears place caches.
- UI: blurred sticky header (logo + "Start search" pill) → greeting "Hello, {firstName}" → resume
  card → categories grid (first 3 place types) → curated carousels (`home.lists`, non-empty, sorted by
  `sort_order`) → floating "Start a new search" FAB.
- Edges: pill / FAB / category tile → `/search` (category passes `typeId`); carousel card →
  `/listing/{id}`; "view all" card → `/place-list`; resume card → pending booking / interest listing /
  last search.
- **Resume card** shows at most one, by priority: pending payment (needs `getPendingBookings`) →
  interest (local) → last search (local → `/results?resume=1`).

**Wishlist / Likes** — `/(tabs)/likes` · `app/(tabs)/likes.tsx` · full
- Saved places grid. `getFavorites({page, per_page:20})`, seeded from cache, refetch on focus.
- **AUTH GATE:** logged-out → "Sign in to see your saved places" prompt → `/login`.
- Unliking removes the card immediately. States: 4 skeleton cards first load; empty = big heart +
  copy; infinite scroll.

**Bookings** — `/(tabs)/bookings` · `app/(tabs)/bookings.tsx` · full
- List of the user's bookings. `getBookings({page, per_page:20})`, seeded from cache, signature-diff
  to avoid flicker, refetch on focus.
- **AUTH GATE:** logged-out → "Sign in to see your bookings" → `/login`.
- UI: blurred title header; rows grouped — "Awaiting payment" (pending pinned top) then others.
  Each row: thumb, title, status chip, live phase chip (Ongoing/Upcoming countdown), location, date
  range, total. **Excludes `expired`.** Tap → `/booking-info`.
- States: 5 skeleton rows first load; empty state; infinite scroll.

**Profile / Account** — `/(tabs)/profile` · `app/(tabs)/profile.tsx` · full
- Logged out: title + subtitle + black "Login" button → `/login`.
- Logged in: identity card (avatar, name/phone/gender/email/birth) + "Edit" → `/edit-profile`.
- Menu (all users): FAQ → `/page/faqs`; Contact us → `/support`; About/Terms/Privacy/Cancellation/
  Community → `/page/{slug}`. Logged in adds: Delete account → `/delete-account`; Log out → `/confirm`.
- (Language toggle + all host entry points exist but are gated off.)

### 6.2 Search funnel

**Search** — `/search` (optional `?typeId=`) · `app/search.tsx` · transparent modal (own scale/opacity
entrance, tap-scrim dismiss)
- Airbnb-style stacked-card query builder: **Where** (city) / **When** (calendar + Skip) / **Type**
  (multi-select) / **Area** (only if the city has areas). No fetch — reads bootstrap + selected city.
- "Search" → writes applied filters (cityId, typeIds, areaIds, amenityIds=[], price=null, guests=null,
  checkIn/checkOut) + saves last search → `replace('/results')`. Each new search **resets advanced
  filters.** "Clear all" resets the form.

**Results** — `/results` (optional `?resume=1`) · `app/results.tsx` · full
- Paginated results. `searchPlaces(params)` built from applied filters: `city_id`, `place_type_ids[]`,
  single `city_area_id` (only when exactly one area), `amenities[]`, `price_min/max`, `guests`,
  `check_in`/`check_out` (only when both set), `page`, `per_page:20`. Re-runs on a filter-version bump.
  Resume mode loads up to the saved page then scrolls to it.
- UI: blurred header — back (→ dismiss to home), center summary pill "Homes in {city}" + count (→
  `/search`), filter icon (→ `/filters`). Body: list of full listing cards (threads chosen dates into
  each card) → `/listing/{id}` with `startDate`/`endDate`.
- States: 3 skeleton cards while loading; empty = "No results" + "Adjust filters" → `/filters`;
  infinite-scroll footer skeleton.

**Filters** — `/filters` · `app/filters.tsx` · modal
- `getPlaceFilters(cityId)` → price bounds, guests bounds, place_types (+counts), areas, grouped
  amenities. UI: price range slider, guests stepper, type chips, area chips, grouped amenity chips
  (all with counts), footer "Show results".
- Apply → patch applied filters (only send price when narrowed inside bounds) → back. Loading spinner;
  error state.

**Cities** — `/cities` · `app/cities.tsx` · modal
- Search box + 2-col flag grid of cities; pick → set selected city + back. (Backs the global selected
  city; also used by the search "Where" card.)

### 6.3 Listing detail + sub-pages

**Listing detail** — `/listing/[id]` (params `startDate?`, `endDate?`) · `app/listing/[id]/index.tsx` ·
full (slide-from-right)
- `getPlace(id)`. Quote for chosen dates via `getPlaceQuote`. On Reserve, always refetch
  `getUnavailableDates`. Like via `likePlace`/`unlikePlace`.
- UI top→bottom: hero carousel (→ `/listing/[id]/photos`) → title + stats row (guests · rating ·
  city/area) → description (truncated → `/description` modal) → standalone amenity sections → "Space
  images" horizontal cards → highlighted amenities (top 3) → "Amenities & features" (first 5 →
  `/amenities`) → check-in/out times → reviews carousel + "Show all" (→ `/reviews`) → rules. Floating
  top bar: back, share, like. Sticky compact header fades in on scroll. Bottom **reserve bar**.
- **Reserve logic:** always refetch blocked dates; if a pre-selected range crosses a blocked night →
  `/booking/[id]/dates`; if a clean range is preselected → `/booking/[id]/summary`; if no dates →
  `/booking/[id]/dates`. **No auth gate here** (liking is gated; auth is enforced later at summary).
- States: skeleton while nothing cached; "Listing not found" fallback.

**Sub-pages:** photos gallery `/listing/[id]/photos` (grouped photo tour → `/photo-viewer`) ·
fullscreen `/photo-viewer` (swipeable zoomable pager) · `/listing/[id]/amenities` (modal, grouped, with
counts + descriptions) · `/listing/[id]/description` (modal, full text) · `/listing/[id]/reviews`
(modal — rating bars 5→1, average, per-review rows).

### 6.4 Booking funnel

**Dates** — `/booking/[id]/dates` (params `startDate?`, `endDate?`) · `app/booking/[id]/dates.tsx` ·
modal
- Pick/confirm the stay range against live availability. `getUnavailableDates` (prefetched); on Next
  `getPlaceQuote`.
- UI: header X + "{n} days" title + range subtitle; full-height calendar (blocked days greyed); sticky
  Next with spinner + error text.
- Logic: per-day **inclusive** (`days = diff + 1`). If the blocked set arrives after a pick, it drops
  an invalid start/end. On Next: if quote not bookable → surface reason (dates unavailable / guests
  over max / generic) and fold newly-blocked days into the greyed set; if bookable → close, then
  `/booking/[id]/summary` with `checkIn`/`checkOut`.

**Summary (Confirm & pay)** — `/booking/[id]/summary` (params `checkIn?`, `checkOut?`) ·
`app/booking/[id]/summary.tsx` · full, gestures disabled
- Review price + create booking + hand off to payment. `getPlaceQuote` on mount (never cached); on
  Continue `createBooking(placeId, {check_in, check_out, guests})`.
- UI: header X (leave-confirm) + "Confirm and pay"; listing summary card; check-in / check-out rows
  with "Change" (→ `replace('/booking/[id]/dates')`); price breakdown (Stay · days, VAT %, Total) from
  the quote; footer "Continue to payment" + legal note.
- **AUTH GATE (deferred):** on Continue, if logged out → set a resume marker + `/login`; on return it
  auto-resumes and continues. On success → `/booking/[id]/pay` with `bookingId`, `paymentUrl`, and the
  summary fields. 401 → `/login`; 422 → surface the first field error.
- Leaving (X / back) opens `/confirm` "Leave checkout?".

**Pay** — `/booking/[id]/pay` (params `bookingId`, `paymentUrl`, …) · `app/booking/[id]/pay.tsx` ·
full (slide-from-bottom), gestures disabled
- Embedded Moyasar hosted payment page (WebView on native → **iframe or full redirect on web**).
- **Redirect markers:** URL contains `calm-after-payment` → go verify; `calm-back-payment` → cancel
  the hold (`cancelBooking`) then close (or route to verify if it came back `confirmed`). The
  in-Moyasar back button goes straight to cancel; the app's own X opens `/confirm` "Cancel payment?".
  Loader overlay with a 6s safety timeout. **Never trust the redirect alone — always verify.**

**After-payment (verification)** — `/booking/[id]/after-payment` · `app/booking/[id]/after-payment.tsx`
· full (fade), gestures disabled
- Poll `getBookingPaymentStatus(bookingId)` up to **10× every 2s** (~20s grace for the bank→backend
  webhook); break as soon as status ≠ `pending_payment`.
- UI: Lottie loader/success/pending; title + subtitle per phase; order summary card (image, dates with
  exact times, guests, total — live poll, falling back to forwarded params). CTA fades in when the
  phase settles: confirmed → `/(tabs)/bookings`; pending → `/(tabs)` (home).

> Skip on web: `/booking/[id]/confirmation` is legacy and unreachable.

### 6.5 Auth

**Login** — `/login` (optional `?next=`) · `app/login.tsx` · modal
- Two-step phone → OTP. `requestOtp('phone', identifier)` → `verifyOtp('phone', identifier, code)` →
  sign in with `{token, user, expires_in}`.
- Step "phone": welcome, dial-code pill (→ `/country-picker`), phone input (Saudi validates
  `^5\d{8}$`; strips non-digits + leading zeros; normalizes Arabic/Persian digits to ASCII). Step
  "otp": 6-digit code, resend timer (from `expires_at`/`retry_after`, fallback 90s), verify.
- Post-login: back, then if `next` present → navigate there (used by summary resume).

**Country picker** — `/country-picker` · modal — search + 2-col flag grid; pick → set dial code + back.

### 6.6 Post-booking

**Booking info** — `/booking-info` · `app/booking-info.tsx` · modal
- Full booking detail. Reads the selected booking from a store. `getFinanceDocuments(bookingId)`;
  `getFinanceDocumentPdfUrl(docId)` → `/finance-document`; `deleteReview`.
- UI: header X + share + Support (→ `/support` with booking context); reference number; cover; title +
  status/phase chips; **pending → circular pay countdown to `expires_at`**; ongoing/upcoming → phase
  timer; details card (check-in/out with exact times, duration, guests); price card (subtotal, VAT,
  total); refund card; finance documents list; review block (leave/show/delete). Bottom bar: **"Pay
  now"** when payable → `/booking/[id]/pay`; else **"Open location"** (maps link) when confirmed and a
  `location_url` exists.

**Leave review** — `/leave-review` (param `bookingId`) · modal — 1–5 star picker with meaning labels +
optional comment (max 1000). `submitReview(bookingId, {rate, comment})` → back. "Checked before
published" hint.

**Finance document** — `/finance-document` (params `url`, `type?`) · modal — in-app PDF viewer (native
WebView; on web just open/embed the PDF URL). Title by type: invoice / credit note / refund voucher.

**Support** — `/support` (optional booking context) · sheet (40% detent) — WhatsApp / Email cards from
settings `support_phone` / `support_email`, prefilled with booking context.

### 6.7 Profile sub-pages

**Edit profile** — `/edit-profile` · modal — fields: name, gender chips, email, birth date (via date
picker). Avatar via image picker. Sends **only changed fields** (`updateProfile` or
`updateProfileWithAvatar`) → back.

**Delete account** — `/delete-account` · modal — two steps: warning intro → OTP confirm
(`requestOtp` → `deleteAccount(code)` → sign out). Active-bookings error → "Cancel bookings?" →
`/support`.

**Info page** — `/page/[slug]` · modal — hosted web content (WebView) for `faqs`, `about`, `terms`,
`privacy`, `cancellation`, `community`. On web, just render/route these pages directly.

### 6.8 Utility modals / sheets

| Route | Presentation | Purpose |
|---|---|---|
| `/confirm` | sheet (fit-to-contents, grabber) | Generic confirm dialog (title/message/confirm/secondary/destructive) driven by a store. Dismisses first, then runs `onConfirm` next frame. Used by log out, leave-checkout, cancel-payment, delete-review. |
| `/date-picker` | sheet | Native spinner date picker via a store `onPick` (birth date). |
| `/day-prices` | sheet | Per-weekday price table (Fri/Sat highlighted coral). Opened from the reserve bar's weekend note. |
| `/place-list` | full | "View all" list from a home carousel (store-fed); full listing cards. |

---

## 7. Navigation flow map

```
LAUNCH → splash (waits: fonts + bootstrap) → (tabs)

(tabs):  [Home]   [Wishlist*]   [Bookings*]   [Profile]        (* whole-screen auth gate → /login)

HOME
  search pill / FAB / category tile ─► /search
  carousel card ─► /listing/{id}
  "view all" ─► /place-list ─► /listing/{id}
  resume card ─► pending booking | interest listing | /results?resume=1

SEARCH  /search (Where · When · Type · Area)
  Search ─► set applied filters + save last search ─► replace /results

/results  (searchPlaces)
  header pill ─► /search      filter ─► /filters (getPlaceFilters) ─► back
  back ─► home                card ─► /listing/{id}?startDate&endDate

LISTING  /listing/{id}  (getPlace; quote if dates; getUnavailableDates on Reserve)
  hero / space images ─► /listing/{id}/photos ─► /photo-viewer
  show-more ─► /description | /amenities | /reviews
  weekend note ─► /day-prices        like ─► (gate) /login
  RESERVE:  clean preselected range ─► /booking/{id}/summary
            crosses blocked / no dates ─► /booking/{id}/dates

BOOKING
  /booking/{id}/dates  (getUnavailableDates, getPlaceQuote)
     Next (bookable) ─► /booking/{id}/summary
  /booking/{id}/summary  (getPlaceQuote, createBooking)
     Change ─► replace /booking/{id}/dates
     leave (X/back) ─► /confirm
     Continue:  if !authed ─► /login ─► (resume) ─► createBooking ─► /booking/{id}/pay
  /booking/{id}/pay  (Moyasar; cancelBooking on back)
     redirect calm-after-payment ─► /booking/{id}/after-payment
     redirect calm-back-payment / X ─► /confirm ─► cancel ─► back
  /booking/{id}/after-payment  (poll getBookingPaymentStatus ≤10×2s)
     confirmed ─► /(tabs)/bookings        pending ─► /(tabs)

BOOKINGS tab ─► row ─► /booking-info  (getFinanceDocuments)
     Pay now ─► /booking/{id}/pay      doc ─► /finance-document
     Support ─► /support               Open location ─► maps
     review ─► /leave-review           delete review ─► /confirm

PROFILE ─► /login | /edit-profile | /delete-account | /page/{slug} | /support
```

---

## 8. Business-logic contracts

### 8.1 API client

- **Base URL:** `EXPO_PUBLIC_API_URL ?? "https://calmapp.co"`.
- **Response envelope:** every response is `{ status: number, message: string, data: T }`. Unwrap and
  return `data`. An error is `!res.ok || status >= 400`.
- **Headers:** always `Accept: application/json` and `Accept-Language: <locale>` (default `ar`).
  `Content-Type: application/json` only with a body. Auth: `Authorization: Bearer <token>`.
- **401 → refresh:** on 401 (non-auth path, has token, not already retried), do a **single-flight**
  `POST /api/auth/refresh` (shared across concurrent 401s), then replay the original request once with
  the new token. **Sign out only when the refresh itself throws a definitive `401`.** Network / timeout
  / 5xx during refresh **keep the session** (a genuinely dead token is caught by the next real 401).
- **Proactive refresh:** on app foreground/mount, if within 60s of expiry, refresh best-effort;
  failure is swallowed (keep session).
- **Errors:** `ApiError { status, message, payload }`. A 422 payload is `{ errors: { field: [msg] } }`
  — surface the first field error.

### 8.2 Guest endpoints

| Function | Method + path | Returns |
|---|---|---|
| getSettings | GET `/api/settings` | `ApiSettings` |
| getCountries | GET `/api/countries` | `ApiCountry[]` |
| getCities | GET `/api/cities` | `ApiCity[]` (areas inline) |
| getPlaceTypes | GET `/api/place-types` | `ApiPlaceType[]` |
| getPlaceLists | GET `/api/place-lists` | `ApiPlaceList[]` |
| getMostLikedPlaces | GET `/api/places/most-liked` | `ApiPlace[]` |
| getPlace | GET `/api/places/{id}` | `ApiPlaceDetail` |
| searchPlaces | GET `/api/places/search?…` | `ApiPaginated<ApiPlace>` |
| getPlaceFilters | GET `/api/places/filters?city_id=` | `ApiPlaceFilters` |
| getUnavailableDates | GET `/api/places/{id}/unavailable-dates?from&to` | `ApiUnavailableDates` |
| getPlaceQuote | GET `/api/places/{id}/quote?check_in&check_out&guests?` | `ApiQuote` |
| createBooking | POST `/api/places/{placeId}/bookings` | `ApiBooking` |
| getBookingPaymentStatus | GET `/api/bookings/{id}/payment-status` | `ApiBooking` |
| cancelBooking | POST `/api/bookings/{id}/cancel` | `ApiBooking` |
| getBookings | GET `/api/bookings?page&per_page` | `ApiPaginated<ApiBookingListItem>` |
| getPendingBookings | GET `/api/bookings/pending` | `{ items: ApiBookingListItem[] }` |
| getFavorites | GET `/api/favorites?page&per_page` | `ApiPaginated<ApiPlace>` |
| likePlace / unlikePlace | POST / DELETE `/api/places/{id}/like` | `{ is_liked: boolean }` |
| submitReview | POST `/api/bookings/{id}/reviews` | `ApiBookingReview` |
| deleteReview | DELETE `/api/reviews/{id}` | `null` |
| getFinanceDocuments | GET `/api/finance-documents?booking_id=` | `ApiFinanceDocument[]` |
| getFinanceDocumentPdfUrl | GET `/api/finance-documents/{id}/pdf-url` | `string` |
| requestOtp | POST `/api/auth/otp/request` | `ApiOtpRequestResponse` |
| verifyOtp | POST `/api/auth/otp/verify` | `ApiAuthVerifyResponse` (token + user + expires_in) |
| refreshAuthToken | POST `/api/auth/refresh` | `ApiAuthRefreshResponse` |
| logoutUser | POST `/api/auth/logout` | `null` |
| getUser | GET `/api/user` | `ApiAuthUser` |
| updateProfile | PATCH `/api/user` | `ApiAuthUser` |
| updateProfileWithAvatar | POST `/api/user` (multipart) | `ApiAuthUser` |
| deleteAccount | DELETE `/api/user` | `null` |

**`searchPlaces` params:** `city_id` (required), `city_area_id?`, `q?`, `place_type_ids?: string[]`
(→ `place_type_ids[]`), `amenities?: string[]` (→ `amenities[]`), `price_min?`, `price_max?`,
`guests?`, `check_in?`, `check_out?` (both or neither), `sort?: "most_liked"|"price_asc"|"price_desc"`,
`page?` (1), `per_page?` (20).

### 8.3 Data shapes (TypeScript, from `lib/api.ts`)

```ts
type WeekDay = "sunday"|"monday"|"tuesday"|"wednesday"|"thursday"|"friday"|"saturday";

interface ApiPlace {
  id: string;
  title: string; description: string;
  title_ar?: string|null; title_en?: string|null;
  description_ar?: string|null; description_en?: string|null;
  rules_ar?: string|null; rules_en?: string|null;
  price: number;                              // base per-day, SAR integer (major units)
  per_day_prices: Record<WeekDay, number>;    // per-weekday, SAR integer
  check_in_time: string;                      // "HH:MM"
  check_out_time: string;
  checkout_next_day?: boolean;                // checkout the day AFTER the last booked day
  max_guests?: number|null;
  rules: string|null;
  cover_photo_url: string|null;
  photos: ApiPlacePhoto[];
  featured_photos?: ApiFeaturedPhoto[];       // ordered showcase; [0] = cover
  type: ApiPlaceType;
  city: ApiCity;
  city_area: ApiCityArea|null;
  latitude?: number|null; longitude?: number|null;
  likes_count: number;
  rating: { avg: number|null; count: number };
  is_liked: boolean;
  created_at: string;
}
interface ApiPlacePhoto { id: string; url: string; attribute_id?: string|null; sort_order?: number; featured_order?: number|null; }
interface ApiFeaturedPhoto { id: string; url: string; attribute_id: string|null; }
interface ApiPhotoGroup { attribute_id: string; attribute: {id;name_en;name_ar;icon}|null; min_sort_order: number; photos: {id;url;sort_order;featured_order}[]; }

interface ApiPlaceDetail extends ApiPlace {
  attributes: ApiPlaceAttribute[];
  photo_groups: ApiPhotoGroup[];
  reviews_recent: ApiPlaceReview[];
  host: { id: string; name: string; joined_at: string };
}
interface ApiPlaceAttribute { id: string; value: string|number|boolean|null; description: string|null; attribute: ApiAttributeDef; }
interface ApiAttributeDef { id; name_en; name_ar; icon: string|null; type: string; is_highlighted?: boolean; sort_order?: number; group: { id; name_en; name_ar; is_standalone?: boolean }; }
interface ApiPlaceReview { id; rate: number; comment: string|null; reviewer_name: string|null; reviewer_avatar_url: string|null; created_at: string; }

interface ApiPlaceType { id: string; name_en: string; name_ar: string; icon: string; }
interface ApiPlaceList { id; name_en; name_ar; description_en: string|null; description_ar: string|null; icon: string; sort_order: number; places: ApiPlace[]; }
interface ApiCountry { id; country_code; dial_code; name_en; name_ar; avatar; }
interface ApiCity { id; name_en; name_ar; avatar; country_id; areas?: ApiCityArea[]; }
interface ApiCityArea { id; name_en; name_ar; }

interface ApiPlaceFilters {
  city_id: string; currency: string;
  price: { min: number; max: number };
  guests: { min: number; max: number };
  areas: (ApiCityArea & { places_count: number })[];
  place_types: (ApiPlaceType & { places_count: number })[];
  amenities: { /* grouped amenity groups, each item carries places_count */ }[];
}

interface ApiQuote {
  place_id: string; check_in: string; check_out: string;
  days: number; guests: number|null; max_guests: number|null; currency: string;
  bookable: boolean; dates_available: boolean; guests_ok: boolean;
  unavailable_dates: string[];
  breakdown: { date: string; weekday: WeekDay; price: number; available: boolean }[];
  pricing: ApiQuotePricing;
}
interface ApiQuotePricing {
  subtotal: number;                    // SAR major
  service_fee_percentage?: number; service_fee?: number;  // usually absent (fee baked into per-day price)
  vat_percentage: number; vat: number; // SAR major
  total: number; total_minor: number;  // total SAR major; total_minor = halalas
}

// createBooking payload
{ check_in: string; check_out: string; guests?: number }  // YYYY-MM-DD

type BookingApiStatus = "pending_payment"|"confirmed"|"expired"|"canceled_by_host"|"canceled_by_guest"|"canceled_by_admin"|"completed";
interface ApiBooking {
  id: string; reference: string; place_id: string; status: BookingApiStatus;
  start_date: string; end_date: string; check_in_time: string; check_out_time: string;
  checkout_next_day?: boolean; checkout_at?: string|null;   // resolved checkout instant (+1 day if overnight)
  guests: number|null; currency: string;
  pricing: ApiBookingPricing; payment: ApiBookingPayment;
  expires_at: string|null;                                  // hold expiry (ISO)
  confirmed_at: string|null; created_at: string;
  refund?: ApiBookingRefund;                                // only on paid-then-cancelled
}
interface ApiBookingPricing { subtotal: number; vat_percentage: number; vat: number; total: number; total_minor: number; } // SAR major (+ total_minor halalas)
interface ApiBookingPayment { id: string; method: string|null; status: string; url: string; }  // url = Moyasar hosted page
interface ApiBookingRefund { refunded: boolean; amount: number; amount_minor: number; }
interface ApiBookingListItem extends ApiBooking {
  place: {
    id; title; title_ar?; title_en?; cover_photo_url: string|null;
    type: {name_en;name_ar;icon}; city: {name_en;name_ar}; city_area: {name_en;name_ar}|null;
    location_url?: string|null;   // present only when confirmed/completed → Directions button
  };
  review: ApiBookingReview|null;
  can_review: boolean;            // true only when completed AND review === null
}

type ReviewApiStatus = "under_review"|"published"|"blocked";
interface ApiBookingReview { id: string; rate: number; comment: string|null; status: ReviewApiStatus; created_at: string; }

interface ApiSettings { support_phone: string; support_email: string; commission_percentage?: string|null; vat_percentage?: string|null; } // percentages arrive as strings, may be null

type FinanceDocumentType = "invoice"|"credit_note"|"refund_voucher"|"settlement_statement";
interface ApiFinanceDocument {
  id; document_type: FinanceDocumentType; document_subtype: string; status: string;
  is_tax_document: boolean; number: string|null; currency: string;
  subtotal_amount: number; vat_amount: number; total_amount: number;   // halalas
  booking_reference: string|null; issued_at: string|null; has_pdf: boolean;
}

interface ApiAuthUser {
  id; name: string|null; avatar_url: string|null;
  gender: "male"|"female"|null; age: number|null; birth_date: string|null;
  phone: string|null; email: string|null; country_id: string|null;
  bank?: string; bank_account?: string; bank_account_name?: string;   // host payout (ignore for guest)
  role: "user"|"admin"; is_host?: boolean; locale?: "ar"|"en";
  phone_verified_at: string|null; email_verified_at: string|null; created_at: string;
}

interface ApiPagination { page; per_page; total; last_page: number; has_more: boolean; }
interface ApiPaginated<T> { items: T[]; pagination: ApiPagination; }
```

### 8.4 Pricing / quote

- **Per-day pricing:** the backend stores 7 explicit day prices (`per_day_prices`). There's no
  weekday/weekend flag at runtime — that's only a host-wizard input convenience.
- **KSA weekend = Thursday / Friday / Saturday; weekdays = Sunday–Wednesday.**
- **Client estimate** (for cards + the reserve bar only): sum `per_day_prices[weekday]` for each day,
  iterating **inclusive** from check-in to check-out in **UTC** (deliberately, to avoid timezone drift
  on date-only strings). Returns halalas.
- **The server quote (`getPlaceQuote`) is authoritative** for the charged total and VAT — the summary
  and checkout always use it. **VAT % and amounts come from the server; never compute VAT
  client-side.** Guest totals never include commission (host-side only).
- **Day counting is inclusive:** Sat + Sun = 2 days; a lone day = 1. `days = round((end − start) /
  86,400,000) + 1`, min 1.
- **"Prices differ" indicator:** if not all 7 weekday prices are equal, the reserve bar shows a
  "Prices differ on weekends ›" note that opens the day-prices sheet.

### 8.5 Money formatting

Two unit conventions — get these right:

- **SAR major** (`quote`/`booking` `subtotal`, `vat`, `total`): format with a "SAR-float" helper →
  e.g. `formatSar(1402.5)` → `"1,402.5 SR"`.
- **Halalas** (`price`, `per_day_prices`, `*_minor`, finance amounts): divide by 100 → e.g.
  `formatPriceSR(halalas)` → `"1,250 SR"`, or `formatMoney(halalas, locale, {showCurrency, exact})`
  using `Intl.NumberFormat('ar-SA'|'en-SA', currency SAR)`.
- The `" SR"`-suffixed helpers force `en-US`, so prices show **Latin digits even in Arabic**.

### 8.6 Booking lifecycle & status

- **Status theme** (chip bg/fg + `{ar,en}` label):
  - `pending_payment` → amber "بانتظار الدفع" / "Pending"
  - `confirmed` → blue "مؤكد" / "Confirmed"
  - `expired` → red "منتهي" / "Expired"
  - `canceled_by_host|guest|admin` → shared red "ملغي" / "Cancelled"
  - `completed` → green "مكتمل" / "Completed"
  - Always trust the backend status (never derive from dates); unknown → neutral grey chip.
- **Live phase (confirmed only):** `upcoming` (now < check-in), `ongoing` (checked-in, not yet
  checked-out), else `other`. Build instants at **Asia/Riyadh = UTC+3, no DST** (literal `+03:00`).
  For checkout, **prefer the backend `checkout_at`** (already +1 day for overnight); else
  `check_out_time` on `end_date` (+1 day when `checkout_next_day`).
- **Hold / expiry:** a pending booking shows a circular countdown to `expires_at`. Payable =
  `pending && !expired && payment.url`. Ring progress = fraction of `expires_at − created_at`
  remaining. Countdown ticks every 1s, stops at 0.

### 8.7 Payment verification

- Moyasar hosted page. **Redirect markers:** `calm-after-payment` (attempt finished → verify) and
  `calm-back-payment` (guest backed out → cancel the hold). On web these become a **return-URL route**
  the payment redirects to; detect which and act.
- **Never trust the redirect alone.** After a return, poll `getBookingPaymentStatus` up to 10× every
  2s; treat `confirmed` as success, anything still `pending_payment` after the grace window as pending.
- `cancelBooking` is safe anytime — the server re-verifies first (a paid hold becomes `confirmed`, an
  unpaid one expires, a confirmed one is a no-op).

### 8.8 Auth & session

- **Token storage:** persist `token`, `user` (JSON), and `expiresAt` (epoch ms). On the web, use
  secure, httpOnly cookies if possible; the mobile app uses secure storage with **no storage TTL** —
  the token persists until refresh definitively fails.
- **`currentUser` = the `ApiAuthUser`.** `is_host` gates host UI (ignore for guest). `isAuthed =
  Boolean(token)`.
- **Gate model:** browsing, search, listing detail, date picking, and the quote are all **public**.
  Auth is forced only at **summary → Continue** (redirect to login, resume after). Wishlist and
  Bookings tabs render a sign-in prompt when logged out. The like tap also gates.
- **Login:** phone → OTP (`requestOtp` → `verifyOtp` → sign in). Saudi phone `^5\d{8}$`. A `next`
  param resumes the intended destination after login.

### 8.9 Dates / timezone

- Venue timezone is **Asia/Riyadh (UTC+3, no DST)**. Wire format: date-only `YYYY-MM-DD` for stay
  dates; full ISO instants for `expires_at` / `checkout_at` / `confirmed_at`.
- Build day keys from **local** components (not `toISOString`) so they align with the calendar grid.
  "Today" for the past-day gate is anchored to Asia/Riyadh.
- Display formatters force **Gregorian** for Arabic (`ar-SA-u-ca-gregory`). Arabic-Indic digits for
  general numbers; **Latin digits for prices**.
- **Availability:** `getUnavailableDates` returns `unavailable_dates: string[]` (+ ranges). The blocked
  set can arrive after a pick — reconcile and drop invalid selections. Block submission if any
  inclusive day in the range is blocked.

---

## 9. Web-port gotchas

- **WebView → iframe / redirect.** Payment, info pages, and the finance-document PDF are native
  WebViews. On web: payment is an iframe or full redirect (move the Moyasar `calm-after-payment` /
  `calm-back-payment` markers to a **return-URL route**); info pages render directly; the PDF opens via
  its URL.
- **Module-singleton stores → route state / query / store.** Several flows pass data via in-memory
  singletons rather than route params: `place-list`, `photo-viewer`, `selected-booking`, `confirm`,
  `day-prices`, `last-search`, `applied-filters`, date/time pickers. Deep links won't hydrate a
  singleton — on web, carry this in route state, query params, or a client store.
- **Applied-filters + version.** `/results` refetches when a filters **version** bumps; `/search` and
  `/filters` both mutate the shared applied-filters object. Model this as one query object that both
  screens write and results reacts to.
- **Sheet presentations.** Map RN `formSheet` / `modal` / `transparentModal` to web dialogs/drawers:
  `/support` is a ~40%-height bottom sheet; `/confirm` and the pickers are fit-to-content sheets with a
  grab handle; `/search` is a transparent-scrim overlay.
- **Skip / build fresh:** `/booking/[id]/confirmation` (dead, unreachable), `/quick-filters` and
  `/listing/[id]/dates` (both "coming soon" placeholders).
- **`borderCurve: continuous`** on all 24px card images — approximate a squircle or accept the plain
  arc.
- **Cards use pure black `#000`** text and `#F3F4F6` image placeholders — not the `text` / `divider`
  tokens. Replicate literally.

---

*Generated from the Calm mobile source as a porting reference. When in doubt, the mobile source
(`constants/theme.ts`, `lib/api.ts`, `lib/format.ts`, `lib/pricing.ts`, `data/*`, `app/*`) is the
ground truth.*
