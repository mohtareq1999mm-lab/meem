# Password — QA Test Plan

## Test Environment
- Staging API: `https://staging-api.example.com/api/v1`
- Pre-seeded test user with known email
- Rate limit reset: wait 1 minute or clear cache manually

## 1. Smoke Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Request reset for existing email | 200, email sent |
| 2 | Verify correct OTP | true |
| 3 | Verify incorrect OTP | false |
| 4 | Reset password with valid OTP | 200, can login with new password |

## 2. Functional Tests

### Forget Password
| # | Test | Expected |
|---|------|----------|
| 5 | Reset for user with special chars in email | 200 |
| 6 | Reset for recently registered user | 200 |
| 7 | Multiple reset requests for same email | Each generates new OTP, single record in DB |
| 8 | Reset for inactive user | 404 (user not found by findByField) |
| 9 | Reset for admin user | 200 (works for any user type) |

### Verify OTP
| # | Test | Expected |
|---|------|----------|
| 10 | Verify immediately after request | true |
| 11 | Verify after 4 minutes 59 seconds | true |
| 12 | Verify after 5 minutes 1 second | false (expired) |
| 13 | Verify with leading/trailing spaces in OTP | false (exact match required) |
| 14 | Verify with lowercase OTP (was sent uppercase) | false (case-sensitive) |
| 15 | Verify OTP for wrong email | false |

### Reset Password
| # | Test | Expected |
|---|------|----------|
| 16 | Reset with common password "password123" | 200 |
| 17 | Reset with strong password "Str0ng!Pass#2024" | 200 |
| 18 | Reset and immediately login with old password | 404 |
| 19 | Reset and immediately login with new password | 200 |
| 20 | Use same OTP twice | 400 (second time — OTP deleted) |
| 21 | Request new OTP after failed reset | 200, new OTP works |

## 3. Security Tests

| # | Test | Expected |
|---|------|----------|
| 22 | SQL injection in email field | No SQL error |
| 23 | SQL injection in OTP field | No SQL error |
| 24 | Mass assignment: extra fields in reset | Ignored |
| 25 | OTP visible in server response | Never — only success/failure |
| 26 | Hash::check timing attack | Bcrypt constant-time comparison |
| 27 | Reset password for another user's email | Not possible (OTP is email-bound) |
| 28 | Brute force OTP guessing | 10^62 combinations × 5/min rate limit |

## 4. Cross-Feature Tests

| # | Test | Expected |
|---|------|----------|
| 29 | Reset password, then change password (old password no longer valid) | Change password requires old password (works) |
| 30 | Reset password, then logout | Logout succeeds (no token anyway if reset deleted it) |
| 31 | Reset password while logged in on multiple devices | All sessions invalidated |
| 32 | Password reset + social login user | Social login users have Hash::make('password') as password — can reset to real password |

## 5. Regression Tests

| # | Test | Expected |
|---|------|----------|
| 33 | Full flow: request → verify → reset → login → me → logout | All succeed |
| 34 | Full flow with 4-minute delay between steps | OTP still valid |
| 35 | Full flow with expired OTP (start over) | Works on second attempt |
| 36 | Rate limit hit, wait, complete flow | Works after cooldown |
