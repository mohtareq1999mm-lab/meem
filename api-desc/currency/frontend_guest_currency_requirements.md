# Guest Currency — Frontend Requirements

> Implementation contract for the Frontend team. The Backend has switched `guest_currency` to a **plaintext, Frontend-readable, Frontend-owned** cookie. The Frontend is now the source of truth for the guest currency; the Backend reads + validates it on every request through the centralized currency service.

---

## 1. Cookie

```text
Name:   guest_currency
Value:  uppercase 3-letter ISO 4217 code, e.g. "KWD"
        (plaintext — NOT encrypted, NOT signed)
```

| Attribute | Value |
|-----------|-------|
| readable by JavaScript | **YES** (`HttpOnly=false`) |
| path | `/` |
| sameSite | `Lax` |
| secure | `true` on `https://`, `false` on `http://localhost` |
| domain | omit (host-only) — do not set `Domain` |
| lifetime | ~1 year (`Max-Age=31557600` or `expires: 365` days) |

### When to create it
- After the user selects a currency, and on bootstrap if you need to migrate a legacy value (see §7).

### When to update it
- Every time the user changes the currency.

### When to remove it
- Only when you explicitly want to reset to the backend default (rare). **Do not** delete it on every login; the backend ignores it once the user has a saved preference.

---

## 2. Initial Page Load

```
Read guest_currency
  ↓
if exists AND /^[A-Z]{3}$/ after trim+uppercase:
    use it (set app currency state)
  ↓
if missing or invalid:
    use the Backend-provided default/effective currency
    (GET /api/v1/general/currencies lists active currencies; the catalog/default is authoritative)
```

The Backend may still be resolving a **legacy encrypted** cookie for a short migration window. If your read of `document.cookie` returns a long opaque value (`eyJ…`) instead of 3 letters, treat it as "missing" locally, then re-write a plaintext value once you know the effective currency (see §7).

---

## 3. Currency Selection

```text
User selects KWD
  ↓
Frontend updates guest_currency = KWD   (document.cookie / js-cookie, Path=/, SameSite=Lax)
  ↓
Frontend updates local UI state          (store/currency)
  ↓
Call POST /api/v1/general/currencies/select { "currency_code": "KWD" }   (persists user preference when authenticated)
  ↓
Future API requests automatically carry the cookie (browser-managed)
```

---

## 4. API Requests — DO NOT add headers

```text
DO NOT add X-Currency.
DO NOT add X-Guest-ID.
DO NOT add currency manually to every endpoint.
```

The **browser cookie is the transport mechanism**. Once `guest_currency=KWD` is set, the browser automatically sends `Cookie: guest_currency=KWD` on every request to the API origin. The Backend resolves the currency centrally.

> Note: if the Frontend and Backend are served from **different origins** (not `localhost` and not sibling subdomains), the host-only cookie will not be sent cross-site. In that case configure the deployment so Frontend and Backend share an origin/subdomain, or enable credentialed CORS (see §8). This is an infrastructure concern, not a per-request concern.

---

## 5. Authenticated Users

```text
User logs in
  ↓
Backend User Preference (user_preferences) becomes authoritative
```

- After login, the Backend prefers the saved user preference over the guest cookie.
- The Frontend should **not** fight or overwrite a server-side saved preference; reflect whatever currency the Backend reports effective.

## 6. Login / Logout

| Event | Frontend behavior |
|-------|-------------------|
| Login, user had **no** saved preference | Backend adopts the guest currency into the user's preference. The guest cookie remains (owned by you); you may leave it — after logout it is reused. |
| Login, user **already had** a preference | Backend keeps the saved preference; do **not** overwrite the UI with the guest value. |
| Logout | Backend revokes the token and does **not** delete `guest_currency`. The guest cookie continues to determine the guest currency. |

---

## 7. Invalid / Missing Cookie

| State | Frontend behavior |
|-------|-------------------|
| `guest_currency` missing | Use the Backend default/catalog currency. |
| `guest_currency` invalid (not 3 letters / inactive) | Treat as missing; the Backend will safely fall back to the default (no error). You may re-write a valid value after a new selection. |
| Legacy encrypted value (`eyJ…`) | Treat as missing locally; after reading the effective currency from any currency response, write a fresh plaintext `guest_currency=KWD`. |

---

## 8. API Contract (as implemented)

### GET /api/v1/general/currencies

```http
GET /api/v1/general/currencies
Accept: application/json
```

Response `200`: list of active currencies (id, code, name, symbol, country_name, numeric_code, decimal_places, icon, is_active, sort_order, is_base, is_catalog).

### POST /api/v1/general/currencies/select

```http
POST /api/v1/general/currencies/select
Accept: application/json
Content-Type: application/json

{"currency_code": "KWD"}
```

Response `200`:

```json
{
  "status": 200,
  "message": "Currency updated successfully",
  "success": true,
  "data": { "id": 2, "code": "KWD", "name": "Kuwaiti Dinar", "symbol": "KD", "country_name": "Kuwait", "numeric_code": "414", "decimal_places": 3, "icon": "kw", "is_active": true, "sort_order": 2, "is_base": false, "is_catalog": false, "created_at": "..." }
}
```

- Also emits `Set-Cookie: guest_currency=KWD; Path=/; SameSite=Lax; Max-Age=31557600` (plaintext, readable).
- Errors: `422` for unknown/inactive `currency_code`.

---

## 9. Frontend Acceptance Criteria

```text
[ ] Frontend can read guest_currency (document.cookie / js-cookie returns "KWD")
[ ] Frontend can update guest_currency (set/overwrite on selection)
[ ] Currency persists after page refresh
[ ] Currency persists across normal API requests (no manual header)
[ ] No X-Currency header is required
[ ] No X-Guest-ID is required
[ ] Guest currency works before login
[ ] Authenticated currency follows Backend User Preference (guest cookie does not override)
[ ] Login transition works (guest adopted when user has no preference; kept when user has one)
[ ] Logout behavior works (guest currency still usable after logout)
[ ] Invalid/missing cookie falls back to default without errors
```
