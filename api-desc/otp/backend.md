# OTP — Backend Implementation

## Controller Methods

| Endpoint | Method | File | Line |
|----------|--------|------|------|
| send-otp-code | `sendUserOtp()` | `packages/marvel/src/Http/Controllers/UserController.php` | 547 |
| otp-login | `otpLogin()` | `packages/marvel/src/Http/Controllers/UserController.php` | 1099 |

## Supporting Methods

| Method | Visibility | Line | Purpose |
|--------|-----------|------|---------|
| `sendOtpCode()` | public | 1047 | Delegates to OTP gateway, returns response array |
| `verifyOtp()` | protected | 1026 | Verifies OTP via gateway with otp_id + code |
| `verifyLoginOtp()` | protected | 573 | Email-based OTP verification |
| `getOtpGateway()` | protected | 1013 | Resolves gateway from config, falls back to LocalGateway |
| `validateProvider()` | protected | 1005 | Validates social provider (not OTP-specific) |

## Controller
- **File**: `packages/marvel/src/Http/Controllers/UserController.php`
- **Extends**: `CoreController`
- **Traits**: `WalletsTrait`, `UsersTrait`, `ApiResponse`

## Dependencies

### OTP Gateways System
| File | Class | Description |
|------|-------|-------------|
| `packages/marvel/src/Otp/Gateways/OtpGateway.php` | `OtpGateway` | Facade/adapter wrapping concrete gateways |
| `packages/marvel/src/Otp/Gateways/TwilioGateway.php` | `TwilioGateway` | Twilio SMS implementation |
| `packages/marvel/src/Otp/Gateways/LocalGateway.php` | `LocalGateway` | Testing/fallback — no external dependency |

### Models
| Model | Queried By |
|-------|-----------|
| `Marvel\Database\Models\User` | Email or phone_number lookup |

### Mail
| Method | Description |
|--------|-------------|
| `User::sendOneTimePassword()` | Sends OTP email (used when email provided to sendUserOtp) |

## Key Implementation Details

### sendUserOtp()
1. Validates email XOR phone_number
2. Looks up active user by email or phone
3. If email → calls `$user->sendOneTimePassword()` (Laravel Notification)
4. If phone → calls `$this->sendOtpCode($request)` which uses the gateway
5. Returns static OTP data including `otp_id` for verification step
6. ⚠ Uses `USER_LOGGED_IN_SUCCESSFULLY` translation key (misleading — user hasn't logged in yet)

### sendOtpCode()
1. Checks phone_number is not empty
2. Resolves gateway via `getOtpGateway()`
3. Calls `$otpGateway->startVerification($phoneNumber)`
4. Returns response with `otp_id`, `provider`, `phone_number`, `is_contact_exist`

### getOtpGateway()
1. Reads `config('auth.active_otp_gateway')` — e.g. "twilio"
2. Builds class name: `Marvel\Otp\Gateways\TwilioGateway`
3. Wraps in `OtpGateway` facade
4. On failure: logs warning and falls back to `LocalGateway`

### otpLogin()
1. If email present → delegates to `verifyLoginOtp()`
2. If phone_number present → calls `verifyOtp()`, looks up user by phone, creates token
3. Returns Sanctum token on success

### verifyOtp()
1. Reads `$request->otp_id`, `$request->code`, `$request->phone_number`
2. Calls `$otpGateway->checkVerification($id, $code, $phoneNumber)`
3. Returns `isValid()` boolean

### verifyLoginOtp()
1. Validates email + otp
2. Calls `$user->validateOneTimePassword($request->otp)`
3. Returns token on success

## Routes (Currently Disabled)
```php
// packages/marvel/src/Rest/Routes.php:118-121
Route::middleware(['throttle:otp'])->group(function () {
    Route::post('/send-otp-code', [UserController::class, 'sendUserOtp']);
    Route::post('/otp-login', [UserController::class, 'otpLogin']);
});
```

## Tests
- **Feature Test Files**: None found specifically for OTP endpoints
- OTP-related assertions would need mocked gateways
