# Auth API Reference

## POST /api/v1/register

Register a new customer account.

### Request
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone_number": "+201234567890",
  "password": "securePass123!",
  "password_confirmation": "securePass123!",
  "policy": true
}
```

### Validation Rules
| Field | Rules |
|-------|-------|
| first_name | required, string, max:50, min:2 |
| last_name | required, string, max:50, min:2 |
| email | required, email, unique:users, rfc,dns |
| phone_number | required, string, max:20, min:10, unique:users |
| password | required, string, min:8, max:50, confirmed |
| password_confirmation | required, string, min:8, max:50 |
| policy | required, in:1,true |

### Success Response (200)
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "otp_status": true
  }
}
```

### Partial Failure Response (201 — OTP send failed)
```json
{
  "success": true,
  "message": "Account created but OTP failed",
  "data": {
    "requires_resend": true,
    "email": "john@example.com",
    "phone_number": "+201234567890",
    "otp_status": false
  }
}
```

---

## POST /api/v1/token

Login with email/password.

### Request
```json
{
  "email": "john@example.com",
  "password": "securePass123!"
}
```

Or with phone number:
```json
{
  "phone_number": "+201234567890",
  "password": "securePass123!"
}
```

### Validation
Uses `UserAuthEmailAndPasswordRequest` — email or phone_number required.

### Success Response (200)
```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "token": "1|abc123...",
    "email_verified": false
  }
}
```

### Error Response (404)
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

## POST /api/v1/admin-login

Admin login (requires verified email).

### Request
```json
{
  "email": "admin@example.com",
  "password": "adminPass123!"
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "token": "1|abc123...",
    "permissions": ["super_admin", "view_users"],
    "email_verified": true,
    "role": ["super_admin"]
  }
}
```

### Error Responses
- **404**: Invalid credentials / User not found (type !== 'admin') / User not verified

---

## POST /api/v1/social-login-token

Login or register via Google/Facebook.

### Request
```json
{
  "provider": "google",
  "access_token": "ya29.a0AfH6SMC..."
}
```

### Supported Providers
- `google`
- `facebook`

### Business Logic
- Uses Laravel Socialite to verify token
- `firstOrCreate` by email — existing user is logged in, new user is created
- Auto-assigns `email_verified_at = now()`
- Appends/updates provider record in `user_providers` table
- Password is set to `Hash::make('password')` (never used for OAuth users)

### Success Response (200)
```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "token": "1|abc123..."
  }
}
```

---

## GET /api/v1/me

Get the authenticated user's profile.

### Auth
`auth:sanctum`

### Success Response (200)
```json
{
  "success": true,
  "message": "User profile retrieved successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2026-07-20T12:00:00Z",
    "role": "customer",
    "is_active": true,
    "created_at": "2026-07-20T12:00:00Z",
    "updated_at": "2026-07-20T12:00:00Z",
    "profile": { "avatar": {}, "bio": null, "contact": null },
    "address": [],
    "wallet": { ... },
    "shop": null
  }
}
```

### Error (401)
```json
{
  "success": false,
  "message": "Not authorized"
}
```

---

## POST /api/v1/logout

Revoke the current access token.

### Auth
`auth:sanctum`

### Success Response (200)
```json
{
  "success": true,
  "message": "User logged out successfully"
}
```

### Error (404 — no user)
```json
{
  "success": false,
  "message": "User not found"
}
```

---

## POST /api/v1/forget-password

Request a password reset OTP.

### Request
```json
{
  "email": "john@example.com"
}
```

### Business Logic
- Lookup user by email
- Generate a random 6-character string
- Store hashed token in `password_resets` table (upsert)
- Send email with plaintext OTP via `UserRepository::sendResetEmail()`
- Token expires after 5 minutes

### Success Response (200)
```json
{
  "success": true,
  "message": "Check your inbox for password reset email"
}
```

### Error (404)
```json
{
  "success": false,
  "message": "Not found"
}
```

---

## POST /api/v1/verify-forget-password-token

Verify a password reset OTP.

### Request
```json
{
  "email": "john@example.com",
  "otp": "A1B2C3"
}
```

### Business Logic
- Look up `password_resets` by email
- Hash::check the OTP
- Verify token is not older than 5 minutes
- Returns boolean (not a standard API response)

### Response
- `true` — token is valid
- `false` — token invalid, expired, or no token found

Note: This endpoint returns a raw boolean, not a structured API response.

---

## POST /api/v1/reset-password

Reset password with OTP verification.

### Request
```json
{
  "email": "john@example.com",
  "password": "newSecurePass456!",
  "password_confirmation": "newSecurePass456!",
  "otp": "A1B2C3"
}
```

### Validation Rules
| Field | Rules |
|-------|-------|
| password | required, string, min:8, max:50, confirmed |
| password_confirmation | required, string, min:8, max:50 |
| email | required, email |
| otp | required, string |

### Business Logic
- Runs in a database transaction
- Calls `verifyForgetPasswordToken()` internally
- If OTP invalid → returns 400 `Invalid token`
- Hashes new password
- Deletes all existing tokens (forces re-login on all devices)
- Cleans up the `password_resets` record

### Success Response (200)
```json
{
  "success": true,
  "message": "Password reset successful"
}
```

---

## POST /api/v1/send-otp-code — DISABLED

Send a phone OTP via SMS gateway.

### Request
```json
{
  "email": "john@example.com",
  "phone_number": "+201234567890"
}
```

### Validation
- `email` required_without:phone_number
- `phone_number` required_without:email, string, max:15, min:11

### Response (200)
```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "otp": "123456",
    "otp_id": "verification-id-from-gateway",
    "provider": "twilio"
  }
}
```

---

## POST /api/v1/otp-login — DISABLED

Login via OTP (phone or email).

### Request (phone)
```json
{
  "phone_number": "+201234567890",
  "otp_id": "...",
  "code": "123456"
}
```

### Request (email)
```json
{
  "email": "john@example.com",
  "otp": "123456"
}
```

### Response (200)
```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "token": "1|abc123..."
  }
}
```
