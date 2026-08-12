# Backend - Currency Feature

## Controllers

### Admin CurrencyController — `packages/marvel/src/Http/Controllers/CurrencyController.php`

Namespace `Marvel\Http\Controllers`, extends `CoreController`, uses `ApiResponse` trait. Constructor-injected `CurrencyService`.

**Middleware (permissions):**

| Method | Permission |
|--------|-----------|
| `index`, `show` | `Permission::VIEW_CURRENCIES` (`view-currencies`) |
| `store` | `Permission::CREATE_CURRENCY` (`create-currency`) |
| `update` | `Permission::UPDATE_CURRENCY` (`update-currency`) |
| `destroy` | `Permission::DELETE_CURRENCY` (`delete-currency`) |
| `setBase` | `Permission::SET_BASE_CURRENCY` (`set-base-currency`) |
| `setCatalog` | `Permission::SET_CATALOG_CURRENCY` (`set-catalog-currency`) |

**index(Request)**
1. `limit = max(1, min((int)$request->query('limit', 15), 100))`.
2. Filters:
   - `search` — `LIKE` across `code`, `numeric_code`, `name->en`, `name->ar`, `symbol->en`, `symbol->ar`, `country_name->en`, `country_name->ar`.
   - `code` — exact code filter.
   - `is_active` — boolean filter (`filter_var(..., FILTER_VALIDATE_BOOLEAN)`; absent → no filter).
   - `sort_order` — exact integer filter.
3. `Currency::query()` with the above `when(...)` clauses `.orderBy('sort_order')->orderBy('code')->paginate($limit)`.
4. Transforms via `CurrencyResource::collection(...)`, extracts pagination metadata into the `data` object.
5. Returns `apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, [...])`.

**show(int $id)**
- `Currency::withTrashed()->find($id)`; missing → `apiResponse(CURRENCY_NOT_FOUND, 404, false)`.
- Returns `CurrencyResource::make($currency)`.

**store(StoreCurrencyRequest)**
- `$this->currencyService->storeCurrency($request->validated())` → 200 `CURRENCY_CREATED_SUCCESSFULLY`.

**update(UpdateCurrencyRequest, int $id)**
- `Currency::withTrashed()->find($id)`; missing → 404.
- `currencyService->updateCurrency(...)` → 200 `CURRENCY_UPDATED_SUCCESSFULLY`.

**destroy(int $id)**
- `Currency::withTrashed()->find($id)`; missing → 404.
- `currencyService->deleteCurrency($currency)`:
  - `CurrencyInUseException` with `reason === REASON_BASE_CURRENCY` → **409** `CANNOT_DELETE_BASE_CURRENCY`.
  - otherwise → **409** `CANNOT_DELETE_CURRENCY_IN_USE`.
- Success → 200 `CURRENCY_DELETED_SUCCESSFULLY`.

**setBase(int $id)**
- `Currency::withTrashed()->find($id)`; missing → 404.
- `currencyService->setBaseCurrency($currency)`:
  - `CurrencyInactiveException` → **422** `CURRENCY_INACTIVE`.
  - `CurrencyRateNotFoundException` → **422** `EXCHANGE_RATE_NOT_FOUND`.
- Success → 200 `SET_BASE_CURRENCY_SUCCESSFULLY` + `CurrencyResource`.

**setCatalog(int $id)**
- `Currency::withTrashed()->find($id)`; missing → 404.
- `currencyService->setCatalogCurrency($currency)`:
  - `CurrencyInactiveException` → **422** `CURRENCY_INACTIVE`.
  - `CurrencyRateNotFoundException` → **422** `EXCHANGE_RATE_NOT_FOUND`.
- Success → 200 `SET_CATALOG_CURRENCY_SUCCESSFULLY` + `CurrencyResource`.

### Admin RateController — `packages/marvel/src/Http/Controllers/CurrencyRateController.php`

Namespace `Marvel\Http\Controllers`, extends `CoreController`, uses `ApiResponse`. Injected `CurrencyRateService`.

**Middleware (permissions):**

| Method | Permission |
|--------|-----------|
| `index`, `show` | `Permission::VIEW_EXCHANGE_RATES` |
| `store` | `Permission::CREATE_EXCHANGE_RATE` |
| `update`, `destroy` | `Permission::UPDATE_EXCHANGE_RATE` |

- `index` → `currencyRateService->list($currencyId, $effectiveDate, $dateFrom, $dateTo, $code, $limit)`, `CurrencyRateResource::collection`, pagination metadata. Query params: `limit`, `currency_id`, `effective_date`, `date_from`, `date_to`, `code`.
- `show(int $id)` → `CurrencyRate::with('currency')->find($id)`; missing → 404.
- `store` → `currencyRateService->store($validated)` (upsert) → `CURRENCY_RATE_CREATED_SUCCESSFULLY`.
- `update(int $id)` → `CurrencyRate::find($id)`; missing → 404; `currencyRateService->update(...)` → `CURRENCY_RATE_UPDATED_SUCCESSFULLY`.
- `destroy(int $id)` → `CurrencyRate::find($id)`; missing → 404; `currencyRateService->delete(...)` → `CURRENCY_RATE_DELETED_SUCCESSFULLY`.

