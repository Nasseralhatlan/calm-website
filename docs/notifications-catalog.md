# Notifications — scenario catalog & manual test guide

Every notification the app can send: **when** it fires, the **exact AR + EN message**, **who**
receives it, and which **channels** go out. Use it to manually verify the system.

> For the mobile **API contract** (device registration, feed, unread count) see
> [api-notifications.md](api-notifications.md). This doc is the internal trigger catalog.

---

## How the pipeline works

`NotificationService::notify(User, payload)` →
1. writes **one row** to `user_notifications` (in-app feed, synchronous), and
2. dispatches `SendNotificationChannels` (queued job) which sends **SMS + push**.

Source: [NotificationService](../app/Services/Notification/NotificationService.php) ·
[SendNotificationChannels](../app/Jobs/SendNotificationChannels.php)

| Channel | Sent when | Driver / gate |
|---|---|---|
| **In-app** | always | row in `user_notifications` |
| **SMS** | user has a phone (phone is the login id) | `SMS_DRIVER` — `mock` logs to `storage/logs/laravel.log`, `sms_saudi` is the real gateway. Message = `"{title}\n{body}"` |
| **Push** | `PUSH_ENABLED=true` **and** user has device tokens | `PUSH_DRIVER` — `mock` / `expo`. Default `PUSH_ENABLED=false` (push OFF) |

**Language:** outbound **SMS/push are sent in Arabic for now**, regardless of the user's `locale`. The
in-app row still stores **both** AR + EN, so the app can localize the feed.

---

## Before you test (caveats)

1. **In-app feed + admin broadcast UI are currently DISABLED.** The API routes
   ([routes/api.php](../routes/api.php) lines ~78–87) and admin web routes
   ([routes/web.php](../routes/web.php) lines ~162–164) are commented out. Notifications still fire
   (DB row + SMS/push) — you just can't browse the in-app feed via API or send a broadcast from the
   admin screen until those routes are uncommented.
2. **Watch dry-run output:** set `SMS_DRIVER=mock` (optionally `PUSH_DRIVER=mock`, `PUSH_ENABLED=true`),
   then `tail -f storage/logs/laravel.log` while triggering events — mock drivers log the full payload.
3. **Queue:** SMS/push run through a queued job. With `QUEUE_CONNECTION=sync` they fire inline;
   otherwise run `php artisan queue:work` or they'll wait in the queue.
4. **Verify the in-app row** without the API: `php artisan tinker` →
   `App\Models\UserNotification::latest()->first()`.

---

## The catalog (8 active + 1 reserved)

### 1 · `booking_confirmed` → **Guest**
- **When:** a pending booking's payment succeeds (Moyasar confirms → status `Confirmed`). Fires together
  with #2. Source: [BookingService::fireBookingNotification](../app/Services/Booking/BookingService.php)
- **AR** — title: `تم تأكيد حجزك` · body: `تم تأكيد حجزك في {place}. الدخول {checkIn}، والخروج {checkOut}. رقم الحجز: {ref}. نتمنى لك إقامة سعيدة.`
- **EN** — title: `Your booking is confirmed` · body: `Your booking at {place} is confirmed. Check-in {checkIn}, check-out {checkOut}. Booking ref: {ref}. Enjoy your stay!`
- **Channels:** in-app + SMS + push · **data:** `booking_id`, `place_id`
- **How to trigger:** complete a booking payment through to success.
- _`{checkIn}`/`{checkOut}` = Gregorian date + AM/PM time, e.g. AR `23 يونيو 2026، 3:00 PM`, EN `23 Jun 2026, 3:00 PM`._

### 2 · `host_new_booking` → **Host**  *(fires alongside #1)*
- **When:** same moment as `booking_confirmed` — the place's host is notified.
- **AR** — title: `لديك حجز جديد` · body: `لديك حجز جديد على "{place}". الدخول {checkIn}، والخروج {checkOut}. رقم الحجز: {ref}.`
- **EN** — title: `You have a new booking` · body: `You've got a new booking on "{place}". Check-in {checkIn}, check-out {checkOut}. Booking ref: {ref}.`
- **Channels:** in-app + SMS + push · **data:** `booking_id`, `place_id`
- **How to trigger:** same as #1 — then check the host's phone/feed too.

### 3 · `booking_canceled_by_host` → **Guest AND Host** (two messages)
- **When:** admin cancels a **confirmed** booking choosing actor = *host* on the admin booking detail
  page. Source: [BookingService::cancelByAdmin](../app/Services/Booking/BookingService.php)
