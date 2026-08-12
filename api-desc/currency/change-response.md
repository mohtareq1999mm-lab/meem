# Response Changes - Currency Catalog `<->` Base Refactor

This file documents every **API response difference** introduced by the catalog `/` base currency refactor. Compare against the pre-refactor shape on `main`.

## Key Status Legend

Every key below is tagged so the frontend can audit its own usage:

| Status | Meaning | Frontend action |
|--------|---------|-----------------|
| **ADDED** | New key returned by the API | Start reading it (nullable-safe) |
| **CHANGED** | Existing key returned, value/semantics changed | Re-verify how the value is used (currency, unit, precision) |
| **REMOVED** | Key no longer returned | Stop reading it; migrate to the replacement |
| **UNCHANGED** | Key returned with same meaning (listed for completeness) | No action required |

> **Rule of thumb:** if the frontend displays or persists a **money amount**, assume it is now expressed in the **base currency** unless the field name says otherwise. Always pair amounts with the response `currency`/`base_currency` code.

---

## Frontend Key Change Summary (consolidated)

| Resource | Key | Status | Before | After |
|----------|-----|--------|--------|-------|
| Product | `price` | **CHANGED** | Raw catalog amount | Base-currency converted value |
| Product | `current_price` | **UNCHANGED** | Converted value | Still converted (base) |
| Product | `discount_amount` | **CHANGED** | Raw value always | Converted to base **only** for fixed-rate discounts; raw for percentage |
| Product | `converted_current_price` | **REMOVED** | Duplicate of `current_price` | Gone — use `current_price` |
| Cart | `total_price` | **CHANGED** | Catalog subtotal | Base-currency converted |
| Cart | `subtotal` | **CHANGED** | Catalog subtotal | Base-currency converted |
| Cart | `coupon_discount` | **CHANGED** | Catalog value | Base-currency converted |
| Cart | `total_after_coupon` | **CHANGED** | Catalog value | Base-currency converted |
| Cart | `currency` | **ADDED** | — | Base currency code |
| Cart item | `price` | **CHANGED** | Raw catalog price | Base-currency converted |
| Cart item | `total_price` | **CHANGED** | Raw catalog total | Base-currency converted |
| Cart item | `discount_amount` | **CHANGED** | Raw value | Base-currency converted |
| Order | `total_price` | **CHANGED** | Catalog total | Base-currency amount (authoritative) |
| Order | `currency_code` | **CHANGED** | Catalog code on snapshot | Base code (== `base_currency_code`) |
| Order | `base_currency_code` | **UNCHANGED** | Base code | Base code |
| Order | `catalog_currency` | **ADDED** | — | `catalog_currency_code ?? base_currency_code ?? fallback` |
| Order | `currency_rate` | **CHANGED** | Catalog snapshot semantics | Catalog → base ratio |
| Order | `converted_total_price` | **UNCHANGED** | Converted value | Kept for backward compat |
| Settings | `currency_selection_enabled` | **ADDED** | — | Boolean (default `false`); when `false` effective currency = catalog code |
| Currency (select) | `data` | **ADDED** | — | `CurrencyResource` of the selected currency (`POST /api/v1/general/currencies/select`) |
| Admin lists | `page`, `path`, `last_page_url`, `first_page_url` | **ADDED** | Omitted from docs (present in response) | Part of the custom pagination envelope |

---

## Old vs New JSON Examples

### 1. Product (catalog USD → base KWD, rate 0.221)

**Before**
```json
{
  "price": 100.0,
  "current_price": 22.1,
  "discount_type": "fixed",
  "discount_amount": 20.0,
  "converted_current_price": 22.1,
  "currency": "KWD"
}
```

**After**
```json
{
  "price": 22.1,
  "current_price": 22.1,
  "discount_type": "fixed",
  "discount_amount": 4.42,
  "currency": "KWD"
}
```

> `converted_current_price` removed; `price`/`discount_amount` now base-currency consistent (`price - discount_amount == current_price`).

---

### 2. Cart (catalog USD → base KWD, rate 0.221)

**Before**
```json
{
  "total_price": 120.0,
  "subtotal": 120.0,
  "coupon_discount": 10.0,
  "total_after_coupon": 110.0,
  "items": [
    {
      "id": 1,
      "price": 60.0,
      "total_price": 120.0,
      "discount_amount": 0.0
    }
  ]
}
```

**After**
```json
{
  "total_price": 26.52,
  "subtotal": 26.52,
  "coupon_discount": 2.21,
  "total_after_coupon": 24.31,
  "currency": "KWD",
  "items": [
    {
      "id": 1,
      "price": 13.26,
      "total_price": 26.52,
      "discount_amount": 0.0
    }
  ]
}
```

