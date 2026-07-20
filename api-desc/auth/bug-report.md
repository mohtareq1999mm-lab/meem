# Auth — Bug Reports

## Bug 1: Verify-Forget-Password-Token Returns Raw Boolean Instead of API Response

**Severity**: LOW
**File**: `packages/marvel/src/Http/Controllers/UserController.php:798-821`
**Endpoint**: `POST /api/v1/verify-forget-password-token`

**Description**: The `verifyForgetPasswordToken()` method returns a raw PHP `true`/`false` value instead of a structured JSON API response. All other endpoints use `$this->apiResponse()`. This inconsistency breaks the frontend if it expects a standard `{ success: true, data: boolean }` envelope.

**Impact**: Frontend must handle a bare `true`/`false` response body with Content-Type `text/html` (Laravel default for boolean return), not `application/json`.

**Reproduction**:
1. POST /api/v1/verify-forget-password-token with valid email and OTP
2. Response body is `true` (raw, not JSON)
3. POST with invalid OTP → response body is empty (false returns nothing)

**Suggested Fix**: Wrap the boolean in a proper JSON response:
```php
public function verifyForgetPasswordToken(Request $request)
{
    // ... existing logic ...
    if (!$tokenData || !Hash::check(...) || Carbon::parse(...)->isPast()) {
        return response()->json(['success' => true, 'data' => false]);
    }
    return response()->json(['success' => true, 'data' => true]);
}
```

---

## Bug 2: Custom Password Reset Breaks Laravel's Built-in Middleware Integration

**Severity**: LOW
**File**: `packages/marvel/src/Http/Controllers/UserController.php:762-852`
**Routes**: forget-password, reset-password

**Description**: The password reset flow uses a custom `password_resets` table implementation instead of Laravel's built-in `PasswordBroker`. This means:
- No integration with `Password::reset()` or `Password::sendResetLink()`
- No notification class reuse
- Custom OTP length (6 chars) vs Laravel's default (64-char random string)
- No built-in throttle on the password broker side (though route-level throttle exists)

**Impact**: Harder to maintain, no password broker events/broker, custom email sending.

**Suggested Fix**: Consider wrapping the custom logic with Laravel's Password facade for broker integration, or document clearly why custom logic is used (custom OTP length requirement).

---

## Bug 3: SendUserOtp Returns LOGGED_IN Message on OTP Send

**Severity**: LOW
**File**: `packages/marvel/src/Http/Controllers/UserController.php:571`
**Endpoint**: `POST /api/v1/send-otp-code` (DISABLED)

**Description**: `sendUserOtp()` returns the translation key `USER_LOGGED_IN_SUCCESSFULLY` when it successfully sends an OTP code. The message is misleading — user has not logged in, only an OTP was sent.

```php
return $this->apiResponse(USER_LOGGED_IN_SUCCESSFULLY, 200, true, $data);
```

**Impact**: Frontend reading the message string would show "User logged in successfully" after requesting an OTP code.

**Suggested Fix**: Replace with a dedicated translation key like `OTP_SENT_SUCCESSFULLY`.

---

## Bug 4: /me Route Registered Twice

**Severity**: LOW
**File**: `packages/marvel/src/Rest/Routes.php:102,134`

**Description**: `GET /me` is registered twice:
1. Line 102: `Route::get('me', [UserController::class, 'me'])->middleware('auth:sanctum');`
2. Line 134: `Route::get('me', [UserController::class, 'me']);`

The second registration (line 134) is unprotected (no auth middleware). Due to route ordering, the first `auth:sanctum` version should take precedence, but if the route registration order changes, `/me` could become publicly accessible.

**Impact**: Potential information disclosure if route order changes.

**Suggested Fix**: Remove the duplicate at line 134. It appears to be an artifact from an older code structure.