- **Guest** — AR title: `نعتذر، تم إلغاء حجزك` · AR body: `نأسف لإبلاغك بأنه تم إلغاء حجزك في "{place}" من قِبل المضيف. رقم الحجز: {ref}. نعتذر عن الإزعاج، وفريقنا سعيد بمساعدتك في إيجاد بديل مناسب.`
  - EN title: `Sorry — your booking was cancelled` · EN body: `We're sorry to let you know your booking at "{place}" was cancelled by the host. Booking ref: {ref}. Apologies for the inconvenience — our team is happy to help you find a great alternative.`
- **Host** — AR title: `تم إلغاء الحجز` · AR body: `تم إلغاء حجز الضيف في "{place}" بناءً على طلبك. رقم الحجز: {ref}. شكراً لإشعارنا.`
  - EN title: `Booking cancelled` · EN body: `The guest's booking at "{place}" has been cancelled per your request. Booking ref: {ref}. Thanks for letting us know.`
- **Channels:** in-app + SMS + push (both parties) · **data:** `booking_id`, `place_id`
- **How to trigger:** Admin → Bookings → open a *confirmed* booking → **Cancel (host request)**.

### 4 · `booking_canceled_by_admin` → **Guest AND Host** (two messages)
- **When:** admin cancels a **confirmed** booking choosing actor = *guest / admin*.
- **Guest** — AR title: `تم إلغاء حجزك` · AR body: `تم إلغاء حجزك في "{place}" بناءً على طلبك. رقم الحجز: {ref}. نتمنى استضافتك مجدداً قريباً.`
  - EN title: `Your booking was cancelled` · EN body: `Your booking at "{place}" has been cancelled as requested. Booking ref: {ref}. We'd love to host you again soon.`
- **Host** — AR title: `تم إلغاء حجز` · AR body: `نود إعلامك بأنه تم إلغاء حجز في "{place}" بناءً على طلب الضيف. رقم الحجز: {ref}. نعتذر عن أي إزعاج.`
  - EN title: `A booking was cancelled` · EN body: `Just so you know, a booking at "{place}" was cancelled at the guest's request. Booking ref: {ref}. Apologies for any inconvenience.`
- **Channels:** in-app + SMS + push (both parties) · **data:** `booking_id`, `place_id`
- **How to trigger:** Admin → Bookings → open a *confirmed* booking → **Cancel (guest request)**.
- **Note:** only **confirmed** bookings are cancellable — the request is refused (422) otherwise.

### 5 · `place_submitted` → **Host**
- **When:** host submits a new place, OR edits & resubmits one.
  Source: [PlaceService::createForHost / updateDetailsForHost](../app/Services/Place/PlaceService.php)
- **AR** — title: `تم استلام مكانك للمراجعة` · body: `استلمنا "{title}" وهو الآن قيد المراجعة. سنخبرك فور اكتمالها ليظهر مكانك في تطبيق كالم.`
- **EN** — title: `Your place was submitted for review` · body: `We received "{title}" — it's now under review. We'll let you know once it's live on the Calm app.`
- **Channels:** in-app + SMS + push · **data:** `place_id`
- **How to trigger:** as a host, complete the add-place wizard (or edit & save an existing place).

### 6 · `place_approved` → **Host**
- **When:** admin approves a place. Source: [PlaceReviewService::approve](../app/Services/Place/PlaceReviewService.php)
- **AR** — title: `تمت الموافقة على مكانك` · body: `أصبح "{title}" متاحاً للحجز الآن في تطبيق كالم.`
- **EN** — title: `Your place was approved` · body: `"{title}" is now live and available for booking on the Calm app.`
- **Channels:** in-app + SMS + push · **data:** `place_id`
- **How to trigger:** Admin → Places → approve a place pending review.

### 7 · `place_rejected` → **Host**
- **When:** admin rejects a place (optionally with a reason).
  Source: [PlaceReviewService::reject](../app/Services/Place/PlaceReviewService.php)
- **AR** — title: `مكانك يحتاج إلى تعديلات`
  - body (with reason): `يحتاج "{title}" إلى تعديلات: {reason}`
  - body (no reason): `يحتاج "{title}" إلى بعض التعديلات قبل الموافقة عليه.`