### Public Controller — `app/Http/Controllers/Api/Currency/CurrencyController.php`

Namespace `App\Http\Controllers\Api\Currency`, extends `App\Http\Controllers\Controller`, uses `ApiResponse` + `HasCache`.

- `index()` → `Currency::active()->orderBy('sort_order')->orderBy('code')->get()`.
- Caches `CurrencyResource::collection(...)` under `FrontendResource::CURRENCIES->value` tag, key `md5(request()->fullUrl())`, TTL 4h.
- Returns `apiResponse(FETCH_DATA_SUCCESSFULLY, 200, true, $cached)`.
- `select(SelectCurrencyRequest)` → `POST /api/v1/general/currencies/select`:
  1. `strtoupper` the validated `currency_code`.
  2. `$user = auth('sanctum')->user() ?? auth()->user()`; if present, `UserCurrencyPreferenceService::setUserPreference($user, $code)`.
  3. Always `UserCurrencyPreferenceService::setGuestCurrencyCode($code, $request)` (sets `guest_currency` cookie).
  4. `app(CurrencyService::class)->forgetEffectiveCode()`.
  5. Returns `apiResponse(CURRENCY_SELECTED_SUCCESSFULLY, 200, true, new CurrencyResource($currency))`.

> **Gating:** the stored preference/cookie only affects `getEffectiveCode()` when `isCurrencySelectionEnabled()` is `true`. Otherwise the effective currency always resolves to the catalog code.

## Services

### CurrencyService (singleton) — `app/Services/Currency/CurrencyService.php`

Registered as singleton in `AppServiceProvider::register()` (line 26). Uses `HasCache` trait for tag-based settings/cache flushing. Memoizes base/catalog codes + currencies and an in-memory rate cache.

- `getBaseCode()` / `getCatalogCode()` — from settings options, uppercase, default `config('shop.default_currency', 'USD')`.
- `getBaseCurrency()` / `getCatalogCurrency()` — query `Currency` by code.
- `isCurrencySelectionEnabled()` — memoized bool from `settings.options.currency_selection_enabled` (default `false`).
- `getEffectiveCode(?User)` / `getEffectiveCurrency()` — when selection is **disabled** returns the catalog code (ignores stored preference/cookie); when **enabled** resolves `user preference > guest cookie > catalog code` (via `UserCurrencyPreferenceService`), validating/clearing stale codes and falling back to catalog when preference tables are absent.
- `forgetEffectiveCode()` — resets `effectiveCode`, `effectiveCurrency` and the `currencySelectionEnabled` memo (called on settings update, `select`, set-base/set-catalog, rate writes).
- `convert(amount, from, to, ?date)` — delegates to `CurrencyConversionService`.
- `convertPrice(amount, from, to, ?date)` — float conversion rounded to 2 decimals; identity short-circuit.
- `storeCurrency(array)` — `Currency::create` + `invalidatePriceCaches()`.
- `updateCurrency(Currency, array)` — update + cache invalidation + `fresh()`.
- `deleteCurrency(Currency)` — base-currency check first, then `rates()->exists()`, throws `CurrencyInUseException`; soft delete + cache invalidation.
- `setBaseCurrency(Currency)` — transaction + row lock on settings; rejects inactive; requires a rate `<= today`; writes `base_currency_code` and `currency` options; flushes cached settings via `flushTag(FrontendResource::SETTINGS->value)`; resets memoized base; flushes price caches including settings tag.
- `setCatalogCurrency(Currency)` — transaction + row lock on settings; rejects inactive; requires a rate `<= today`; writes **only** `catalog_currency_code` option (base + `currency` untouched); flushes cached settings; resets memoized catalog; flushes price caches.
- `invalidatePriceCaches(bool $flushSettings = false)` — flushes tag set: `currencies`, `products`, all product strategy tags (`products_{type}`), and optionally `settings`.
- `resolveRate(code, date)` — private; per-key array cache; latest `effective_date <= date` via `whereDate`; throws `CurrencyRateNotFoundException`.

### CurrencyConversionService — `app/Services/Currency/CurrencyConversionService.php`

- `const SCALE = 6`.
- `convert(amount, from, to, ?date)`:
  - Uppercases codes; `date ?? now()->toDateString()`.
  - Identity (`from === to`) → `CurrencyConversionResult(amount, round(amount,2), rate:'1', ...)` — no DB query.
  - `sourceRate = resolveRate(from)`, `targetRate = resolveRate(to)`.
  - `converted = bcdiv(bcmul(amount, targetRate, 6), sourceRate, 6)`; `convertedAmount = round((float)$converted, 2)`.
  - `rate = bcdiv(targetRate, sourceRate, 6)`.
- `resolveRate(code, date)` — same latest-`<=`-date lookup; throws `CurrencyRateNotFoundException`.

### CurrencyRateService — `app/Services/Currency/CurrencyRateService.php`

