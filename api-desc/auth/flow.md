# Auth — Flow Diagrams

## 1. Registration Flow

```
[Client] → POST /register (first_name, last_name, email, phone_number, password)
  │
  ├─ throttle:auth (10/min per IP)
  │
  ▼
[UserCreateRequest] → validate
  │
  ▼
[UserRepository::create()] → INSERT users (type='user', is_active=true)
  │
  ▼
[assignRole('customer')] → INSERT model_has_roles
  │
  ▼
[Media upload] → avatar → INSERT media (if file present)
  │
  ▼
[sendOneTimePassword()] → Send OTP email
  │
  ├─ Success → 200 { otp_status: true }
  └─ Fail    → 201 { requires_resend: true, otp_status: false }
```

## 2. Login Flow

```
[Client] → POST /token (email|phone_number, password)
  │
  ├─ throttle:auth (10/min per IP)
  │
  ▼
[User] = WHERE email=$email OR phone_number=$phone AND is_active=true
  │
  ├─ !found or !Hash::check → 404 INVALID_CREDENTIALS
  │
  ▼
[createToken('auth_token')] → INSERT personal_access_tokens
  │
  ▼
[dispatch AdminLoggedIn]
  │
  ▼
200 { token, email_verified }
```

## 3. Admin Login Flow

```
[Client] → POST /admin-login (email, password)
  │
  ├─ throttle:auth (10/min per IP)
  │
  ▼
[User] = WHERE email=$email AND is_active=true
  │
  ├─ !found or !Hash::check → 404 INVALID_CREDENTIALS
  ├─ type !== 'admin' → 404 USER_NOT_FOUND
  ├─ !hasVerifiedEmail() → 404 USER_NOT_VERIFIED
  │
  ▼
[createToken('auth_token')] → INSERT personal_access_tokens
  │
  ▼
[dispatch AdminLoggedIn]
  │
  ▼
200 { token, permissions, email_verified, role }
```

## 4. Password Reset Flow

```
[Client] → POST /forget-password (email)
  │
  ├─ throttle:sensitive (5/min per IP)
  │
  ▼
[User] = findByField('email', $email)
  │
  ├─ count < 1 → 404 NOT_FOUND
  │
  ▼
[password_resets] → upsert { email, token: Hash::make(Str::random(6)) }
  │
  ▼
[sendResetEmail($email, $plainTextToken)] → Send email with 6-char OTP
  │
  ├─ true  → 200 CHECK_INBOX
  └─ false → 500 SOMETHING_WENT_WRONG
```

```
[Client] → POST /verify-forget-password-token (email, otp)
  │
  ├─ throttle:sensitive
  │
  ▼
[password_resets] = WHERE email=$email
  │
  ├─ !found → false
  ├─ !Hash::check(otp, token) → false
  ├─ created_at + 5min < now() → false
  │
  ▼
true (raw boolean)
```

```
[Client] → POST /reset-password (email, otp, password, password_confirmation)
  │
  ├─ throttle:sensitive
  │
  ▼
[validate]
  │
  ▼
[verifyForgetPasswordToken()]
  │
  ├─ false → 400 INVALID_TOKEN
  │
  ▼
[DB::transaction]
  ├─ UPDATE users SET password = Hash::make($password)
  ├─ DELETE personal_access_tokens WHERE tokenable_id = $user->id
  └─ DELETE password_resets WHERE email = $email
  │
  ▼
200 PASSWORD_RESET_SUCCESSFUL
```

## 5. Social Login Flow

```
[Client] → POST /social-login-token (provider, access_token)
  │
  ├─ throttle:auth
  │
  ▼
[validateProvider] → provider in ['facebook', 'google']
  │
  ├─ invalid → 422 PLEASE_LOGIN_USING_FACEBOOK_OR_GOOGLE
  │
  ▼
[Socialite::driver($provider)->userFromToken($token)]
  │
  ├─ Exception → 422 INVALID_CREDENTIALS
  │
  ▼
[User::firstOrCreate(['email' => $email], [...])]
  │
  ▼
[user_providers::updateOrCreate(...)]
  │
  ▼
[createToken('auth_token')] → INSERT personal_access_tokens
  │
  ▼
200 { token }
```