- **EN** — title: `Your place needs changes`
  - body (with reason): `"{title}" needs changes: {reason}`
  - body (no reason): `"{title}" needs some changes before it can be approved.`
- **Channels:** in-app + SMS + push · **data:** `place_id`
- **How to trigger:** Admin → Places → reject a place pending review (try with and without a reason).

### 8 · `broadcast` → **chosen audience** (`all` / `hosts` / `guests`)
- **When:** admin sends a manual broadcast from the admin notifications screen.
  Source: [NotificationService::broadcast](../app/Services/Notification/NotificationService.php) ·
  [Admin/NotificationsController](../app/Http/Controllers/Admin/NotificationsController.php)
- **Messages:** admin-authored AR + EN title/body. Audience = **hosts** (users with places),
  **guests** (users without places), or **all**. Writes an audit row in `notification_broadcasts`
  plus one `user_notifications` row per recipient, and fires SMS/push to each.
- **How to trigger:** re-enable the admin route (caveat #1) → `/admin/notifications`.

### Reserved (not wired): `booking_cancelled` → Guest
Defined in `NotificationService` but **never called** — kept for a future self-cancel flow. Ignore it
when testing.

---

## Quick reference

All delivered messages are in **Arabic** (see Language note above). The Arabic body format is shown
below; `{place}` / `{title}` / `{reason}` / `{ref}` are filled in at send time, and
`{checkIn}` / `{checkOut}` are Gregorian date + AM/PM time (e.g. `23 يونيو 2026، 3:00 PM`).

| Type | Recipient | Trigger | الرسالة (عربي) |
|---|---|---|---|
| `booking_confirmed` | Guest | Payment succeeds | `تم تأكيد حجزك في {place}. الدخول {checkIn}، والخروج {checkOut}. رقم الحجز: {ref}. نتمنى لك إقامة سعيدة.` |
| `host_new_booking` | Host | Payment succeeds (with above) | `لديك حجز جديد على "{place}". الدخول {checkIn}، والخروج {checkOut}. رقم الحجز: {ref}.` |
| `booking_canceled_by_host` | Guest | Admin cancels confirmed booking (host request) | `نأسف لإبلاغك بأنه تم إلغاء حجزك في "{place}" من قِبل المضيف. رقم الحجز: {ref}. نعتذر عن الإزعاج، وفريقنا سعيد بمساعدتك في إيجاد بديل مناسب.` |
| `booking_canceled_by_host` | Host | (same event) | `تم إلغاء حجز الضيف في "{place}" بناءً على طلبك. رقم الحجز: {ref}. شكراً لإشعارنا.` |
| `booking_canceled_by_admin` | Guest | Admin cancels confirmed booking (guest request) | `تم إلغاء حجزك في "{place}" بناءً على طلبك. رقم الحجز: {ref}. نتمنى استضافتك مجدداً قريباً.` |
| `booking_canceled_by_admin` | Host | (same event) | `نود إعلامك بأنه تم إلغاء حجز في "{place}" بناءً على طلب الضيف. رقم الحجز: {ref}. نعتذر عن أي إزعاج.` |
| `place_submitted` | Host | Host submits / resubmits a place | `استلمنا "{title}" وهو الآن قيد المراجعة. سنخبرك فور اكتمالها ليظهر مكانك في تطبيق كالم.` |
| `place_approved` | Host | Admin approves a place | `أصبح "{title}" متاحاً للحجز الآن في تطبيق كالم.` |
| `place_rejected` | Host | Admin rejects a place | `يحتاج "{title}" إلى تعديلات: {reason}` (بدون سبب: `يحتاج "{title}" إلى بعض التعديلات قبل الموافقة عليه.`) |
| `broadcast` | hosts / guests / all | Admin sends a broadcast (route disabled) | نص يكتبه المشرف (عربي) |

---

## Manual verification checklist
1. `.env`: `SMS_DRIVER=mock`, `QUEUE_CONNECTION=sync` → `php artisan config:clear`.
2. `tail -f storage/logs/laravel.log`, then trigger each scenario from the admin/host UI.
3. Per scenario confirm: (a) a `user_notifications` row exists for the right user(s); (b) the mock SMS
   log line shows the expected AR/EN text; (c) for cancellations, **both** guest and host get a line.
4. Real SMS later: `SMS_DRIVER=sms_saudi` + gateway creds, use a real phone, repeat.
5. In-app feed / admin broadcast: uncomment the disabled routes first (caveat #1).
