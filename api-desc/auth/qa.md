# Auth — QA Test Plan

## Test Environment
- Staging API URL: `https://staging-api.example.com/api/v1`
- Test accounts: pre-seeded customer, admin (verified), admin (unverified), inactive user
- Rate limiter reset: wait 1 minute or use `Cache::forget()` for throttle keys

## 1. Smoke Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Register a new user | 200, user exists in DB |
| 2 | Login with new user | 200, token returned |
| 3 | Get current user | 200, correct user data |
| 4 | Logout | 200, token invalidated |
| 5 | Try authenticated request after logout | 401 |

## 2. Functional Tests

### Registration
| # | Test | Expected |
|---|------|----------|
| 6 | Register with all valid fields | 200, otp_status: true |
| 7 | Register with minimum fields | 200 |
| 8 | Register with special chars in name | 200 |
| 9 | Register with Arabic/UTF-8 name | 200 |
| 10 | Register without accepting policy | 422 |
| 11 | Register with mismatched passwords | 422 |
| 12 | Register with existing email | 422 |
| 13 | Register with invalid email (no @) | 422 |
| 14 | Register with phone < 10 digits | 422 |

### Login
| # | Test | Expected |
|---|------|----------|
| 15 | Login with correct email + password | 200, token |
| 16 | Login with correct phone + password | 200, token |
| 17 | Login with correct email + wrong password | 404 |
| 18 | Login with wrong email | 404 |
| 19 | Login with inactive user | 404 |
| 20 | Login with empty password | 422 |

### Admin Login
| # | Test | Expected |
|---|------|----------|
| 21 | Admin login with verified email | 200, permissions + role in response |
| 22 | Admin login with unverified email | 404 |
| 23 | Customer attempts admin login | 404 |

### Social Login
| # | Test | Expected |
|---|------|----------|
| 24 | Google login with valid access_token (mock) | 200 |
| 25 | Facebook login with valid access_token (mock) | 200 |
| 26 | Login with unsupported provider | 422 |
| 27 | Login with expired/invalid token | 422 |

### Password Reset
| # | Test | Expected |
|---|------|----------|
| 28 | Request reset for existing email | 200 |
| 29 | Request reset for non-existing email | 404 |
| 30 | Verify valid OTP | true |
| 31 | Verify invalid OTP | false |
| 32 | Verify expired OTP (wait 6 min) | false |
| 33 | Reset password with valid OTP | 200, can login with new password |
| 34 | Reset password with invalid OTP | 400 |
| 35 | Reset password with too-short new password | 422 |
| 36 | After reset, old token cannot be used | 401 |
| 37 | After reset, login with old password fails | 404 |

## 3. Rate Limiting Tests

| # | Test | Expected |
|---|------|----------|
| 38 | Send 11 register requests rapidly | 10th succeeds, 11th is 429 |
| 39 | Send 11 login requests rapidly | 10th succeeds, 11th is 429 |
| 40 | Send 6 forget-password requests rapidly | 5th succeeds, 6th is 429 |
| 41 | Send 10 logout requests rapidly | All 200 (no throttle) |
| 42 | Wait 1 minute after 429 | Requests succeed again |

## 4. Security Tests

| # | Test | Expected |
|---|------|----------|
| 43 | SQL injection in email field | 422 or 404, no SQL error |
| 44 | XSS in name field | Stored as-is, no script execution |
| 45 | Mass assignment: pass `type=admin` in register | type forced to 'user' |
| 46 | Mass assignment: pass `is_active=true` | Controller sets it explicitly |
| 47 | Token in response body (not just header) | Yes — Sanctum returns in body |
| 48 | Password in response | Never — only token returned |
| 49 | Reset password without OTP field | 422 |
| 50 | Try to use another user's reset token | Fails (email-bound) |

## 5. Regression Tests

| # | Test | Expected |
|---|------|----------|
| 51 | Full flow: register → email verify → login → me → logout | All steps succeed |
| 52 | Full flow: register → forget password → reset → login with new password | All steps succeed |
| 53 | Full flow: admin login → me → logout | All steps succeed |
| 54 | Token reuse after logout | 401 |
