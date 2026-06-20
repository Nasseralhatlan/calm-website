# Calm Auth API — OTP Login

Phone → OTP → JWT bearer. No passwords. Email login isn't exposed yet.

The same flow handles **login AND registration**: if the phone isn't registered yet, `verify` creates the user on the fly and logs them in. The frontend doesn't branch.

---

## Response envelope

All responses (success + error) share the envelope:

```json
{
  "status": 200,
  "message": "OK",
  "data": { ... }    // object | array | null
}
```

`status` mirrors the HTTP status code. Validation errors put field messages in `data.errors`.

---

## The two-step flow

```
1. POST /api/auth/otp/request   → server sends a 6-digit OTP via SMS
2. POST /api/auth/otp/verify    → returns { token, user } if the OTP matches
3. Attach the token to every subsequent request:
   Authorization: Bearer <token>
4. POST /api/auth/logout         → invalidates the token + clears cookie
5. POST /api/auth/refresh        → swap for a fresh token before expiry
```

---

## 1. Request OTP

```
POST /api/auth/otp/request
Auth: public
```

**Body**

```json
{
  "phone": "512345678"
}
```

| Field   | Type   | Notes |
| ------- | ------ | ----- |
| `phone` | string | Saudi national 9-digit format `5XXXXXXXX` — **no leading 0, no +966**. |

**Response — success**

```json
{
  "status": 200,
  "message": "OTP dispatched.",
  "data": {
    "phone": "512345678",
    "expires_at": "2026-06-12T18:35:00+00:00"
  }
}
```

| Field        | Notes |
| ------------ | ----- |
| `phone`      | Echoes the phone you submitted. |
| `expires_at` | ISO 8601 UTC — when the SMS-delivered code stops being accepted. Use to drive a "expires in X:XX" countdown on the OTP-entry screen and to gate the resend button. |

The OTP code is **never** returned in the response — it's delivered out-of-band (SMS). Frontend should now show the OTP-entry screen.

**Errors**

| Status | When                                              |
| ------ | ------------------------------------------------- |
| 422    | Invalid phone format or missing field, OR the phone still has a pending OTP (service-layer cooldown returned) |
| 429    | Public IP cap hit (30/min across all public endpoints combined) |

---

## 2. Verify OTP

```
POST /api/auth/otp/verify
Auth: public
```

**Body**

```json
{
  "phone": "512345678",
  "otp": "123456"
}
```

| Field   | Type                      | Notes |
| ------- | ------------------------- | ----- |
| `phone` | string                    | Same value submitted in the request step. |
| `otp`   | string (exactly 6 digits) | The code the user received. |

**Response — success**

```json
{
  "status": 200,
  "message": "Verified.",
  "data": {
    "token": "eyJhbGciOi...",
    "token_type": "bearer",
    "expires_in": 3600,
    "expires_at": "2026-06-12T19:30:00+00:00",
    "user": {
      "id": "019eb8...",
      "name": null,
      "avatar_url": null,
      "gender": null,
      "age": null,
      "phone": "512345678",
      "email": null,
      "country_id": "019eb8...",
      "role": "user",
      "is_host": false,
      "phone_verified_at": "2026-06-12T18:30:00+00:00",
      "email_verified_at": null,
      "created_at": "2026-06-12T18:30:00+00:00"
    }
  }
}
```

| Field         | Notes |
| ------------- | ----- |
| `token`       | JWT — store in secure storage (Keychain on iOS, Keystore on Android). |
| `token_type`  | Always `"bearer"`. |
| `expires_in`  | TTL in **seconds**. |
| `expires_at`  | Absolute ISO 8601 UTC timestamp the token stops working. Prefer this over `expires_in` to avoid clock-skew math — schedule the refresh against `expires_at - 20%` of the window. |
| `user`        | Full user profile — the **same object** returned by `GET /api/user` and `PATCH`/`POST /api/user` (see §5). `name`/`avatar_url`/`gender`/`age`/`email` are nullable until the user fills them. |
| `user.avatar_url` | Public URL of the profile picture to **display** in an `<img>`, or `null` when none is set. |
| `user.role`   | `"user"` for normal users, `"admin"` for staff. The mobile app only cares about `"user"`. |
| `user.is_host` | `true` once the user has any place in the system — **including drafts and rejected places**. Flip the "Become a host" CTA to "My listings" based on this. |

