# OTP — Changelog

## [1.0.0] — Initial Documentation

### Added
- 2 OTP endpoints: send-otp-code, otp-login
- Configurable OTP gateway system (Twilio, Local)
- Automatic gateway fallback on failure
- Email OTP via Laravel Notifications (sendOneTimePassword)
- Phone OTP via third-party SMS gateway
- `throttle:otp` rate limiter — 3 requests/min per IP

### Architecture
- Gateway pattern: `OtpGateway` facade wrapping concrete implementations
- No persistent OTP storage — email OTPs are notification-based, phone OTPs are gateway-managed
- Sanctum token created on successful OTP login

### Current Status
- **Routes disabled by default** — must uncomment `Routes.php` lines 118-121 to enable
- Used indirectly by: `POST /update-contact` (always active, uses same sendUserOtp + verifyOtp)
- Registration has its own separate OTP system (sendOneTimePassword at registration time)

### Known Issues
1. `sendUserOtp()` returns `USER_LOGGED_IN_SUCCESSFULLY` translation key — misleading message
2. Email OTP is returned as static "123456" in response body — security concern if exposed in production
3. Commented-out static OTP in sendOtpCode — cleanup needed
4. Missing translation entries for `OTP_SEND_FAIL` / `OTP_SEND_SUCCESSFUL`
