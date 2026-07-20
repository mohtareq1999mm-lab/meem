# Auth — Frontend Jira Stories

## Epic: AUTH-FE-EPIC-1 — Frontend Authentication

### Story AUTH-FE-1: Registration Page
**As a** new visitor
**I want** a registration form
**So that** I can create an account

**Acceptance Criteria:**
- [ ] Form fields: first_name, last_name, email, phone_number, password, password_confirmation, policy checkbox
- [ ] Client-side validation: email format, password length (8+), password match, policy required
- [ ] Show field-level error messages from API (422)
- [ ] Submit to POST /api/v1/register
- [ ] On 200: redirect to email verification prompt or dashboard
- [ ] On 201: show "OTP email may not have been sent — you can resend"
- [ ] On 429: show rate limit message with timer
- [ ] Disabled submit button while loading
- [ ] Social login buttons (Google, Facebook)

### Story AUTH-FE-2: Login Page
**As a** registered user
**I want** a login form
**So that** I can access my account

**Acceptance Criteria:**
- [ ] Form fields: email, password
- [ ] "Remember me" option (store token in localStorage)
- [ ] Submit to POST /api/v1/token
- [ ] Store token on success
- [ ] Check email_verified flag
- [ ] Redirect based on role: admin → /admin/dashboard, customer → /
- [ ] Show error on invalid credentials
- [ ] Inactive user message
- [ ] 429 rate limit handling
- [ ] "Forgot password?" link → password reset flow

### Story AUTH-FE-3: Admin Login Page
**As an** admin
**I want** to log in with verified email
**So that** I can access the admin panel

**Acceptance Criteria:**
- [ ] Same form as regular login but calls POST /api/v1/admin-login
- [ ] Stores permissions and role from response
- [ ] Shows "Please verify your email" on USER_NOT_VERIFIED
- [ ] Shows "No admin account found" on USER_NOT_FOUND

### Story AUTH-FE-4: Social Login Buttons
**As a** visitor
**I want** to log in with Google or Facebook
**So that** I can skip manual registration

**Acceptance Criteria:**
- [ ] Google login button using @react-oauth/google
- [ ] Facebook login button using react-facebook-login
- [ ] Extract access_token and send to POST /api/v1/social-login-token
- [ ] Same post-login flow as regular login
- [ ] Handle provider errors gracefully

### Story AUTH-FE-5: Password Reset Flow
**As a** user who forgot their password
**I want** to reset it via email OTP
**So that** I can regain access

**Acceptance Criteria:**
- [ ] Step 1: Enter email → POST /forget-password → "Check your inbox"
- [ ] Step 2: Enter 6-digit OTP → POST /verify-forget-password-token → advance on true
- [ ] Step 3: Enter new password + confirmation → POST /reset-password
- [ ] Handle raw true/false response from verify endpoint
- [ ] Show "Invalid or expired OTP" on error
- [ ] OTP input: 6 characters, auto-advance on mobile
- [ ] Rate limit handling (5/min)

### Story AUTH-FE-6: Auth State Management
**As a** frontend developer
**I want** a global auth state
**So that** all components can check authentication status

**Acceptance Criteria:**
- [ ] On app mount: check localStorage for token
- [ ] If token exists: call GET /api/v1/me to validate
- [ ] Store user data in global state (context/redux)
- [ ] Provide `isAuthenticated`, `user`, `role`, `permissions` to all components
- [ ] On 401 from any API call: clear token, redirect to login
- [ ] On logout: clear token and user state
- [ ] Handle token expiry (Sanctum does not auto-expire by default)

### Story AUTH-FE-7: Profile Dropdown / User Menu
**As a** logged-in user
**I want** to see my name and avatar in the header
**So that** I know I'm logged in

**Acceptance Criteria:**
- [ ] Show user avatar (or initials if no avatar)
- [ ] Show user name
- [ ] Dropdown menu: Profile, Orders, Settings, Logout
- [ ] Admin users see "Admin Panel" link

### Story AUTH-FE-8: Protected Route Guards
**As a** frontend developer
**I want** route guards for authenticated pages
**So that** unauthenticated users are redirected to login

**Acceptance Criteria:**
- [ ] Pages requiring auth redirect to /login if not authenticated
- [ ] Admin pages redirect to /admin/login if not admin
- [ ] Login/register pages redirect to / if already authenticated
- [ ] Loading state while checking auth on app mount

## Jest Test Cases

### AuthService API layer
1. `register()` — POST /api/v1/register with valid data → returns otp_status
2. `login()` — POST /api/v1/token → returns token
3. `adminLogin()` — POST /api/v1/admin-login → returns token + permissions
4. `socialLogin()` — POST /api/v1/social-login-token → returns token
5. `getMe()` — GET /api/v1/me → returns user data
6. `logout()` — POST /api/v1/logout → clears token
7. `forgetPassword()` — POST /api/v1/forget-password → success message
8. `verifyOtp()` — POST /api/v1/verify-forget-password-token → true/false
9. `resetPassword()` — POST /api/v1/reset-password → success message
10. All API calls handle 401 → auto-logout
11. All API calls handle 429 → rate limit error message
12. All API calls handle 422 → field validation errors

### AuthContext / AuthProvider
13. `useAuth()` returns isAuthenticated=false when no token
14. `useAuth()` returns user data when token is valid
15. `login()` updates auth state
16. `logout()` clears auth state
17. `useAuth()` redirects to login on 401
