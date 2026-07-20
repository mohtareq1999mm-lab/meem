# Auth Module

## Overview
Customer and admin authentication system supporting email/password login, registration, social login (Google/Facebook), password reset via email OTP, and phone-based OTP authentication (disabled). All auth endpoints are organized under three rate limiter groups to prevent abuse.

## Rate Limiters
| Limiter | Limit | Scope | Applied To |
|---------|-------|-------|------------|
| `throttle:auth` | 10/min per IP | Authentication attempts | register, token, admin-login, social-login-token |
| `throttle:sensitive` | 5/min per IP | Password operations | forget-password, verify-forget-password-token, reset-password |
| `throttle:otp` | 3/min per IP | Phone OTP (disabled) | send-otp-code, otp-login |

## Endpoints

### Authentication (throttle:auth)
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/register` | None | Register a new customer account |
| POST | `/token` | None | Login with email + password |
| POST | `/admin-login` | None | Admin login (requires verified email) |
| POST | `/social-login-token` | None | Login via Google/Facebook OAuth |

### Authenticated User
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| GET | `/me` | `auth:sanctum` | Get current user profile |
| POST | `/logout` | `auth:sanctum` | Logout (revoke current token) |

### Password Reset (throttle:sensitive)
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/forget-password` | None | Request password reset (sends 6-digit OTP) |
| POST | `/verify-forget-password-token` | None | Verify reset OTP validity |
| POST | `/reset-password` | None | Reset password with OTP |

### Phone OTP — DISABLED (throttle:otp)
| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/send-otp-code` | None | Send OTP via SMS (disabled) |
| POST | `/otp-login` | None | Login via phone OTP (disabled) |

## Key Architecture Decisions
1. **Rate limiters are per-IP** (not per-user) for unauthenticated endpoints to block brute-force at the network level.
2. **Logout is NOT rate limited** — users must always be able to log out regardless of rate limit state.
3. **`/me` route is registered twice** (line 102 and line 134 in Routes.php) — once in the public section and once unprotected. The protected version takes precedence via route ordering.
4. **Admin login requires `email_verified`** — regular user login does not.
5. **Registration auto-assigns "customer" role** and sends an OTP email for email verification.
6. **Password reset uses a plaintext 6-character OTP** stored hashed in `password_resets` table (not Laravel's built-in reset).
7. **OTP routes are commented/disconnected** — the route group exists but is typically disabled in production deployments.
