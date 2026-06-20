# Notifications API (mobile)

Notifications go out over **SMS + the in-app feed** in the user's language. **Push is
disabled for now** (server flag `PUSH_ENABLED=false`) so we can ship without Expo/EAS setup —
the device-registration endpoint below still works (tokens are stored, just not sent to) so
the app can be wired ahead of time; flip `PUSH_ENABLED=true` later to turn push on with no
code change. These endpoints cover (1) registering the device for push and (2) the in-app
feed. All require auth: `Authorization: Bearer <token>`. Standard envelope:
`{ status, message, data }`.

---

## 1. Register the device for push

```
POST /api/devices
```

Call this **right after login** and again whenever Expo hands the app a new push token.
Upserts by token, so re-registering the same device is safe (it re-points the token to the
current user). Sending `locale` also sets the user's preferred notification language.

**Body**
```json
{ "token": "ExponentPushToken[xxxxxxxx]", "platform": "ios", "locale": "ar" }
```

| Field | Rule |
| --- | --- |
| `token` | required — the Expo push token |
| `platform` | optional — `ios` or `android` |
| `locale` | optional — `ar` or `en` (sets the user's notification language) |

**Response:** `{ "status": 200, "message": "Device registered.", "data": null }`

### Unregister (on logout)

```
DELETE /api/devices
Body: { "token": "ExponentPushToken[xxxxxxxx]" }
```

---

## 2. In-app feed

### List notifications

```
GET /api/notifications?page=1&per_page=20
```

Paginated, newest first, **already localized** to the user's language.

```json
{
  "status": 200,
  "message": "Notifications fetched.",
  "data": {
    "items": [
      {
        "id": "019ed2...",
        "type": "booking_confirmed",
        "title": "تم تأكيد حجزك",
        "body": "تم تأكيد حجزك في شاليه البحيرة. نتمنى لك إقامة سعيدة.",
        "data": { "booking_id": "019e...", "place_id": "019e..." },
        "is_read": false,
        "read_at": null,
        "created_at": "2026-06-19T12:00:00+00:00"
      }
    ],
    "pagination": { "page": 1, "per_page": 20, "total": 8, "last_page": 1, "has_more": false }
  }
}
```

| Field | Notes |
| --- | --- |
| `type` | Machine type for routing/icon: `booking_confirmed`, `booking_cancelled`, `place_approved`, `place_rejected`, `broadcast`. |
| `title` / `body` | Resolved to the user's `locale` (set via device registration / profile). |
| `data` | Deep-link payload — e.g. `{booking_id}` or `{place_id}` — for tapping through to a screen. |
| `is_read` | Convenience boolean (`read_at !== null`). |

Pagination is offset-based — use `pagination.has_more` to drive "load more" (request `page + 1`).

### Unread badge count

```
GET /api/notifications/unread-count        →  { "data": { "count": 3 } }
```

### Mark read

```
POST /api/notifications/{id}/read     // one (404 if it isn't yours)
POST /api/notifications/read-all      // all of the user's unread
```

---

## Notes
- The feed is the **in-app copy** of what was also sent via SMS + push — there's no separate
  "fetch push" call; the app just renders this list and the OS handles the push banner.
- Localization follows the user's stored `locale`. Set it on device registration (`locale`)
  or via `PATCH /api/user`.
- Errors: `401` (missing/expired token), `422` (validation, e.g. missing `token`).
