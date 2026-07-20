# Password — Frontend Integration

## Overview
Three-step password reset flow implemented as a wizard/paginated form:

```
Step 1: Email → POST /forget-password
Step 2: OTP  → POST /verify-forget-password-token
Step 3: New Password → POST /reset-password
```

## Step 1: Request OTP

```
POST /api/v1/forget-password
Body: { email }
```

**Frontend:**
- Single email input field
- Submit button with loading state
- On 200 → "Check your inbox for the OTP" → advance to Step 2
- On 404 → "No account found with this email"
- On 429 → "Too many attempts. Try again in 1 minute."
- Pre-fill email in Step 2

## Step 2: Verify OTP

```
POST /api/v1/verify-forget-password-token
Body: { email, otp }
```

**Frontend:**
- 6-character OTP input (single input or 6 individual digit boxes)
- Auto-submit when 6 characters entered (mobile-friendly)
- ⚠ **Response is raw boolean** — not a JSON envelope
  - `true` → advance to Step 3
  - `false` → "Invalid or expired OTP"
- Show remaining time (5-minute countdown from Step 1)
- "Resend OTP" link → returns to Step 1
- On 429 → rate limit notice

**Implementation note:** Because the response is a raw boolean (not `{ success: true, data: true }`), use:
```js
const res = await api.post('/verify-forget-password-token', body);
if (res.data === true) { /* advance */ }
```

## Step 3: Reset Password

```
POST /api/v1/reset-password
Body: { email, otp, password, password_confirmation }
```

**Frontend:**
- Password + confirm password fields
- Password strength indicator (min 8 chars)
- On 200 → "Password reset successful!" → redirect to login
- On 400 → "Invalid or expired OTP. Please start over." → return to Step 1
- On 422 → field-level validation errors
- On 429 → rate limit notice

## Error Handling Summary

| Status | Meaning | Action |
|--------|---------|--------|
| 200 | Success | Advance to next step or redirect |
| 400 | Invalid OTP | Show error, allow retry or resend |
| 404 | Email not found | Show error, stay on Step 1 |
| 422 | Validation error | Show field errors |
| 429 | Rate limited | Show timer, disable submit |
| 500 | Server error | Show generic error, retry |

## Rate Limiting
All 3 endpoints share `throttle:sensitive` — 5 requests/min per IP.
- After 5 requests, all endpoints return 429 for 1 minute
- Consider showing a cooldown timer to the user
- The limiter is per-IP, so multiple users on same network share the limit
