# Authentication API

## POST /api/v1/register

Register a new user account.

**Authentication:** None (public)
**Rate Limit:** 10/min per IP (throttle:auth)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| first_name | string | yes | User's first name |
| last_name | string | yes | User's last name |
| email | string | yes | Valid email address |
| password | string | yes | Min 8 chars |
| phone_number | string | yes | Min 11 chars |
| avatar | file | no | Profile picture |

### Response (200)

```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "otp_status": true
  }
}
```

### Response (201 — OTP email failed)

```json
{
  "success": true,
  "message": "Account created but OTP email could not be sent",
  "data": {
    "otp_status": false,
    "requires_resend": true,
    "email": "user@example.com",
    "phone_number": "12345678901"
  }
}
```

### Execution Flow

```
POST /api/v1/register
  → throttle:auth (10/min)
  → UserController::register()
    → UserCreateRequest (validation)
    → DB::beginTransaction()
    → UserRepository::create()
    → assignRole('customer')
    → DB::commit()
    → User::sendOneTimePassword() (Spatie OTP notification — queued via ShouldQueue, dispatched to 'high' queue)
    → JSON Response
```

---

## POST /api/v1/token

Login with email and password.

**Authentication:** None (public)
**Rate Limit:** 10/min per IP (throttle:auth)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes | Registered email |
| password | string | yes | Account password |

### Response (200)

```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "token": "1|sanctum_token_string",
    "permissions": ["view-products", "create-products"],
    "role": "customer"
  }
}
```

### Execution Flow

```
POST /api/v1/token
  → throttle:auth (10/min)
  → UserController::token()
    → findByField('email', ...)
    → Hash::check(password)
    → User::createToken('auth_token')
    → JSON Response (token + permissions + role)
```

---

## POST /api/v1/logout

Revoke current access token.

**Authentication:** Required (auth:sanctum)

### Request Body

None

### Response (200)

```json
{
  "success": true,
  "message": "User logged out successfully"
}
```

### Execution Flow

```
POST /api/v1/logout
  → auth:sanctum
  → UserController::logout()
    → $request->user()->currentAccessToken()->delete()
    → JSON Response
```

---

## GET /api/v1/me

Get current authenticated user profile.

**Authentication:** Required (auth:sanctum)

### Response (200)

```json
{
  "success": true,
  "message": "Profile fetched successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2026-07-22T10:00:00Z",
    "is_active": true,
    "type": "user",
    "phone_number": "12345678901",
    "permissions": [],
    "role": "customer"
  }
}
```

### Execution Flow

```
GET /api/v1/me
  → auth:sanctum
  → UserController::me()
    → UserResource
    → JSON Response
```

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

Note: Fields use camelCase (`oldPassword`, `newPassword`), not snake_case.

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
    → Hash::check(oldPassword)
    → Hash::make(newPassword) → save
    → $user->tokens()->delete()
    → JSON Response
```

---

## POST /api/v1/send-otp-code

Send an OTP code via email for phone-based authentication.

**Authentication:** None (public)
**Rate Limit:** 3/min per IP (throttle:otp)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes (if phone not provided) | User email |
| phone_number | string | yes (if email not provided) | User phone |

### Response (200)

```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "otp_id": 42
  }
}
```

### Response (201 — mail failed)

```json
{
  "success": true,
  "message": "Account created but OTP email could not be sent",
  "data": {
    "otp_status": false,
    "requires_resend": true
  }
}
```

### Execution Flow

```
POST /api/v1/send-otp-code
  → throttle:otp (3/min)
  → UserController::sendUserOtp()
    → validate(email|phone_number)
    → User::where(email|phone_number, is_active) → first
    → if not found → 404
    → if email:
        → User::createOneTimePassword()
        → notify(OneTimePasswordNotification) → queued on 'high'
        → $data['otp_id'] = $oneTimePassword->id
    → if phone:
        → sendOtpCode($request)
        → $data['otp_id'] from response
    → JSON Response (200, data includes otp_id)
```

### Notes

- Returns `otp_id` in `data` — frontend should store this to track the OTP verification session
- OTP email is **queued** (ShouldQueue, `high` queue) — do NOT show instant success; allow brief delay
- On mail failure, returns 201 with `otp_status: false`, `requires_resend: true`
- This endpoint is currently DISABLED in routes (uncomment Routes.php to enable)
- Queue worker must be running: `php artisan queue:work --queue=high,default`

---

## POST /api/v1/otp-login

Verify OTP code and receive authentication token.

**Authentication:** None (public)

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | yes (if phone not provided) | Registered email |
| phone_number | string | yes (if email not provided) | Registered phone |
| code | string | yes | 4-6 digit OTP code |

### Response (200)

```json
{
  "success": true,
  "message": "User logged in successfully",
  "data": {
    "token": "1|sanctum_token_string"
  }
}
```

### Response (400)

```json
{
  "success": false,
  "message": "OTP verification failed"
}
```

### Execution Flow

```
POST /api/v1/otp-login
  → UserController::otpLogin()
    → if email:
        → UserController::verifyLoginOtp()
          → validate(email, code)
          → User::where(email, is_active) → first
          → verifyOneTimePassword(code)
          → createToken('auth_token')
          → JSON Response (200, {token})
    → if phone_number:
        → verifyOtp(request)
        → User::where(phone_number) → first
        → createToken('auth_token')
        → JSON Response (200, {token})
    → on failure → 400 or 422
```
