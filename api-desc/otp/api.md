# OTP API Reference

## POST /api/v1/send-otp-code — DISABLED

Send a one-time password to the user via SMS or email.

### Request
```json
{
  "email": "user@example.com",
  "phone_number": "+201234567890"
}
```

At least one of `email` or `phone_number` must be provided.

### Validation Rules
| Field | Rules |
|-------|-------|
| email | required_without:phone_number, email |
| phone_number | required_without:email, string, max:15, min:11 |

### Business Logic
1. Lookup active user by email OR phone_number
2. If user not found → 404
3. If email provided → call `$user->sendOneTimePassword()` (sends OTP via email)
4. If phone_number provided → call `sendOtpCode()` which delegates to the configured OTP gateway

### Success Response (200)
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

### Error Response (404)
```json
{
  "success": false,
  "message": "User not found"
}
```

---

## POST /api/v1/otp-login — DISABLED

Login using a verified OTP code.

### Request (phone-based)
```json
{
  "phone_number": "+201234567890",
  "otp_id": "verification-id-from-gateway",
  "code": "123456"
}
```

### Request (email-based)
```json
{
  "email": "user@example.com",
  "otp": "123456"
}
```

### Business Logic
- If `email` present → calls `verifyLoginOtp()` which validates email + OTP
- If `phone_number` present → calls `verifyOtp()` via gateway, then looks up user by phone

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

### Error Responses
- **404** — User not found by phone/email, or REQUIRED_INFO_MISSING
- **400** — OTP verification failed
- **422** — Invalid gateway or OTP verification exception
