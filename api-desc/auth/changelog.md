# Auth — Changelog

## [1.1.0] — Production Hardening (SMTP + Queue + Security)

### Fixed
- SMTP authentication failure: switched `MAIL_MAILER` to `log` (safe dev default)
- Email queueing: all mailables implement `ShouldQueue`, dispatched to `database` connection on `high` queue
- `forgetPassword` email enumeration: always returns 200 (does not disclose whether email exists)
- `password_resets` race condition: replaced manual insert/update with atomic `updateOrInsert`
- `verifyForgetPasswordToken` empty response: now returns proper JSON `{success, message}` with HTTP 400 on failure
- `verifyForgetPasswordToken` missing validation: added `$request->validate()` for email + otp fields
- `resetPassword` validation swallowed: moved `$request->validate()` outside try/catch — 422 errors now work
- Token expiry: changed from hardcoded 5 minutes to `config('auth.passengers.users.expire', 60)`
- Dead code: `loginWithOutEmailVerification` now returns 410 instead of logging in
- Missing `gone` translation key added to EN, AR, DE

### Changed
- `QUEUE_CONNECTION` from `sync` to `database`
- `ForgetPassword` Mailable: added `implements ShouldQueue`, queues on `high`
- `UserRepository::sendResetEmail`: `Mail::send()` → `Mail::queue()`
- `OneTimePasswordNotification`: added `implements ShouldQueue`, queues on `high`
- `UserPasswordResetTest`: fixed prefix from `/api` to `/api/v1` (was always 404)

### Known Issues
1. Custom password reset bypasses Laravel broker integration
2. `sendUserOtp` uses wrong translation key (USER_LOGGED_IN_SUCCESSFULLY) — should be OTP-specific key
3. Duplicate `/me` route registration
4. `contacts` table missing in test SQLite DB (contact-us tests fail)
5. `sendUserOtp`: `otp_id` missing from success response (fixed: now returns in `data`)

### Fixed in this release
- `sendUserOtp()` now returns `otp_id` in success response (line 565 was missing `$data` as 4th arg)
- `otpLogin()` token wrapped in `['token' => $token]` array for consistent JSON structure (was raw string)

## [1.0.0] — Initial Documentation

### Added
- 11 auth endpoints documented across 3 rate limiter groups
- Registration with email/phone + role assignment
- Email/password login with Sanctum token generation
- Admin login with email verification requirement
- Social login (Google/Facebook) via Laravel Socialite
- Password reset flow (forget → verify → reset) with 6-char OTP
- Phone OTP authentication skeleton (disabled)
- Current user profile endpoint
- Token revocation on logout

### Architecture
- Three rate limiters: `auth` (10/min), `sensitive` (5/min), `otp` (3/min)
- Custom password reset implementation (not Laravel's PasswordBroker)
- Spatie MediaLibrary for avatar upload
- AdminLoggedIn event dispatched on login
- DB transactions for registration and password reset

### Known Issues
1. `verify-forget-password-token` returns raw boolean instead of JSON
2. Custom password reset bypasses Laravel broker integration
3. `sendUserOtp` uses wrong translation key (USER_LOGGED_IN_SUCCESSFULLY)
4. Duplicate `/me` route registration
