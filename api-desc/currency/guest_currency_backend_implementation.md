# Guest Currency — Backend Implementation (Frontend-Owned Cookie)

**Status:** Implemented
**Date:** 2026-09-07
**Scope:** Laravel backend. The Frontend owns the `guest_currency` cookie; the backend reads + validates it through the centralized `CurrencyService`.

---

## 1. Current Architecture (after this change)

```
Guest
  ↓
Frontend
  ↓
guest_currency cookie  (plaintext "KWD", HttpOnly=false, SameSite=Lax, host-only)
  ↓
Browser automatically attaches Cookie: guest_currency=KWD on every API request
  ↓
Laravel (EncryptCookies skips guest_currency — no decryption)
  ↓
CurrencyService::getEffectiveCode()
  ↓
Authenticated User Preference
        OR
Guest Currency Cookie
        OR
Default/Catalog Currency
```

---

## 2. Previous Flow (before this change)

- The **backend** owned the cookie: `POST /api/v1/general/currencies/select` queued a Laravel-**encrypted**, **HttpOnly** `guest_currency` cookie (`Cookie::make(..., minutes, path)` with no explicit `httpOnly`, so Laravel defaulted to `true`, and `EncryptCookies::$except = []` encrypted it).
- The Frontend could **not** read the cookie (`HttpOnly` + encrypted), so it had to keep a duplicate copy in `localStorage`.
- Guests' currency still resolved server-side via `CurrencyService::getEffectiveCode()`, but the value was opaque to JavaScript.

## 3. New Flow

- The cookie is **plaintext** and **HttpOnly=false** → the Frontend can read and write it directly with `document.cookie` / `js-cookie`.
- The **Frontend owns** the cookie (writes it on selection). The backend still writes it on `POST /currencies/select` for backward compatibility — same name/value/path/attributes, so no duplicate/competing cookie values.
- The backend **never trusts** the value: every read is normalized to uppercase ISO 4217 and validated against active `currencies` rows before use.

---

## 4. Cookie Ownership

| Concern | Owner | Notes |
|---------|-------|-------|
| Write | Frontend (primary) | `document.cookie = "guest_currency=KWD; Path=/; SameSite=Lax; Max-Age=31557600"` |
| Write (compat) | Backend | `POST /currencies/select` still emits the cookie via `UserCurrencyPreferenceService::setGuestCurrencyCode()` with identical attributes |
| Read | Backend | `UserCurrencyPreferenceService::getGuestCurrencyCode()` → validated |
| Delete | Frontend + Backend | Frontend may `remove`; backend may `forget` when a value is invalid |

The backend does **not** overwrite the cookie on every request — it only writes it on the explicit `select` endpoint (compatibility), matching the Frontend's own write.

---

## 5. Cookie Attributes

| Attribute | Value | Where configured |
|-----------|-------|------------------|
| Name | `guest_currency` | `../../config/currency.php` → `guest_cookie_name` |
| Value | uppercase 3-letter ISO 4217 (e.g. `KWD`) | plaintext, normalized server-side |
| HttpOnly | `false` | `guest_cookie_http_only` (default `false`) |
| Path | `/` | `guest_cookie_path` |
| SameSite | `lax` | `guest_cookie_same_site` |
| Secure | auto (HTTPS ⇒ Secure; HTTP ⇒ not) | `guest_cookie_secure` (default `null` = auto) |
| Lifetime | 525960 minutes (≈ 1 year) | `guest_cookie_lifetime` |
| Encryption | **none** | `EncryptCookies::$except` includes `guest_currency` |

---

## 6. Encryption Behavior

- `../../app/Http/Middleware/EncryptCookies.php` now has:

```php
protected $except = [
    'guest_currency',
];
```

- This disables `EncryptCookies` for `guest_currency` in **both directions** (no encryption on response, no decryption attempt on request), so the browser sees a plaintext, readable value.
- **Legacy migration:** users who still hold the old Laravel-encrypted `guest_currency` cookie are handled by `UserCurrencyPreferenceService::decryptLegacyGuestValue()`, which mirrors `EncryptCookies::decryptCookie` (decrypt without unserialize → `CookieValuePrefix::validate`). The old cookie resolves to the correct code on the server until the Frontend overwrites it with a fresh plaintext value.

---

## 7. Backend Resolution Precedence (`CurrencyService::getEffectiveCode`)

Unchanged and centralized:

```
1. currency_selection_enabled == false  → catalog/default currency (short-circuit)
2. Authenticated user's saved preference  (user_preferences.currency_code, validated active)
3. guest_currency cookie                   (normalized + validated active)
4. catalog/default currency                (settings.options.catalog_currency_code → USD)
```

- All consumers (`ProductController`, `CartResource`/`CartItemResource`, `OrderService`, `HomeService`, `ConvertsProductPrice`, etc.) read the effective currency through `CurrencyService::getEffectiveCode()` / `getEffectiveCurrency()` — **no consumer reads the cookie directly**.

---

## 8. Guest Behavior

| Cookie | Effective currency |
|--------|--------------------|
| missing | catalog/default |
| `KWD` | `KWD` |
| `kwd` / `" KWD "` | `KWD` (normalized) |
| invalid code (`XXX`) | catalog/default (silently cleared) |
| malformed (`12`, `EG`, very long) | catalog/default (no error thrown) |
| inactive code (exists but `is_active=false`) | catalog/default (silently cleared) |
| legacy encrypted value | decoded to the original code |

