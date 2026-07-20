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
- Show success message "Check your inbox"
- On 404 → "No account found with this email"
- On 429 → rate limit notice
- No token returned — user must check email

### Verify OTP
```
POST /api/v1/verify-forget-password-token
Body: { email, otp }
```

**Frontend handling:**
- 6-character OTP input field
- Response is raw boolean — handle both `true` and `false`
- On true → advance to password reset form
- On false → "Invalid or expired OTP"

### Reset Password
```
POST /api/v1/reset-password
Body: { email, otp, password, password_confirmation }
```

**Frontend handling:**
- On 200 → "Password reset successful!" → redirect to login
- On 400 (INVALID_TOKEN) → "Invalid or expired OTP, please request a new one"
- On 422 → field validation errors

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
