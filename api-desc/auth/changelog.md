# Auth — Changelog

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