The server also sets an httpOnly `calm_token` cookie alongside the JSON body — used by the web client, harmless for mobile (just ignore it).

**Errors**

| Status | When |
| ------ | ---- |
| 422    | Validation: missing field, bad OTP format (must be 6 digits), bad phone format |
| 422    | **Invalid or expired OTP** — the code didn't match or already used. Message: `"Invalid or expired OTP."` Service-layer also enforces a per-OTP attempt cap; once exceeded, further verify calls for the same code return this error until a new OTP is requested. |
| 429    | Public IP cap hit (30/min across all public endpoints combined) |

**Distinguishing 422 cases.** Validation errors come with `data.errors`. The "invalid OTP" error has `data: null` and `message: "Invalid or expired OTP."`. Frontend should check `message` first when status is 422.

---

## 3. Logout

```
POST /api/auth/logout
Auth: required
Headers: Authorization: Bearer <token>
```

Invalidates the current JWT server-side (blacklist) AND clears the `calm_token` cookie. After this call, the same token will return 401 on any subsequent request.

**Response**

```json
{
  "status": 200,
  "message": "Logged out.",
  "data": null
}
```

The frontend should also drop the token from secure storage.

---

## 4. Refresh token

```
POST /api/auth/refresh
Auth: required
Headers: Authorization: Bearer <current-token>
```

Swap a soon-to-expire token for a fresh one **without** re-prompting the user for an OTP. Call this before `expires_in` hits 0 (e.g. at 80% of TTL).

**Response**

```json
{
  "status": 200,
  "message": "Token refreshed.",
  "data": {
    "token": "eyJhbGciOi...",
    "token_type": "bearer",
    "expires_in": 3600,
    "expires_at": "2026-06-12T20:30:00+00:00"
  }
}
```

Replace the old token in secure storage with the new one. The old token is invalidated immediately — don't keep using it after a successful refresh.

**Errors**

| Status | When |
| ------ | ---- |
| 401    | Token already expired / blacklisted — fall back to the OTP flow |

---

## 5. Get & update profile

### Get the current user

```
GET /api/user
Auth: required
```

Returns the full user profile (the same `user` object shown in §2), wrapped in `data`:

```json
{
  "data": {
    "id": "019eb8...",
    "name": "Nasser",
    "avatar_url": "https://calm-object-storage.fra1.digitaloceanspaces.com/avatars/k1towwlu7c0d.webp",
    "gender": "male",
    "age": 30,
    "birth_date": "1996-05-01",
    "phone": "512345678",
    "email": "me@example.com",
    "country_id": "019eb8...",
    "role": "user",
    "is_host": false,
    "phone_verified_at": "2026-06-12T18:30:00+00:00",
    "email_verified_at": null,
    "created_at": "2026-06-12T18:30:00+00:00"
  }
}
```

### Update name / details (JSON)

```
PATCH /api/user
Auth: required
Content-Type: application/json
```

Send **only the fields you want to change** — all optional:

```json
{ "name": "Nasser", "gender": "male", "age": 30, "birth_date": "1996-05-01", "email": "me@example.com" }
```

### Update profile **with a picture** (multipart)

```
POST /api/user
Auth: required
Content-Type: multipart/form-data
```

⚠️ A profile-picture upload **must use POST**, not PATCH — PHP only parses multipart bodies on POST. You can update `name` (and the other fields) in the same request:

```
name=Nasser
avatar=<image file>     // jpeg / jpg / png / webp, ≤ 5 MB
```

**Response** (identical for `PATCH` and `POST`):

