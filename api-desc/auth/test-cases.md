# Auth — Test Cases

## Setup
```php
use Tests\TestCase;
use Marvel\Database\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
```

## Registration

### Success Cases
1. **Register a new customer** — POST /register with valid data → 200, user created in DB, role 'customer' assigned, `personal_access_tokens` not created
2. **Register with avatar** — POST /register with valid image file → 200, media record created in `media` table with collection_name='avatar'
3. **Register without avatar** — POST /register without avatar file → 200, no media record

### Validation Failures
4. **Missing first_name** → 422
5. **Missing email** → 422
6. **Invalid email format** → 422
7. **Duplicate email** → 422
8. **Password too short (< 8)** → 422
9. **Password confirmation mismatch** → 422
10. **Policy not accepted** → 422
11. **Missing phone_number** → 422

### Business Rules
12. **New user type is 'user'** → assert DatabaseHas with type='user'
13. **New user is_active is true** → assert DatabaseHas with is_active=1
14. **Customer role assigned** → assert user has role 'customer'
15. **OTP email sent** → assert Notification::assertSentTo

## Login (token)

### Success Cases
16. **Login with valid email/password** → POST /token → 200, token returned
17. **Login with valid phone_number/password** → POST /token with phone_number → 200, token returned
18. **AdminLoggedIn event dispatched** → Event::assertDispatched(AdminLoggedIn::class)

### Failure Cases
19. **Invalid password** → POST /token with wrong password → 404 'Invalid credentials'
20. **Non-existent email** → POST /token with unknown email → 404 'Invalid credentials'
21. **Inactive user** → POST /token with is_active=false user → 404 'Invalid credentials'

## Admin Login

### Success Cases
22. **Admin login with verified email** → POST /admin-login → 200, includes permissions and role in response

### Failure Cases
23. **Customer tries admin login** → POST /admin-login with customer credentials → 404 'User not found'
24. **Admin without verified email** → POST /admin-login with unverified admin → 404 'User not verified'

## Social Login

### Success Cases
25. **Social login with valid Google token (mock)** → POST /social-login-token → 200, user created if new
26. **Existing user social login** → user exists with same email → 200, no duplicate

### Failure Cases
27. **Invalid provider** → POST /social-login-token with provider='twitter' → exception
28. **Invalid access token** → POST /social-login-token with fake token → 422

## Get Current User

### Success Cases
29. **Get authenticated user profile** → GET /me with valid token → 200, user data returned
30. **Response includes role** → assert response has 'role' field
31. **Response includes wallet** → assert response has 'wallet' field (if wallet exists)

### Failure Cases
32. **No token** → GET /me without Bearer token → 401
33. **Invalid token** → GET /me with fake token → 401

## Logout

### Success Cases
34. **Logout with valid token** → POST /logout → 200, token deleted from DB

### Failure Cases
35. **Logout without auth** → POST /logout without token → 401

## Forget Password

### Success Cases
36. **Request password reset** → POST /forget-password with existing email → 200
37. **Password reset token created** → assert password_resets table has record

### Failure Cases
38. **Non-existent email** → POST /forget-password → 404
39. **Too many requests** → exceed throttle:sensitive (6th request) → 429

## Verify Forget Password Token

### Success Cases
40. **Valid OTP** → POST /verify-forget-password-token with correct OTP → true

### Failure Cases
41. **Invalid OTP** → POST /verify-forget-password-token with wrong OTP → false
42. **Expired OTP** → travel 6 minutes → false
43. **No token for email** → random email → false

## Reset Password

### Success Cases
44. **Reset password with valid OTP** → POST /reset-password → 200, password updated
45. **All tokens deleted after reset** → assert personal_access_tokens count = 0
46. **password_resets cleaned up** → assert record deleted

### Failure Cases
47. **Invalid OTP** → POST /reset-password with wrong OTP → 400
48. **Password too short** → POST /reset-password with 6-char password → 422
49. **Password confirmation mismatch** → 422
50. **Missing fields** → 422

## Rate Limiting

### throttle:auth
51. **11th register request in 1 minute** → 429
52. **11th token request in 1 minute** → 429
53. **11th admin-login request in 1 minute** → 429
54. **11th social-login request in 1 minute** → 429

### throttle:sensitive
55. **6th forget-password request in 1 minute** → 429

### Logout (no throttle)
56. **Rapid logout requests** → all succeed (no 429)

## Edge Cases
57. **Register with existing email (soft-deleted user)** → 422 unique constraint
58. **Login with email that exists but inactive** → 404
59. **Password reset for user with multiple tokens** → all tokens deleted
60. **Register and immediately login** → verify password hash matches
61. **Social login creates user with Hash::make('password')** — verify user can NOT log in with 'password' via token endpoint (password is a fallback only)