- `store(array)` — upsert: `where(currency_id, date)` → update, else create; then `invalidatePriceCaches()`.
- `update(CurrencyRate, array)` — updates `exchange_rate`; invalidates caches.
- `delete(CurrencyRate)` — hard delete; invalidates caches.
- `list(?currencyId, ?effectiveDate, ?dateFrom, ?dateTo, ?code, limit)` — `with('currency')`, filters (`currency_id`, `whereDate effective_date`, `whereDate >= date_from`, `whereDate <= date_to`, `whereHas currency.code = code`), `orderByDesc('effective_date')`, `orderByDesc('id')`, paginate.

### UserCurrencyPreferenceService — `app/Services/Currency/UserCurrencyPreferenceService.php`

- `setUserPreference(User, string $code)` — store the user's currency preference (`user_preferences` table).
- `getUserPreference(?User)` — read stored preference (per-user).
- `clearUserPreference(User)` / `clearGuestCurrencyCode()` — clear stale/invalid selections.
- `setGuestCurrencyCode(string $code, Request)` — persist a `guest_currency` cookie.
- `getGuestCurrencyCode()` — read the guest cookie.
- `isValidActiveCurrency(string $code)` — verify the code exists and is active.

## DTO — `app/DTOs/CurrencyConversionResult.php`

Immutable readonly object: `amount`, `convertedAmount`, `rate`, `effectiveDate`, `fromCode`, `toCode`, `sourceRate`, `targetRate`.

## Exceptions

| Class | Trigger | Reason/Message |
|-------|---------|----------------|
| `CurrencyInUseException` | Delete base or referenced currency | `REASON_BASE_CURRENCY` / `REASON_REFERENCED_BY_RATES` |
| `CurrencyInactiveException` | Set inactive base | `Inactive currency [X] cannot be set as the base currency.` |
| `CurrencyRateNotFoundException` | No rate `<=` date | `No exchange rate found for currency [X] on or before [Y].` |

`CurrencyInactiveException` and `CurrencyRateNotFoundException` are also thrown by `setCatalogCurrency` (inactive catalog currency / missing rate), surfacing as `CURRENCY_INACTIVE` / `EXCHANGE_RATE_NOT_FOUND`.

## Models

### Currency — `app/Models/Currency.php`
`HasTranslations` (`name`, `symbol`, `country_name`), `SoftDeletes`. Fillable: code, name, symbol, country_name, numeric_code, decimal_places, icon, is_active, sort_order. `setCodeAttribute` uppercases. Scopes: `active()`. Helpers: `isBaseCurrency()`, `isCatalogCurrency()`. Relation: `rates()`.

### CurrencyRate — `app/Models/CurrencyRate.php`
Fillable: currency_id, exchange_rate, effective_date. Casts: `exchange_rate => string`, `effective_date => date`. Scope `effectiveOn($date)`: `whereDate('effective_date', '<=', $date)->orderByDesc('effective_date')`. Relation: `currency()`.

## API Resources

### CurrencyResource — `app/Http/Resources/Currency/CurrencyResource.php`
Fields: id, code, name, symbol, country_name (translated per locale), numeric_code, decimal_places (int), icon, is_active (bool), sort_order (int), `is_base`, `is_catalog`, created_at (ISO8601).

### CurrencyRateResource — `app/Http/Resources/Currency/CurrencyRateResource.php`
Fields: id, currency_id, exchange_rate, effective_date (`toDateString()`), created_at (ISO8601). The `currency` relation is eager-loaded (for filtering/ordering) but is **not** serialized in the response.

## Form Requests

| Request | Rules |
|---------|-------|
| `StoreCurrencyRequest` | code size:3 + regex + unique (uppercased), translatable arrays with unique translation, decimal_places 0–4 |
| `UpdateCurrencyRequest` | same fields, all `sometimes`/`nullable` |
| `StoreCurrencyRateRequest` | currency_id exists, exchange_rate numeric gt:0, effective_date date |
| `UpdateCurrencyRateRequest` | exchange_rate required numeric gt:0 |
| `SelectCurrencyRequest` | currency_code required, string, max:3, exists in `currencies` with `is_active = true` |

## Enums & Constants

- `App\Enums\FrontendResource::CURRENCIES` = `'currencies'` — public cache tag.
- `Marvel\Enums\Permission` lines 92–100: `VIEW_CURRENCIES`, `CREATE_CURRENCY`, `UPDATE_CURRENCY`, `DELETE_CURRENCY`, `VIEW_EXCHANGE_RATES`, `CREATE_EXCHANGE_RATE`, `UPDATE_EXCHANGE_RATE`, `SET_BASE_CURRENCY`, `SET_CATALOG_CURRENCY`.
- `packages/marvel/config/constants.php` lines 525–566 — message keys for all currency operations/errors (incl. `SET_CATALOG_CURRENCY_SUCCESSFULLY`).

## Routes

- Admin: `packages/marvel/src/Rest/Routes.php` lines 186–192 (apiResource with `whereNumber('currency')` / `whereNumber('currency_rate')`, `set-base` + `set-catalog` with `whereNumber('id')`), all inside the `auth:sanctum` + `throttle:admin` group.
- Public: `routes/api.php` lines 100–101 under `v1/general` prefix (`GET currencies`, `POST currencies/select`).