```json
{
  "status": 200,
  "message": "Profile updated.",
  "data": {
    "id": "019eb8...",
    "name": "Nasser",
    "avatar_url": "https://calm-object-storage.fra1.digitaloceanspaces.com/avatars/k1towwlu7c0d.webp",
    "gender": "male",
    "age": 30,
    "birth_date": "1996-05-01",
    "phone": "512345678",
    "email": "me@example.com",
    "country_id": "019eb8...",
    "role": "user",
    "is_host": false,
    "phone_verified_at": "2026-06-12T18:30:00+00:00",
    "email_verified_at": null,
    "created_at": "2026-06-12T18:30:00+00:00"
  }
}
```

**Behavior**
- Every field is optional — send only what changed.
- The response returns **`avatar_url`** (the public picture URL to display); it's `null` until a picture is set. The uploaded file field is named `avatar`.
- Uploading a new `avatar` **replaces and deletes** the previous one — no orphaned files.
- `phone` changes via the OTP flow, not here. `role` and the `*_verified_at` timestamps are ignored if sent.

**Validation**

| Field        | Rule |
| ------------ | ---- |
| `name`       | string, ≤ 120 |
| `avatar`     | image (`jpeg`/`jpg`/`png`/`webp`), ≤ 5 MB. **POST only.** |
| `gender`     | `male` or `female` |
| `age`        | integer, 13–120 |
| `birth_date` | `YYYY-MM-DD`, before today (after 1900) |
| `email`      | valid email, ≤ 254, unique across users |

**Errors**

| Status | When |
| ------ | ---- |
| 401    | Missing / expired token |
| 422    | Validation — failed fields under `data.errors.<field>` (e.g. non-image `avatar`, duplicate `email`) |

curl:

```bash
# Update name + avatar in one multipart call
curl -X POST https://api.calmapp.co/api/user \
  -H "Authorization: Bearer $TOKEN" \
  -F "name=Nasser" \
  -F "avatar=@/path/to/photo.jpg"
```

---

## End-to-end recipe (curl)

```bash
# 1. Request OTP
curl -X POST https://api.calmapp.co/api/auth/otp/request \
  -H "Content-Type: application/json" \
  -d '{"phone":"512345678"}'

# (user receives "Your Calm code: 123456" via SMS)

# 2. Verify OTP
RESP=$(curl -s -X POST https://api.calmapp.co/api/auth/otp/verify \
  -H "Content-Type: application/json" \
  -d '{"phone":"512345678","otp":"123456"}')
TOKEN=$(echo "$RESP" | jq -r '.data.token')

# 3. Use the token
curl https://api.calmapp.co/api/user \
  -H "Authorization: Bearer $TOKEN"

# 4. Refresh
curl -X POST https://api.calmapp.co/api/auth/refresh \
  -H "Authorization: Bearer $TOKEN"

# 5. Logout
curl -X POST https://api.calmapp.co/api/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

---

## Field validation cheat sheet

**Saudi phone format** — `5XXXXXXXX`. Exactly 9 digits, must start with `5`. Strip these before sending:
- Leading `0` (`0501234567` → `501234567`)
- Country code (`+966501234567` → `501234567`)
- Spaces, dashes, parens (`+966 50 123 4567` → `501234567`)

Regex on the backend: `/^5\d{8}$/`.

**OTP code format** — exactly 6 digits. Regex: `/^\d{6}$/`. No spaces, no dashes.

---

## Common frontend pitfalls

1. **Sending the phone with a leading 0.** `0501234567` will 422. Strip it.
2. **Sending +966 prefix.** Strip it. The server stores 9-digit national format only.
3. **Re-sending OTP request while the previous code is still valid.** The server returns the SAME pending code instead of a new one (per-identifier cooldown enforced in the service layer). Use the `expires_at` from the request response to gate your "Resend" button — only re-request once the previous expiry has passed.
4. **Storing the token in localStorage on the web.** The web app already gets it as an httpOnly cookie automatically — no JS storage needed for SSR. Mobile uses secure native storage.
5. **Not refreshing.** Once the token expires the user gets dumped back to OTP. Schedule a refresh against `expires_at` (subtract ~20% of the window) — avoids clock-skew issues with computing it from `expires_in`.
6. **Treating "user created" as a separate state.** There's none — `verify` either logs in an existing user or creates+logs in a new one. The response shape is identical either way; check `user.name === null` to decide whether to send the user to the profile-completion screen.