Ordinary API requests (products, cart, checkout…) never throw because of a stale/malformed guest cookie — they fall back to the default.

---

## 9. Authenticated Behavior

- `user_preferences.currency_code` is authoritative. A stale guest cookie can never override it.
- Selecting a currency while authenticated persists to `user_preferences` (and also updates the cookie for post-logout continuity).
- Invalid/stale stored preference is cleaned and resolution falls through to guest cookie → default.

## 10. Login Migration

`UserCurrencyPreferenceService::adoptGuestCurrencyOnLogin()` (unchanged semantics):

| Guest cookie | Saved preference | Result |
|--------------|------------------|--------|
| `KWD` | none | `user_preferences = KWD` |
| `KWD` | `USD` | stays `USD` (not overwritten) |
| `XXX` / missing | none | no adoption |

**Change:** adoption no longer clears the guest cookie (the Frontend owns it and may reuse it after logout).

## 11. Logout Behavior

- Logout only revokes the token; it does **not** touch `guest_currency`.
- After logout the guest cookie continues to determine the guest currency.

## 12. Cross-Origin Considerations

- The cookie is **host-only** (no `Domain` attribute), so it is sent automatically for same-site requests: `localhost:3000 → localhost:8000` (same host) and sibling subdomains if a shared `Domain` is configured separately.
- `../../config/cors.php` is now env-driven:

```env
CORS_ALLOWED_ORIGINS=*                        # or explicit list: http://localhost:3000,https://app.example.com
CORS_ALLOWED_ORIGINS_PATTERNS=                # e.g. https://.*\.vercel\.app
CORS_SUPPORTS_CREDENTIALS=false               # enable ONLY with an explicit allowed_origins list
```

- `supports_credentials` is `false` by default (unchanged behavior). For cross-origin Frontend → Backend where the cookie must be carried, enable it **together with** an explicit origin list — a wildcard `*` with credentials is rejected by browsers.

## 13. Migration Strategy (legacy encrypted cookies)

1. Old cookie arrives as an opaque Laravel-encrypted payload.
2. `getGuestCurrencyCode()` detects it is not plaintext and attempts legacy decryption.
3. On success, the effective currency still resolves to the user's prior choice (no data loss).
4. The Frontend reads the effective currency (from any currency response / `GET /general/currencies`) and writes a new plaintext `guest_currency` cookie, completing the transition.
5. No manual purge is required — old cookies expire naturally (1-year max-age) or are overwritten on the next selection.

## 14. Security Considerations

- The cookie is **not a security boundary**: it is a display preference. It is never trusted without `Currency::where('code', ...)->where('is_active', true)` validation.
- Plaintext + non-HttpOnly is safe here because the value is non-sensitive (a public currency code). XSS impact is limited to changing the display currency.
- No `X-Currency`, no `X-Guest-ID`, no guest database entity were introduced.

## 15. API Behavior

`POST /api/v1/general/currencies/select` is unchanged in contract:
- Body: `{"currency_code":"KWD"}` (validated active).
- Authenticated: persists `user_preferences`.
- Always emits the (now plaintext, readable) `guest_currency` cookie.
- Response: `200` + `CurrencyResource`.

---

## 16. Files Changed

| File | Change |
|------|--------|
| `../../app/Http/Middleware/EncryptCookies.php` | Added `guest_currency` to `$except` (plaintext, readable) |
| `../../app/Services/Currency/UserCurrencyPreferenceService.php` | Cookie `httpOnly=false` + `sameSite`/`secure` from config; value normalization + legacy-encrypted decryption; login adoption no longer clears the guest cookie |
| `../../config/currency.php` | Added `guest_cookie_http_only`, `guest_cookie_same_site`, `guest_cookie_secure` + updated docs |
| `../../config/cors.php` | `allowed_origins` / `allowed_origins_patterns` / `supports_credentials` env-driven (defaults unchanged) |
| `../../tests/Feature/Currency/GuestCurrencyCookieTest.php` | **New** — frontend-readability, normalization, fallback, legacy migration, login survival, plaintext select cookie |
| `../../tests/Feature/Currency/UserCurrencyPreferenceTest.php` | `assertCookie` → `assertPlainCookie` for the guest select test (cookie is now plaintext) |

## 17. Tests Added / Updated

- `GuestCurrencyCookieTest` (8 tests): non-HttpOnly plaintext cookie, uppercase normalization, invalid/malformed fallback, legacy encrypted resolution, login-adoption cookie survival, authed-no-pref fallback, plaintext select cookie.
- `UserCurrencyPreferenceTest`: updated guest select assertion to `assertPlainCookie`.

## 18. Deployment Considerations

- No database migration.
- No environment variables are required for localhost (`httpOnly=false` and auto `Secure` default work out of the box).
- For HTTPS production, optionally set `GUEST_CURRENCY_COOKIE_SECURE=true` (auto-detection already covers this).
- For cross-origin production, set `CORS_ALLOWED_ORIGINS` + `CORS_SUPPORTS_CREDENTIALS=true` with an explicit origin list.
- Legacy encrypted cookies are self-healing (see §13); no purge command needed.
