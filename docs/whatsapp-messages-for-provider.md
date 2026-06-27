# Calm — WhatsApp message templates (for the provider)

**App:** Calm (كالم) — a holiday-home / chalet booking app in Saudi Arabia.
**Business name:** Calm
**Channel:** WhatsApp Business (these messages are sent to users as **WhatsApp messages**).
**Message types:** **Authentication** (one-time verification code) and **Utility** (transactional
booking / listing status updates). **No marketing or promotional** content is sent on this channel.

**Languages:** every template is provided in **English** and **Arabic**. Arabic uses Arabic-Indic
numerals for dates (e.g. ٢٣ يونيو ٢٠٢٦). No customer names, addresses, or payment details are sent —
only a short booking reference and dates.

**Variables:** WhatsApp placeholders are shown as `{{1}}`, `{{2}}`, … with their meaning listed and a
realistic filled example underneath each template.

---

## 1. Authentication

### `verification_code` — category: **Authentication**
- **When:** the user requests a login/verification code. **To:** the person signing in.
- **Variables:** `{{1}}` = 6-digit code.
- **English template:**
  ```
  {{1}} is your Calm verification code.
  ```
  *Example:* `123456 is your Calm verification code.`
- **Arabic template:**
  ```
  {{1}} هو رمز التحقق الخاص بك في كالم.
  ```
  *Example:* `123456 هو رمز التحقق الخاص بك في كالم.`

---

## 2. Bookings — category: **Utility**

### `booking_confirmed` — to the guest
- **When:** the guest's payment succeeds and the booking is confirmed.
- **Variables:** `{{1}}` = stay dates · `{{2}}` = booking reference.
- **English template:**
  ```
  Booking confirmed
  Your booking is confirmed for {{1}}. Ref: {{2}}. For more details, view your booking in the Calm app.
  ```
  *Example:* `Your booking is confirmed for 23 Jun 2026 – 25 Jun 2026. Ref: CB-3QAKD4. For more details, view your booking in the Calm app.`
- **Arabic template:**
  ```
  تم تأكيد حجزك
  تم تأكيد حجزك بتاريخ {{1}}. رقم الحجز: {{2}}. لمزيد من التفاصيل، اطّلع على حجزك في تطبيق كالم.
  ```
  *Example:* `تم تأكيد حجزك بتاريخ ٢٣ يونيو ٢٠٢٦ – ٢٥ يونيو ٢٠٢٦. رقم الحجز: CB-3QAKD4. لمزيد من التفاصيل، اطّلع على حجزك في تطبيق كالم.`

### `host_new_booking` — to the host
- **When:** a guest books the host's place (same moment as above).
- **Variables:** `{{1}}` = stay dates · `{{2}}` = booking reference.
- **English template:**
  ```
  New booking
  You have a new booking for {{1}} in the Calm app. Ref: {{2}}.
  ```
  *Example:* `You have a new booking for 23 Jun 2026 – 25 Jun 2026 in the Calm app. Ref: CB-3QAKD4.`
- **Arabic template:**
  ```
  حجز جديد
  لديك حجز جديد بتاريخ {{1}} في تطبيق كالم. رقم الحجز: {{2}}.
  ```
  *Example:* `لديك حجز جديد بتاريخ ٢٣ يونيو ٢٠٢٦ – ٢٥ يونيو ٢٠٢٦ في تطبيق كالم. رقم الحجز: CB-3QAKD4.`

### `booking_cancelled_by_host` — two templates (guest + host)
- **When:** a confirmed booking is cancelled at the host's request. **Variables:** `{{1}}` = booking reference.

**To the guest**
- **English:**
  ```
  Booking cancelled
  Sorry, your booking was cancelled by the host. Ref: {{1}}.
  ```
  *Example:* `Sorry, your booking was cancelled by the host. Ref: CB-3QAKD4.`
- **Arabic:**
  ```
  تم إلغاء حجزك
  نعتذر، تم إلغاء حجزك من قِبل المضيف. رقم الحجز: {{1}}.
  ```

**To the host**
- **English:**
  ```
  Booking cancelled
  The booking was cancelled per your request. Ref: {{1}}.
  ```
- **Arabic:**
  ```
  تم إلغاء الحجز
  تم إلغاء الحجز بناءً على طلبك. رقم الحجز: {{1}}.
  ```

### `booking_cancelled_by_request` — two templates (guest + host)
- **When:** a confirmed booking is cancelled at the guest's request. **Variables:** `{{1}}` = booking reference.

**To the guest**
- **English:**
  ```
  Booking cancelled
  Your booking was cancelled as requested. Ref: {{1}}.
  ```
- **Arabic:**
  ```
  تم إلغاء حجزك
  تم إلغاء حجزك بناءً على طلبك. رقم الحجز: {{1}}.
  ```

**To the host**
- **English:**
  ```
  A booking was cancelled
  A booking was cancelled at the guest's request. Ref: {{1}}.
  ```
- **Arabic:**
  ```
  تم إلغاء حجز
  تم إلغاء حجز بناءً على طلب الضيف. رقم الحجز: {{1}}.
  ```

---

## 3. Listings — category: **Utility** (sent to the host)

### `place_received`
- **When:** a host submits (or resubmits) a place for review. **Variables:** none.
- **English:**
  ```
  Place received
  Your place was received and is under review. We'll let you know when it's live in the Calm app.
  ```
- **Arabic:**
  ```
  تم استلام مكانك
  تم استلام مكانك وهو قيد المراجعة. سنخبرك عند ظهوره في تطبيق كالم.
  ```

### `place_approved`
- **When:** the team approves the host's place. **Variables:** none.
- **English:**
  ```
  Place approved
  Your place was approved and is now live in the Calm app.
  ```
- **Arabic:**
  ```
  تمت الموافقة على مكانك
  تمت الموافقة على مكانك وأصبح متاحاً في تطبيق كالم.
  ```

### `place_needs_changes`
- **When:** the team asks the host to adjust their place before approval. **Variables:** `{{1}}` = short note (optional — a no-note variant is also used).
- **English (with note):**
  ```
  Place needs changes
  Your place needs changes: {{1}}
  ```
  *Example:* `Your place needs changes: Add clearer photos of the place.`
- **English (no note):**
  ```
  Place needs changes
  Your place needs some changes before approval.
  ```
- **Arabic (with note):**
  ```
  مكانك يحتاج تعديلات
  يحتاج مكانك إلى تعديلات: {{1}}
  ```
  *Example:* `يحتاج مكانك إلى تعديلات: إضافة صور أوضح للمكان.`
- **Arabic (no note):**
  ```
  مكانك يحتاج تعديلات
  يحتاج مكانك إلى بعض التعديلات قبل الموافقة.
  ```

---

## Notes for the provider
- All templates are **Authentication** (the code) or **Utility** (booking/listing updates) — please
  register them under those WhatsApp categories. No marketing templates.
- Variable values are limited to: a **6-digit code**, a short **booking reference** (e.g. `CB-3QAKD4`),
  **stay dates** (e.g. `23 Jun 2026 – 25 Jun 2026`), and an occasional short **review note** from our team.
- A single cancellation event sends **two** messages — one to the guest, one to the host.
- The first line of each message is a short heading; WhatsApp may render it in **bold** if desired.
