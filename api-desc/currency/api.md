# API Documentation - Currency Feature

All admin endpoints require `auth:sanctum` and `permission:*`, prefix `/api/v1`. Public endpoint prefix `/api/v1/general`.

Response envelope (via `Marvel\Traits\ApiResponse`):
```json
{ "status": 200, "message": "translated", "success": true, "data": { ... } }
```
Error envelope: `{ "status": 4xx, "message": "translated", "success": false }`.

---

## 1. List Currencies (Admin)

**GET** `/api/v1/currencies`

### Query Parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `limit` | int | No | Per page (default 15, max 100, min 1) |
| `page` | int | No | Page number |

### Response

```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "code": "USD",
        "name": "US Dollar",
        "symbol": "$",
        "country_name": "United States",
        "numeric_code": "840",
        "decimal_places": 2,
        "icon": "us",
        "is_active": true,
        "sort_order": 1,
        "is_base": true,
        "is_catalog": true,
        "created_at": "2026-08-10T00:00:00+00:00"
      }
    ],
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

---

## 2. Create Currency (Admin)

**POST** `/api/v1/currencies`

### Request Body

```json
{
  "code": "EGP",
  "name": { "en": "Egyptian Pound", "ar": "جنيه مصري" },
  "symbol": { "en": "E£", "ar": "ج.م" },
  "country_name": { "en": "Egypt", "ar": "مصر" },
  "numeric_code": "818",
  "decimal_places": 2,
  "icon": "eg",
  "is_active": true,
  "sort_order": 7
}
```

### Validation Rules (`StoreCurrencyRequest`)

| Field | Rules |
|-------|-------|
| `code` | required, string, **size:3**, regex `/^[A-Za-z]{3}$/`, unique `currencies.code` (case-insensitive via uppercase merge) |
| `name` | required, array |
| `name.*` | required, string, max:255, unique translation |
| `symbol` | required, array |
| `symbol.*` | required, string, max:255, unique translation |
| `country_name` | required, array |
| `country_name.*` | required, string, max:255, unique translation |
| `numeric_code` | nullable, string, max:3 |
| `decimal_places` | nullable, integer, min:0, max:4 |
| `icon` | nullable, string, max:255 |
| `is_active` | nullable, boolean |
| `sort_order` | nullable, integer |

`prepareForValidation()` uppercases `code` before validation.

### Success Response (200)
Same structure as list item, `message` = `CURRENCY_CREATED_SUCCESSFULLY` (`Currency created successfully`).

### Errors
| Status | Condition |
|--------|-----------|
| 422 | Validation failures (e.g. code length/format, duplicate code, invalid decimal_places) |
| 401 | No token |
| 403 | Missing `create-currency` permission |

---

## 3. Show Currency (Admin)

**GET** `/api/v1/currencies/{currency}`

- `{currency}` constrained numeric (route `whereNumber('currency')`).
- Looks up with `withTrashed()` (a soft-deleted currency can still be viewed).
- Missing / non-numeric → 404 (`CURRENCY_NOT_FOUND`).

### Response (200)
Same structure as list item, `is_base` / `is_catalog` reflect current settings.

---

## 4. Update Currency (Admin)

**PUT** `/api/v1/currencies/{currency}`

### Request Body (all optional — `UpdateCurrencyRequest`)

| Field | Rules |
|-------|-------|
| `name` | sometimes, array |
| `symbol` | nullable, array |
| `country_name` | nullable, array |
| `numeric_code` | nullable, string, max:3 |
| `decimal_places` | nullable, integer, min:0, max:4 |
| `icon` | nullable, string, max:255 |
| `is_active` | nullable, boolean |
| `sort_order` | nullable, integer |

### Response (200)
`message` = `CURRENCY_UPDATED_SUCCESSFULLY`. Missing → 404.

---

## 5. Delete Currency (Admin)

**DELETE** `/api/v1/currencies/{currency}`

### Guards (in `CurrencyService::deleteCurrency`)

1. If `code === baseCode` → `CurrencyInUseException(REASON_BASE_CURRENCY)` → **409** `CANNOT_DELETE_BASE_CURRENCY`.
2. If `rates()->exists()` → `CurrencyInUseException(REASON_REFERENCED_BY_RATES)` → **409** `CANNOT_DELETE_CURRENCY_IN_USE`.
3. Otherwise soft delete (`deleted_at` set) → **200** `CURRENCY_DELETED_SUCCESSFULLY`.

### Errors
| Status | Condition |
|--------|-----------|
| 409 | Base currency or currency referenced by rates |
| 404 | Currency not found |

---

## 6. Set Base Currency (Admin)

**POST** `/api/v1/currencies/{id}/set-base`

`{id}` constrained numeric.

### Flow (`CurrencyService::setBaseCurrency`)
1. `DB::transaction`, lock settings row.
2. Reject inactive currency → **422** `CURRENCY_INACTIVE`.
3. Require an exchange rate `effective_date <= today` → **422** `EXCHANGE_RATE_NOT_FOUND`.
4. Update settings options: `base_currency_code` = code, `currency` = code; clear cached settings.
5. Reset memoized base in singleton, flush price caches.

### Response (200)
`message` = `SET_BASE_CURRENCY_SUCCESSFULLY`, data = `CurrencyResource`.

---

## 7. List Exchange Rates (Admin)

**GET** `/api/v1/currency-rates`

### Query Parameters

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `limit` | int | No | Per page (default 15, max 100) |
| `currency_id` | int | No | Filter by currency |
| `effective_date` | date | No | Filter exact date (Y-m-d) |

### Response

```json
{
  "status": 200,
  "message": "Data fetched successfully",
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "currency_id": 2,
        "exchange_rate": "0.2210000000",
        "effective_date": "2026-08-10",
        "created_at": "2026-08-10T00:00:00+00:00"
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 3
  }
}
```

---

## 8. Create Exchange Rate (Admin)

**POST** `/api/v1/currency-rates`

### Request Body

```json
{ "currency_id": 2, "exchange_rate": "0.2500000000", "effective_date": "2026-08-10" }
```

### Validation (`StoreCurrencyRateRequest`)

| Field | Rules |
|-------|-------|
| `currency_id` | required, integer, exists `currencies.id` |
| `exchange_rate` | required, numeric, `gt:0` |
| `effective_date` | required, date |

### Business Rule: Upsert
`CurrencyRateService::store()` looks up `(currency_id, effective_date)`; if found it **updates** the rate, otherwise it **creates** a new row. No duplicate rows per currency/day (also enforced by unique DB constraint).

### Response (200)
`message` = `CURRENCY_RATE_CREATED_SUCCESSFULLY`, data includes nested `currency` (via `CurrencyRateResource` + loaded relation).

---

## 9. Show Exchange Rate (Admin)

**GET** `/api/v1/currency-rates/{currency_rate}` — `{currency_rate}` numeric. Missing → 404.

---

## 10. Update Exchange Rate (Admin)

**PUT** `/api/v1/currency-rates/{currency_rate}`

`exchange_rate` required, numeric, `gt:0`. Only `exchange_rate` is writable. Missing → 404.

---

## 11. Delete Exchange Rate (Admin)

**DELETE** `/api/v1/currency-rates/{currency_rate}` — hard delete. Missing → 404. `message` = `CURRENCY_RATE_DELETED_SUCCESSFULLY`.

---

## 12. List Currencies (Public / Storefront)

**GET** `/api/v1/general/currencies` — no auth required.

- Returns only `is_active = true` currencies, ordered `sort_order` then `code`.
- Result cached under tag `currencies` with key `md5(fullUrl)`, TTL 4h (`HasCache::remember`).
- Cache invalidated on any currency/rate write via `CurrencyService::invalidatePriceCaches()` (flushes `currencies` + `products` + product strategy tags).

### Response (200)
Flat array of active currencies (same item structure minus admin flags depend on settings).

---

## Business Rules Summary

1. **Base vs catalog currency** live in settings `options` (`base_currency_code`, `catalog_currency_code`, `currency`), default `config('shop.default_currency')` (USD).
2. **Conversion formula** (`CurrencyConversionService`):
   - `rate = bcdiv(targetRate, sourceRate, 6)`
   - `converted = round(bcdiv(bcmul(amount, targetRate, 6), sourceRate, 6), 2)`
   - Same-code conversion is identity (no DB query, rate `1`).
3. **Rate resolution** picks the latest `effective_date <= date` (`whereDate`), raising `CurrencyRateNotFoundException` if none.
4. **Order snapshot** (`OrderCreationService::resolveCurrencySnapshot`) stores `currency_code`, `base_currency_code`, `currency_rate` (ratio), `currency_rate_date`, `converted_total_price` on every create/update.
5. **Product prices** converted via `ConvertsProductPrice` from catalog → base code.
6. **Delete protection** for base currency and currencies referenced by rates.
7. **All user-facing messages** come from `resources/lang/{en,ar}/message.php`.
