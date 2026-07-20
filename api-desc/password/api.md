# Password API Reference

## POST /api/v1/forget-password

Request a password reset OTP sent to the user's email.

### Request
```json
{
  "email": "user@example.com"
}
```

### Validation
No form request class — inline validation via `$request->email`. If email is missing, Laravel returns 500 (no explicit validation in the method body). Email existence is checked against `users` table.

### Success Response (200)
```json
{
  "success": true,
  "message": "Check your inbox for password reset email"
}
```

### Error Response (404)
```json
{
  "success": false,
  "message": "Not found"
}
```

### Error Response (500)
```json
{
  "success": false,
  "message": "Something went wrong"
}
```

---

## POST /api/v1/verify-forget-password-token

Verify whether an OTP is valid and not expired.

### Request
```json
{
  "email": "user@example.com",
  "otp": "A1B2C3"
}
```

### Business Logic
1. Look up `password_resets` by email
2. `Hash::check($otp, $token)` — verify OTP matches
3. Check `created_at + 5 minutes` is not in the past

### Response (valid)
```
true
```

### Response (invalid/expired)
```
false
```

**Note**: Returns raw boolean, not a JSON envelope. Frontend must handle both `true` and `false` as raw response bodies.

---

## POST /api/v1/reset-password

Reset password after OTP verification.

### Request
```json
{
  "email": "user@example.com",
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
- Runs in `DB::transaction`
- Calls `verifyForgetPasswordToken()` — if false, returns 400
- Updates user password with `Hash::make()`
- Deletes ALL user tokens (`$user->tokens()->delete()`)
- Cleans up `password_resets` record

### Success Response (200)
```json
{
  "success": true,
  "message": "Password reset successful"
}
```

### Error Response (400)
```json
{
  "success": false,
  "message": "Invalid token"
}
```

### Error Response (422)
```json
{
  "password": ["The password must be at least 8 characters."],
  "otp": ["The otp field is required."]
}
```
