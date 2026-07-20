# Auth — Jira Stories

## Epic: AUTH-EPIC-1 — Authentication System

### Story AUTH-1: User Registration
**As a** new visitor
**I want** to register with my name, email, phone, and password
**So that** I can create a customer account

**Acceptance Criteria:**
- [ ] Registration requires: first_name, last_name, email, phone_number, password, password_confirmation, policy
- [ ] Email must be unique and RFC-compliant with DNS check
- [ ] Password minimum 8 characters, must be confirmed
- [ ] Policy must be accepted (in: 1, true)
- [ ] On success: user created with type='user', role='customer', is_active=true
- [ ] OTP email sent via sendOneTimePassword()
- [ ] Returns 200 with otp_status on success
- [ ] Returns 201 with requires_resend if OTP mail fails
- [ ] Returns 422 on validation failure
- [ ] Rate limited: 10 requests/min per IP
- [ ] Avatar upload supported via Spatie MediaLibrary

### Story AUTH-2: User Login
**As a** registered user
**I want** to log in with my email or phone number and password
**So that** I can access my account

**Acceptance Criteria:**
- [ ] Login by email OR phone_number
- [ ] Returns Sanctum token on success
- [ ] Returns email_verified flag in response
- [ ] Fires AdminLoggedIn event
- [ ] Invalid credentials return 404 with INVALID_CREDENTIALS
- [ ] Inactive users cannot log in
- [ ] Rate limited: 10 requests/min per IP

### Story AUTH-3: Admin Login
**As an** admin user
**I want** to log in with my verified email
**So that** I can access the admin panel

**Acceptance Criteria:**
- [ ] Requires type='admin'
- [ ] Requires email_verified_at to be set
- [ ] Returns token + permissions + role in response
- [ ] Non-admin users get 404 USER_NOT_FOUND
- [ ] Unverified admins get 404 USER_NOT_VERIFIED

### Story AUTH-4: Social Login
**As a** visitor
**I want** to log in with Google or Facebook
**So that** I don't need to create a separate password

**Acceptance Criteria:**
- [ ] Supports Google and Facebook providers
- [ ] Verifies access_token via Socialite
- [ ] Creates user if email not found (firstOrCreate)
- [ ] Stores provider info in user_providers table
- [ ] Auto-verifies email for OAuth users
- [ ] Invalid provider returns 422
- [ ] Invalid token returns 422

### Story AUTH-5: Current User Profile
**As a** logged-in user
**I want** to view my profile
**So that** I can see my account details

**Acceptance Criteria:**
- [ ] Requires auth:sanctum
- [ ] Returns user data with wallet, addresses, shop, profile, role
- [ ] 401 if not authenticated

### Story AUTH-6: Logout
**As a** logged-in user
**I want** to log out
**So that** my session ends

**Acceptance Criteria:**
- [ ] Requires auth:sanctum
- [ ] Deletes current access token
- [ ] NOT rate limited (users must always be able to log out)
- [ ] Returns 404 if no user found (should not happen with auth middleware)

### Story AUTH-7: Password Reset
**As a** registered user
**I want** to reset my forgotten password
**So that** I can regain access to my account

**Acceptance Criteria:**
- [ ] Forget password sends 6-char OTP to email
- [ ] OTP stored hashed in password_resets table
- [ ] OTP expires after 5 minutes
- [ ] Verify token endpoint returns boolean
- [ ] Reset password requires: email, otp, password, password_confirmation
- [ ] Reset deletes all existing tokens (force re-login)
- [ ] Reset cleans up password_resets record
- [ ] Rate limited: 5 requests/min per IP for all three endpoints

### Story AUTH-8: Phone OTP Authentication (Disabled)
**As a** user
**I want** to authenticate via phone OTP
**So that** I can log in without a password

**Acceptance Criteria:**
- [ ] Send OTP code via SMS gateway (configurable)
- [ ] Verify OTP code
- [ ] Return Sanctum token on successful verification
- [ ] Rate limited: 3 requests/min per IP
- [ ] Routes disabled by default
- [ ] Fallback to LocalGateway if configured gateway unavailable

### Story AUTH-9: Rate Limiter Protection
**As a** security engineer
**I want** rate limiters on auth endpoints
**So that** brute force and credential stuffing attacks are mitigated

**Acceptance Criteria:**
- [ ] throttle:auth → 10/min on register, token, admin-login, social-login
- [ ] throttle:sensitive → 5/min on password reset endpoints
- [ ] throttle:otp → 3/min on phone OTP endpoints
- [ ] All throttle keys are per-IP
- [ ] 429 response with Retry-After header
- [ ] Logout is not rate limited
