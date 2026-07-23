# Auth — Frontend Integration

## API Client
All auth endpoints are unauthenticated (except `/me` and `/logout`) and called from:
- Login page (email/password, Google, Facebook)
- Registration page
- Password reset flow (email → OTP → new password)

## Endpoints

### Registration
```
POST /api/v1/register
Body: { first_name, last_name, email, phone_number, password, password_confirmation, policy }
```

**Frontend handling:**
- Show loading state during submission (throttle:auth — 10/min)
- On 200 → redirect to email verification prompt or dashboard
- On 201 (OTP failed) → show "Check your email — OTP may not have been sent, you can resend"
- On 422 → display field-level validation errors
- On 429 → show "Too many attempts. Please try again later."
- The `policy` field must be checked via UI checkbox
- OTP email is **queued** (has slight delay) — don't show failure immediately

### Login
```
POST /api/v1/token
Body: { email, password }
```

**Frontend handling:**
- Save `token` from response to localStorage/session
- Check `email_verified` flag — if false, prompt verification
- Redirect based on user role (admin → dashboard, customer → home)
- On 404 (INVALID_CREDENTIALS) → show "Invalid email or password"
- On 429 → rate limit notice

### Admin Login
```
POST /api/v1/admin-login
Body: { email, password }
```

**Frontend handling:**
- Same as login, but requires email verified
- Store `permissions` and `role` from response for UI routing
- 404 with USER_NOT_VERIFIED → show "Please verify your email before logging in"
- 404 with USER_NOT_FOUND → show "No admin account found with this email"

### Send OTP Code
```
POST /api/v1/send-otp-code
Body: { email } or { phone_number }
```

**Frontend handling:**
- Request OTP for email or phone verification
- Response includes `otp_id` in `data` — store it to track verification session
- OTP email is **queued** — may take 1-3 seconds to arrive
- On 201 (mail failed) → show existing "OTP service unavailable" banner with resend option
- Do NOT disable resend for longer than 5 seconds (email is queued, not sent)

### OTP Login
```
POST /api/v1/otp-login
Body: { email, code } or { phone_number, code }
```

**Frontend handling:**
- Verify the OTP code and receive authentication token
- On 200 → `data.token` contains the Sanctum token (same format as `/token`)
- On 400 → "Invalid or expired code"
- Show remaining attempts if available

### Social Login
```
POST /api/v1/social-login-token
Body: { provider, access_token }
```

**Frontend handling:**
- Use a social auth library (e.g., `@react-oauth/google`, `react-facebook-login`)
- Extract `access_token` from provider SDK
- Send to backend, receive Sanctum token
- If you're already logged in and do social login, you get a new account (no merge)
- On error (422 INVALID_CREDENTIALS) → "Login failed. Please try again."

### Get Current User
```
GET /api/v1/me
Headers: Authorization: Bearer <token>
```

**Frontend handling:**
- Call on app mount to check authentication status
- Store `role`, `name`, `email`, `profile.avatar` in global state
- On 401 → clear token, redirect to login
- Used for profile dropdown, user menu, permission checks

### Logout
```
POST /api/v1/logout
Headers: Authorization: Bearer <token>
```

**Frontend handling:**
- Call on logout button click
- Clear token from storage
- Clear user state
- Redirect to login

### Forget Password
```
POST /api/v1/forget-password
Body: { email }
```

**Frontend handling:**
- Always returns 200 — does NOT disclose whether the email exists
- Show generic success message: "If this email is registered, check your inbox"
- Do NOT show "no account found" — this prevents email enumeration
- On 429 → rate limit notice
- Password reset email is **queued** — may take a few seconds to arrive
- Wait at least 2 seconds before offering "resend" option

### Verify OTP
```
POST /api/v1/verify-forget-password-token
Body: { email, otp }
```

**Frontend handling:**
- 6-character OTP input field
- Response is JSON: `{ success: true/false, message: "..." }`
- On 200 (`success: true`) → advance to password reset form
- On 400 (`success: false`) → "Invalid or expired OTP"
- OTP expires after 60 minutes (configurable via backend)

### Reset Password
```
POST /api/v1/reset-password
Body: { email, otp, password, password_confirmation }
```

**Frontend handling:**
- On 200 → "Password reset successful!" → redirect to login
- On 400 (INVALID_TOKEN) → "Invalid or expired OTP, please request a new one"
- On 422 → field validation errors (password too short, mismatch, missing email)

## Error Handling

### 429 Rate Limit
```json
{
  "message": "Too Many Attempts."
}
```
Show rate limit notice with retry timer.

### 422 Validation
```json
{
  "email": ["The email field is required."],
  "password": ["The password must be at least 8 characters."]
}
```
Map to form field errors.

### 401 Unauthenticated
```json
{
  "success": false,
  "message": "Not authorized"
}
```
Clear session, redirect to login.

## Token Storage
- Use `localStorage` or `secure cookie` for the Sanctum token
- Include `Authorization: Bearer <token>` in all authenticated API calls
- On 401 response, auto-logout
