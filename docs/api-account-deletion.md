# Account deletion API

Satisfies Apple App Store guideline 5.1.1(v) — an in-app way to delete the account.

> The app must show a real **in-app** "Delete account" button (not a web link, not "email us").

## Flow (two calls, OTP-confirmed)

1. **Send a confirmation code** — reuse the normal OTP request:
   ```
   POST /api/auth/otp/request
   { "phone": "512345678" }
   ```
2. **Delete the account:**
   ```
   DELETE /api/user
   Authorization: Bearer <jwt>
   { "code": "123456" }
   ```
   - `200` → `{ "status": 200, "message": "Account deleted." }`. The JWT is invalidated and the
     account is gone from the app's perspective (the user can't log back in). The app should clear its
     token and return to the logged-out state.
   - `422` → invalid/expired code, an **active obligation**, or an admin account. Show `message`, e.g.
     *"You have upcoming bookings. Please complete or cancel them before deleting your account."*

After deletion the same phone number can immediately register a brand-new account.

## Notes for the client
- Show a clear confirmation ("This permanently deletes your account") before calling `DELETE /api/user`.
- Deletion is blocked while the user has active bookings (as guest) or active bookings / unpaid payouts
  on their listings (as host) — surface the returned `message`.
- For App Review test accounts, add the reviewer's number to `SMS_MOCK_PHONES` so their OTP is the fixed
  `111111` and no real SMS is sent.

## Data handling (server-side)
The account is soft-deleted and hidden; bookings/payment records are retained de-identified for
legal/financial requirements (the deleted user shows as "—" in any historical view). Support can recover
an account on request (`php artisan accounts:restore <phone>`). If `ACCOUNT_RETAIN_DAYS` is configured, a
scheduled job permanently scrubs personal data after that window.