> `currency` field added (base code). All amounts converted to base.

---

### 3. Order (catalog USD → base KWD)

**Before**
```json
{
  "total_price": 120.0,
  "currency_code": "USD",
  "base_currency_code": "KWD",
  "currency_rate": 1.0,
  "converted_total_price": 26.52
}
```

**After**
```json
{
  "total_price": 26.52,
  "currency_code": "KWD",
  "base_currency_code": "KWD",
  "catalog_currency": "USD",
  "currency_rate": 4.5249,
  "converted_total_price": 26.52
}
```

> `total_price` now the authoritative base amount; `currency_code` equals base; `catalog_currency` added; `currency_rate` is the catalog→base ratio.

---

### 4. Settings (admin + public)

**Before**
```json
{
  "minimumOrderAmount": 100,
  "options": {
    "minimumOrderAmount": 100,
    "fast_shipping": {
      "enabled": true,
      "duration_minutes": 120,
      "fee": 0,
      "start_hour": "08:00",
      "end_hour": "22:00"
    }
  }
}
```

**After**
```json
{
  "minimumOrderAmount": 100,
  "currency_selection_enabled": false,
  "options": {
    "minimumOrderAmount": 100,
    "currency_selection_enabled": false,
    "fast_shipping": {
      "enabled": true,
      "duration_minutes": 120,
      "fee": 0,
      "start_hour": "08:00",
      "end_hour": "22:00"
    }
  }
}
```

> `currency_selection_enabled` added (top-level + stored in `options`). `PUT /api/v1/settings` accepts it as a boolean.

---

### 5. Currency Select (new endpoint)

**Before** — endpoint did not exist.

**After** — `POST /api/v1/general/currencies/select` with body `{ "currency_code": "KWD" }`
```json
{
  "status": 200,
  "message": "Currency updated successfully",
  "success": true,
  "data": {
    "id": 2,
    "code": "KWD",
    "name": "Kuwaiti Dinar",
    "symbol": "KD",
    "country_name": "Kuwait",
    "numeric_code": "414",
    "decimal_places": 3,
    "icon": "kw",
    "is_active": true,
    "sort_order": 2,
    "is_base": true,
    "is_catalog": false,
    "created_at": "2026-08-10T00:00:00+00:00"
  }
}
```

---

### 6. Admin list pagination envelope (currencies / currency-rates)

