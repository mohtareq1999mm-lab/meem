# Password — Changelog

## [1.0.0] — Initial Documentation

### Added
- 3 password reset endpoints: forget-password, verify-forget-password-token, reset-password
- Custom 6-character alphanumeric OTP generation
- 5-minute OTP expiry window
- `throttle:sensitive` rate limiter (5/min per IP) across all endpoints
- DB transaction for atomic password reset + token revocation
- Automatic cleanup of `password_resets` record after successful reset

### Architecture
- Custom implementation (not Laravel's PasswordBroker)
- Manual bcrypt hash comparison for OTP verification
- All Sanctum tokens revoked on password change
- Email-only delivery (no SMS gateway integration)

### Known Issues
1. `verify-forget-password-token` returns raw boolean instead of JSON API response
2. `forgetPassword()` lacks input validation on email field
3. Custom implementation bypasses Laravel Password events and broker
