# Password Reset API

## Overview

The password reset flow uses a 4-step process:

1. **Forget Password** → generates 6-char OTP token, stores hashed in `password_resets` table, sends via queued email
2. **Verify Token** → validates OTP against stored hash + configurable expiry
3. **Reset Password** → updates password, revokes all tokens, deletes reset record
4. **Change Password** → authenticated user changes own password

**Mail Driver:** Uses Laravel mail configuration (default: `log` driver for development)
**Queue:** All password reset emails dispatch to the `high` queue — requires `php artisan queue:work --queue=high,default`

---

## POST /api/v1/forget-password

Request a password reset OTP token.

**Authentication:** None (public)
**Rate Limit:** 5/min per IP (throttle:sensitive)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes | Registered email address |

### Response (200)

```json
{
  "success": true,
  "message": "Check your email for password reset token"
}
```

**Always returns 200** — does not disclose whether the email exists (prevents email enumeration).

### Execution Flow

```
POST /api/v1/forget-password
  → throttle:sensitive (5/min)
  → UserController::forgetPassword()
    → UserRepository::findByField('email', ...) — silent if not found
    → Str::random(6) — generates 6-char OTP
    → DB::table('password_resets')->updateOrInsert() — atomic upsert (no race condition)
    → UserRepository::sendResetEmail() — Mail::to()->queue(new ForgetPassword($token))
    → JSON Response
```

### Mail Template

**File:** `resources/views/emails/forget-password.blade.php`

Sends a Markdown email with the 6-character OTP displayed in a code block.

---

## POST /api/v1/verify-forget-password-token

Verify the OTP token is valid and not expired.

**Authentication:** None (public)
**Rate Limit:** 5/min per IP (throttle:sensitive)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes | Registered email |
| otp | string | yes | 6-character token from email |

### Response (200)

```json
{
  "success": true,
  "message": "Token is valid"
}
```

### Response (400)

```json
{
  "success": false,
  "message": "Invalid token"
}
```

### Validation Rules

- Token must exist in `password_resets` table for the given email
- Token hash must match using `Hash::check()`
- Token must not be older than `auth.passwords.users.expire` (default: 60 minutes)

### Execution Flow

```
POST /api/v1/verify-forget-password-token
  → throttle:sensitive (5/min)
  → UserController::verifyForgetPasswordToken()
    → $request->validate([email, otp])
    → checkResetToken() — private method
      → DB::table('password_resets')->where('email', ...)->first()
      → Hash::check($request->otp, $tokenData->token)
      → Carbon::parse(...)->addMinutes(config('auth.passwords.users.expire', 60))->isPast()
    → JSON Response
```

---

## POST /api/v1/reset-password

Reset the password using a verified OTP token.

**Authentication:** None (public)
**Rate Limit:** 5/min per IP (throttle:sensitive)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes | Registered email |
| otp | string | yes | 6-character token from email |
| password | string | yes | New password (min 8, max 50) |
| password_confirmation | string | yes | Must match password |

### Response (200)

```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

### Response (400)

```json
{
  "success": false,
  "message": "Invalid token"
}
```

### Execution Flow

```
POST /api/v1/reset-password
  → throttle:sensitive (5/min)
  → UserController::resetPassword()
    → $request->validate([password, password_confirmation, email, otp]) — outside try/catch (returns 422, not 500)
    → DB::transaction()
      → checkResetToken() — validates OTP + expiry
      → User::where('email', ...)->first()
      → $user->password = Hash::make($request->password)
      → $user->save()
      → $user->tokens()->delete()
      → DB::table('password_resets')->where('email', ...)->delete()
    → JSON Response
```

### Side Effects

- All existing Sanctum tokens for the user are revoked (forces re-login on all devices)
- The `password_resets` record is deleted (prevents token reuse)

---

## POST /api/v1/change-password

Change password for authenticated user.

**Authentication:** Required (auth:sanctum, email.verified)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| oldPassword | string | yes | Current password |
| newPassword | string | yes | New password (min 8, max 50) |
| newPassword_confirmation | string | yes | Must match newPassword |

Note: Fields use camelCase (`oldPassword`, `newPassword`).

### Response (200)

```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

### Execution Flow

```
POST /api/v1/change-password
  → auth:sanctum
  → email.verified
  → UserController::changePassword()
    → ChangePasswordRequest (validation)
    → Hash::check($request->oldPassword, $user->password)
    → $user->password = Hash::make($request->newPassword)
    → $user->tokens()->delete()
    → JSON Response
```

---

## Database Tables

### `password_resets`

| Column | Type | Description |
|--------|------|-------------|
| email | varchar | User email (unique per reset) |
| token | varchar | Hashed OTP token |
| created_at | timestamp | Token generation time |

### Indexes

- Primary key on `email` (upsert uses email as unique identifier)

---

## Mail Configuration

| Setting | Value | Source |
|---------|-------|--------|
| Default mailer | `log` (dev) / any (prod) | `.env` → `MAIL_MAILER` |
| Queue for mail | `high` | Set via `$this->onQueue('high')` in Mailable |
| Queue connection | `database` | `.env` → `QUEUE_CONNECTION` |
| Queue worker | `php artisan queue:work --queue=high,default` | Required to dispatch emails |
| Token expiry | 60 min (configurable) | `config/auth.php` → `passwords.users.expire` |
| From address | per config | `.env` → `MAIL_FROM_ADDRESS` |
| Reset mail class | `Marvel\Mail\ForgetPassword` | `packages/marvel/src/Mail/ForgetPassword.php` |
| Reset mail template | `resources/views/emails/forget-password.blade.php` | Markdown email |
| OTP notification | `Marvel\Notifications\OneTimePasswordNotification` | `config/one-time-passwords.php` |
| OTP template | `resources/views/emails/one-time-passwords.blade.php` | Bilingual (EN/AR) |