**Before (as documented)**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [],
    "current_page": 1,
    "from": 1,
    "to": 15,
    "last_page": 1,
    "per_page": 15,
    "total": 3,
    "next_page_url": "",
    "prev_page_url": ""
  }
}
```

**After (actual response — custom flattened envelope)**
```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [],
    "page": 1,
    "current_page": 1,
    "from": 1,
    "to": 15,
    "last_page": 1,
    "path": "http://localhost/api/v1/currencies",
    "per_page": 15,
    "total": 3,
    "next_page_url": "",
    "prev_page_url": "",
    "last_page_url": "http://localhost/api/v1/currencies?page=1",
    "first_page_url": "http://localhost/api/v1/currencies?page=1"
  }
}
```

> Added keys: `page`, `path`, `last_page_url`, `first_page_url`. Same shape for `GET /api/v1/currency-rates`.

---

## 1. Products

**Files:** `app/Http/Resources/Product/ProductResource.php`, `app/Http/Resources/Product/ProductMiniResource.php`

| Key | Status | Before | After |
|-----|--------|--------|-------|
| `price` | **CHANGED** | Raw catalog amount (`roundMoney`) | **Converted to base currency** (`convertCatalogPrice`) |
| `current_price` | **UNCHANGED** | Converted value | Unchanged (still converted) |
| `discount_amount` | **CHANGED** | Raw value always | **Converted to base currency ONLY for fixed-rate discounts** (`discount_type = fixed`); stays raw for percentage discounts |
| `converted_current_price` | **REMOVED** | Present (duplicate of `current_price`) | **Removed** — use `current_price` |

> **Impact:** Frontends that read `price`/`current_price` now get base-currency values; `discount_amount` is now currency-consistent with `price` (so `price - discount_amount == current_price` holds for fixed-rate discounts). Consumers relying on `converted_current_price` must switch to `current_price`.

### Example (catalog USD -> base KWD, rate 0.221)

```json
{
  "price": 22.1,
  "current_price": 22.1,
  "discount_type": "fixed",
  "discount_amount": 4.42,
  "currency": "KWD"
}
```

---

## 2. Cart

**Files:** `packages/marvel/src/Http/Resources/CartResource.php`, `packages/marvel/src/Http/Resources/CartItemResource.php`

| Key | Status | Before | After |
|-----|--------|--------|-------|
| `total_price` | **CHANGED** | Catalog subtotal | **Converted to base currency** |
| `subtotal` | **CHANGED** | Catalog subtotal | **Converted to base currency** |
| `coupon_discount` | **CHANGED** | Catalog value | **Converted to base currency** |
| `total_after_coupon` | **CHANGED** | Catalog value | **Converted to base currency** |
| `currency` | **ADDED** | Not present | **New field** — base currency code |
| item `price` | **CHANGED** | Raw catalog price | **Converted to base currency** |
| item `total_price` | **CHANGED** | Raw catalog total | **Converted to base currency** |
| item `discount_amount` | **CHANGED** | Raw value | **Converted to base currency** |

---

## 3. Orders

**File:** `app/Http/Resources/Order/OrderResource.php`, `app/Services/Checkout/OrderCreationService.php`

| Key | Status | Before | After |
|-----|--------|--------|-------|
| `total_price` | **CHANGED** | Catalog total | **Stored/converted to base amount** (authoritative) |
| `currency_code` | **CHANGED** | Catalog code on snapshot | **Base code** (snapshot `currency_code` now equals `base_currency_code`) |
| `base_currency_code` | **UNCHANGED** | Base code | Unchanged |
| `catalog_currency` | **ADDED** | Not present | **New field** — `catalog_currency_code ?? base_currency_code ?? fallback` |
| `currency_rate` | **CHANGED** | Catalog snapshot semantics | Catalog → base ratio |
| `converted_total_price` | **UNCHANGED** | Converted value | Unchanged (kept for backward compat) |

> **Impact:** `OrderResource` documents the order in base currency; `catalog_currency` lets clients know which catalog the prices were originally quoted in.

---

## 4. Payments / Invoices / Reconciliation

These are behavioral changes (not response shapes) that ensure all payment artifacts quote the **order's base currency** instead of a hardcoded default:

| Component | Before | After |
|-----------|--------|-------|
| `MyFatoorahGateway::createInvoice` | `DisplayCurrencyIso = 'EGP'` | `order.base_currency_code ?? order.currency_code ?? 'EGP'` |
| `MyFatoorahGateway::refund` | `currency = 'EGP'` | `order.base_currency_code ?? order.currency_code ?? 'EGP'` |
| `PaymentCheckoutHandler` transactions | `config('payment.default_currency', 'KWD'/'EGP')` | `order.currency_code ?? order.base_currency_code ?? config(...)` |
| `CashierQrService` QR payload | `config('payment.default_currency', 'EGP')` | `transaction.currency ?? order.currency_code ?? order.base_currency_code` |
| `InvoiceService` / `InvoiceSnapshotService` | `'EGP'` fallback | `paidTransaction.currency ?? order.currency_code ?? order.base_currency_code ?? 'EGP'` |
| `PaymentReconciliationJob::compareCurrency` | `config('payment.default_currency')` | `order.base_currency_code ?? order.currency_code ?? config('payment.default_currency')` |
| `OrderController` callback currency-mismatch check | `config('payment.default_currency', 'EGP')` | `order.base_currency_code ?? order.currency_code ?? config('payment.default_currency', 'EGP')` |

---

## 5. New filter parameters (response shape unchanged)

These add **query parameters** (filtering only; response structure unchanged):

### `GET /api/v1/currencies`
Added: `search` (code/numeric_code/name/symbol/country_name incl. translations), `code`, `is_active`, `sort_order`.

### `GET /api/v1/currency-rates`
Added: `date_from`, `date_to`, `code` (in addition to existing `currency_id`, `effective_date`).

---

## 6. New endpoints added (not modified)

| Method | URI | Notes |
|--------|-----|-------|
| POST | `/api/v1/currencies/{id}/set-catalog` | Requires `set-catalog-currency` permission; returns `SET_CATALOG_CURRENCY_SUCCESSFULLY` |

---

## 7. Settings — New `currency_selection_enabled` Field

**Files:** `packages/marvel/src/Http/Resources/SettingResource.php`, `packages/marvel/src/Http/Controllers/SettingsController.php`, `packages/marvel/src/Http/Requests/SettingsRequest.php`, `database/seeders/SettingSeeder.php`, `app/Services/Currency/CurrencyService.php`

| Key | Status | Before | After |
|-----|--------|--------|-------|
| `data.currency_selection_enabled` (top-level, both admin + public settings) | **ADDED** | Not present | Boolean, read from `options.currency_selection_enabled` (default `false`) |
| `options.currency_selection_enabled` | **ADDED** | Not managed by the API | Written by `PUT /api/v1/settings` when the field is sent |

### Behavior
- `PUT /api/v1/settings` now accepts `currency_selection_enabled` (`sometimes|boolean` in `SettingsRequest`).
- When sent, the value is **merged** into `options` (existing `options` like `fast_shipping` are preserved), the effective-currency memo is reset via `CurrencyService::forgetEffectiveCode()`, and the `settings` cache tag is flushed.
- Omitting the field leaves the stored value untouched (old clients remain compatible).

### Impact on effective currency resolution (`CurrencyService::getEffectiveCode()`)
- **Old (before):** storefront effective currency resolved to `user preference > guest cookie > catalog code`.
- **New (after):**
  - `currency_selection_enabled = false` (default) → effective currency **always** resolves to the catalog code, ignoring any stored preference/cookie.
  - `currency_selection_enabled = true` → `user preference > guest cookie > catalog code`.

### Example (admin & public settings response)
```json
{
  "minimumOrderAmount": 100,
  "currency_selection_enabled": false,
  "options": {
    "minimumOrderAmount": 100,
    "fast_shipping": { "enabled": true }
  }
}
```

---

## 8. New Public Endpoint — `POST /api/v1/general/currencies/select`

**Files:** `app/Http/Controllers/Api/Currency/CurrencyController.php` (`select`), `app/Http/Requests/SelectCurrencyRequest.php`, `app/Services/Currency/UserCurrencyPreferenceService.php`

New endpoint (no auth required, `throttle:public-api`).

### Request
```json
{ "currency_code": "KWD" }
```
`currency_code`: required, string, `max:3`, must exist in `currencies` **and** be `is_active = true` (validated via `Rule::exists(...)->where('is_active', true)`).

### Behavior
1. Code uppercased.
2. If an authenticated user is present (`auth('sanctum')->user() ?? auth()->user()`) → stores the user preference.
3. Always stores a `guest_currency` cookie for guests.
4. Resets `CurrencyService::forgetEffectiveCode()`.
5. Returns `200` `CURRENCY_SELECTED_SUCCESSFULLY` with a `CurrencyResource` payload.

### Note
The stored selection only affects pricing/display when `currency_selection_enabled = true` (section 7). While disabled, the preference/cookie is stored but **ignored** by `getEffectiveCode()`.

---

## 9. Doc Corrections Applied During This Investigation

The following are corrections to previously inaccurate API documentation (not code changes):

| Doc claim (before) | Actual (verified in source) |
|--------------------|------------------------------|
| Admin `GET /api/v1/currencies` returns standard Laravel pagination `{data, meta, links}` | Returns a **custom flattened envelope** inside `data`: `data, page, current_page, from, to, last_page, path, per_page, total, next_page_url, prev_page_url, last_page_url, first_page_url` |
| Admin `GET /api/v1/currency-rates` same standard shape | Same custom flattened envelope as above |
| Rate create/update response includes nested `currency` object | `CurrencyRateResource` does **NOT** serialize the relation — only `id, currency_id, exchange_rate, effective_date, created_at` (the relation is loaded via `with('currency')` but not output) |
| `GET /api/v1/settings` is public | It sits inside the `auth:sanctum` group (`Routes.php:114`) — requires token + `view-settings` permission; the public endpoint is `GET /api/v1/general/settings` |

---

## Known caveats for consumers

- **Nulls:** when a value is null/empty, converted fields return `null` (same behavior as before for nulls).
- **Precision:** converted amounts are rounded to 2 decimals (`round(..., 2)`).
- **Same-code:** when catalog == base, conversion is identity (no DB query, rate `1`) — values unchanged.

---

## Frontend Migration Checklist

| Area | Task | Status legend |
|------|------|---------------|
| Product card | Stop reading `converted_current_price`; use `current_price` | REMOVED |
| Product card | Display `price`/`current_price`/`discount_amount` with the base-currency symbol (`currency`) | CHANGED |
| Cart page | Read new `currency` field; format all cart totals in base currency | ADDED |
| Cart page | Re-derive `price - discount_amount == current_price` in base currency (fixed discounts) | CHANGED |
| Order/Invoice view | Show base-currency `total_price`; use `currency_code`/`base_currency_code` for the symbol | CHANGED |
| Order/Invoice view | Show `catalog_currency` if the "original" catalog currency matters to the user | ADDED |
| Currency selector (storefront) | Hide/disable the selector when `currency_selection_enabled` is `false` | ADDED |
| Currency selector (storefront) | Call `POST /api/v1/general/currencies/select` with `{ currency_code }` to persist selection | ADDED |
| Admin settings page | Read/write `currency_selection_enabled` (boolean) in the settings form | ADDED |
| Admin lists (currencies / rates) | Read pagination from the flattened envelope keys (`page`, `path`, `last_page_url`, `first_page_url`, ...) | ADDED |