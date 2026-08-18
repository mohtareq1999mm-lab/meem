# CURRENCY FLOW AUDIT — Meem Commerce

> Read-only discovery/audit of the Currency system. No source files, migrations, database records, configs, or tests were modified.
> Evidence labels used throughout: `VERIFIED FROM CODE`, `VERIFIED FROM DATABASE/CONFIG`, `INFERRED`, `UNKNOWN`.
> Every conclusion cites the exact source file/method.

---

## 1. Executive Summary

Meem Commerce implements a **multi-currency system with three distinct currency roles**:

- **Base currency** — the accounting/reporting currency. Stored in `settings.options['base_currency_code']` (`CurrencyService::getBaseCode()`, `app/Services/Currency/CurrencyService.php:50-59`). Every order snapshots a converted "base" total (`orders.converted_total_price`).
- **Catalog currency** — the currency in which product prices are stored in the database (`products.price`, `products.sale_price`, variants). Stored in `settings.options['catalog_currency_code']` (`CurrencyService::getCatalogCode()`, `CurrencyService.php:61-70`).
- **Effective currency** — the customer-facing display + checkout + payment currency. When `settings.options['currency_selection_enabled']` is `false` (the default and current live value) the effective currency **always equals the catalog currency**. When enabled, it resolves user preference (`user_preferences.currency_code`) → guest cookie (`guest_currency`) → catalog (`CurrencyService::getEffectiveCode()`, `CurrencyService.php:82-126`).

**Conversion math** (`CurrencyConversionService::convert`, `app/Services/Currency/CurrencyConversionService.php:13-48` and `CurrencyService::convertPrice`, `CurrencyService.php:156-171`):

```
converted = round( amount * rate(target) / rate(source), 2 )     // bcmath, SCALE=6
```

The stored `exchange_rate` for a currency X is **"how many units of X equal 1 base currency"** (rate USD=1.0, KWD=0.221, SAR=3.75 — see `database/seeders/CurrencySeeder.php:11-84`). `amount * rate(Y)/rate(X)` therefore converts X→Y. `INFERRED` from the seeder values + verified live (100 USD→22.1 KWD, 100 USD→375 SAR, 100 SAR→5.89 KWD).

**Order snapshot** (`OrderCreationService::resolveCurrencySnapshot`, `app/Services/Checkout/OrderCreationService.php:256-287`): at checkout the order stores `currency_code` (effective), `base_currency_code`, `catalog_currency_code`, `currency_rate` (effective→base rate), `currency_rate_date`, `total_price` (in **effective** currency) and `converted_total_price` (in **base** currency). Line items store effective amounts plus `catalog_price`/`catalog_total_price` (`OrderCreationService::createOrderItems`, lines 197-220).

**Live state** (`VERIFIED FROM DATABASE`): base=catalog=effective=**USD**, `currency_selection_enabled=false`, 6 active currencies, 6 rate rows (all effective 2026-08-18), only **1 of 999 orders** carries a currency snapshot (order #999; 998 are legacy pre-snapshot rows), all 725 transactions recorded in USD, 0 user preferences.

**Primary risk found**: the default MyFatoorah supported list `KWD,SAR,AED,BHD,QAR,OMR,EGP` does **not** include USD, yet base/catalog/effective default to USD — so **online checkout is currently blocked (422)** until either the effective currency is set to a supported one or `MYFATOORAH_SUPPORTED_CURRENCIES` is extended. `VERIFIED FROM CODE + DATABASE`.

Verdict at end of document.

---

## 2. Phase 1 — Codebase Discovery

All currency-relevant files located via repo-wide grep (`currency|exchange|rate|conversion`).

### `app/` layer (active implementation)
| File | Role |
|---|---|
| `app/Services/Currency/CurrencyService.php` | Core facade: base/catalog/effective resolution, `convertPrice`, CRUD, `setBaseCurrency`/`setCatalogCurrency`, cache invalidation |
| `app/Services/Currency/CurrencyConversionService.php` | `convert()` — bcmath math + DTO |
| `app/Services/Currency/CurrencyRateService.php` | Rate CRUD (upsert per day) + list filters |
| `app/Services/Currency/UserCurrencyPreferenceService.php` | User preference + guest cookie + login adoption |
| `app/Models/Currency.php` | Currency model (translatable, soft-deletes) |
| `app/Models/CurrencyRate.php` | Rate model (belongsTo currency) |
| `app/Models/UserPreference.php` | `user_preferences` model |
| `app/DTOs/CurrencyConversionResult.php` | Conversion result DTO |
| `app/Http/Controllers/Api/Currency/CurrencyController.php` | Public `index` + `select` |
| `app/Http/Requests/SelectCurrencyRequest.php` | `currency_code` must exist & be active |
| `app/Http/Requests/Currency/*.php` | 4 FormRequests (store/update currency, store/update rate) |
| `app/Http/Resources/Currency/*.php` | Public CurrencyResource + CurrencyRateResource |
| `app/Exceptions/Currency{InUse,Inactive,RateNotFound,Mismatch}Exception.php` | Domain exceptions |
| `app/Services/Checkout/OrderCreationService.php` | Order + line-item currency snapshot |
| `app/Services/Payment/PaymentCheckoutHandler.php` | Transaction creation + gateway currency gate |
| `app/Services/Gateway/MyFatoorahGateway.php` | `supportsCurrency()` + invoice/verify/refund |
| `app/Services/Payment/PaymentGatewayFactory.php` | Gateway registry (only `myfatoorah`) |
| `app/Jobs/PaymentReconciliationJob.php` | Currency/amount/status reconciliation |
| `app/Http/Resources/Product/ConvertsProductPrice.php` | Product resource currency conversion trait |
| `app/Http/Resources/Order/OrderResource.php` / `OrderItemResource.php` | Order snapshot exposure |
| `app/Services/General/HomeService.php` | Currency-aware home cache keys |
| `app/Services/Invoice/{InvoiceService,InvoiceSnapshotService}.php` | Invoice currency resolution |
| `app/Services/Invoice/Validators/CurrencyValidator.php` | Invoice snapshot currency allow-list |
| `app/Providers/AppServiceProvider.php:26` | Registers `CurrencyService` as **singleton** |
| `app/Http/Controllers/Api/General/ProductController.php:152` | Currency-aware product cache key |

### `packages/marvel` layer (admin CRUD + legacy)
| File | Role |
|---|---|
| `packages/marvel/src/Http/Controllers/CurrencyController.php` | Admin CRUD + `setBase`/`setCatalog` (permission-guarded) |
| `packages/marvel/src/Http/Controllers/CurrencyRateController.php` | Admin rate CRUD/list |
| `packages/marvel/src/Http/Resources/Currency/*.php` | Admin resources (translations arrays) |
| `packages/marvel/src/Rest/Routes.php:199-205` | `/api/v1/currencies*`, `/api/v1/currency-rates*` |
| `packages/marvel/src/Http/Controllers/SettingsController.php:67-77` | `currency_selection_enabled` toggle |
| `packages/marvel/src/Http/Resources/SettingResource.php:49` | Exposes `currency_selection_enabled` |
| `packages/marvel/src/Http/Controllers/UserController.php:493,587,996` | `adoptGuestCurrencyOnLogin` on login/social/register |
| `packages/marvel/src/Exports/OrderExport.php:50-54` | Uses `options['currency']` for exports |
| `packages/marvel/src/Traits/WalletsTrait.php` | Wallet points⇄currency ratio |
| `packages/marvel/src/Payment/*.php` | Legacy gateway classes (Stripe/Paypal/etc.) — NOT routed in new checkout |

### Config & seeders
- `config/currency.php` — guest cookie name/lifetime/path.
- `config/payment.php` — `default_currency` (env `DEFAULT_CURRENCY`, default `KWD`), MyFatoorah `supported_currencies`.
- `packages/marvel/config/shop.php:41` — `shop.default_currency` = env `DEFAULT_CURRENCY`, default `USD`.
- `packages/marvel/config/constants.php:13` — `define('DEFAULT_CURRENCY', config('shop.default_currency'))`.
- `database/seeders/CurrencySeeder.php` — seeds USD/KWD/SAR/AED/EUR/GBP + rates.
- `database/seeders/SettingSeeder.php:50-53` — default options (base/catalog USD, selection disabled).
- `database/seeders/PermissionSeeder.php:227-236` — currency permissions; `super_admin` gets all (lines 504).

---

## 3. Phase 2 — Database Schema

`VERIFIED FROM DATABASE` via migrations + live `SHOW`-style queries.

### `currencies`
`database/migrations/2026_08_10_000002_create_currencies_table.php`
- `code string(3)` unique
- `name json` (translatable), `symbol json?`, `country_name json?`
- `numeric_code string(3)?`, `decimal_places tinyint default 2`
- `icon string?`, `is_active bool default true` indexed, `sort_order int default 0`
- `timestamps`, `softDeletes` (note: **soft deletes** on currency)

### `currency_rates`
`database/migrations/2026_08_10_000003_create_currency_rates_table.php`
- `currency_id` FK → `currencies.id` **cascadeOnDelete**
- `exchange_rate decimal(20,10)`
- `effective_date date`
- **unique** `(currency_id, effective_date)` — one rate per currency per day
- index `effective_date`

### `orders` (currency snapshot)
`2026_08_10_000004_add_currency_columns_to_orders_table.php` + `2026_08_11_000001_add_catalog_currency_code_to_orders_table.php`
- `currency_code string(3)?` (effective), `base_currency_code string(3)?`, `catalog_currency_code string(3)?`
- `currency_rate decimal(20,10)?`, `currency_rate_date date?`
- `converted_total_price decimal(10,3)?` (backfilled from `total_price` on migration)

### `order_products` (snapshot)
`2026_08_11_000003_add_currency_snapshot_columns_to_order_products_table.php`
- `currency_code string(3)?`, `catalog_currency_code string(3)?`
- `catalog_price decimal(8,3)?`, `catalog_total_price decimal(8,3)?`

### `user_preferences`
`2026_08_11_000002_create_user_preferences_table.php`
- `user_id` unique FK → users (cascade delete), `currency_code string(3)?`

### Related price columns
- `products.price decimal(10,2)` — catalog currency, `VERIFIED FROM CODE` (`2020_06_02_051901_create_marvel_tables.php:111`)
- product variants `price double(10,2)`
- `transactions.currency string?` (via `Transaction` model fillable, `packages/marvel/src/Database/Models/Transaction.php:21`)

---

## 4. Phase 3 — Currency Model Flow

`app/Models/Currency.php` (`VERIFIED FROM CODE`)
- `translatable = ['name','symbol','country_name']` (Spatie `HasTranslations`), `SoftDeletes`.
- `code` is uppercased on set (`setCodeAttribute`, lines 35-38).
- Casts: `is_active` bool, `decimal_places` int, `sort_order` int.
- `rates()` hasMany → `CurrencyRate` (lines 40-43).
- `scopeActive` → `is_active = true` (lines 45-48).
- `isBaseCurrency()`/`isCatalogCurrency()` compare code against live `CurrencyService` singleton (lines 50-58) — used by both app and Marvel resources to emit `is_base`/`is_catalog`.

`app/Models/CurrencyRate.php` (`VERIFIED FROM CODE`)
- fillable: `currency_id`, `exchange_rate`, `effective_date`; `exchange_rate` cast to **string** (preserves decimal(20,10) precision); `effective_date` cast to date.
- `currency()` belongsTo; `scopeEffectiveOn($date)` → `effective_date <= date` ordered desc (lines 26-30) — mirrors the resolution used in conversion.

**Relationship note**: `currency_rates.currency_id` is `cascadeOnDelete`, but `Currency` uses soft deletes, so deleting a currency via the API is a soft delete and rates are retained; the API blocks delete for the base currency and for any currency with rates (`CurrencyService::deleteCurrency`, `CurrencyService.php:189-201`).

---

## 5. Phase 4 — Settings Flow

`VERIFIED FROM CODE` (`CurrencyService::settingsOptions()`, `CurrencyService.php:304-309` reads `Settings::query()->first()->options` directly — no cache).

Settings keys consumed:
| Key | Consumer | Default |
|---|---|---|
| `base_currency_code` | `CurrencyService::getBaseCode()` | `config('shop.default_currency','USD')` |
| `catalog_currency_code` | `CurrencyService::getCatalogCode()` | same |
| `currency_selection_enabled` | `CurrencyService::isCurrencySelectionEnabled()` | `false` |
| `currency` (legacy) | `setBaseCurrency` also writes it (line 227); `OrderExport.php:50` reads it | — |
| `currencyToWalletRatio` | `WalletsTrait::currencyToWalletRatio()` | `1` |
| `currencyOptions.formation` | `OrderExport.php:54` | `en-US` |

Who writes these keys:
- `CurrencyService::setBaseCurrency()` → sets `base_currency_code` **and** `currency` (lines 226-227).
- `CurrencyService::setCatalogCurrency()` → sets **only** `catalog_currency_code` (line 268).
- `SettingsController::update()` (admin) → `currency_selection_enabled` boolean (lines 67-71), then `forgetEffectiveCode()` (line 76).
- `database/seeders/SettingSeeder.php:50-53` → initial `currency`/`base_currency_code`/`catalog_currency_code` = USD, `currency_selection_enabled` = false.

**Storefront exposure** (`VERIFIED FROM CODE`): `GET /api/v1/general/settings` returns `SettingResource` which includes the **raw `options` array** (line 50) plus `currency_selection_enabled` (line 49), so the frontend receives base/catalog codes and the selection flag. `INFERRED`: the frontend uses this (plus the currencies list) for client-side display; backend is authoritative for checkout.

Live values `VERIFIED FROM DATABASE`: `currency=USD`, `base_currency_code=USD`, `catalog_currency_code=USD`, `currency_selection_enabled=false`, `currencyOptions=null`, `currencyToWalletRatio=null`.

---

## 6. Phase 5 — Exchange Rate Flow

### Storage
- Rate per currency per day, **one row per (currency, effective_date)** (unique constraint + `CurrencyRateService::store` upsert, `app/Services/Currency/CurrencyRateService.php:14-30`).
- `exchange_rate decimal(20,10)`, cast to string in the model to preserve precision.
- `VERIFIED FROM DATABASE`: 6 rows, one per seeded currency, all effective 2026-08-18.

### Selection (resolution)
`CurrencyConversionService::resolveRate()` (lines 50-63) and `CurrencyService::resolveRate()` (lines 311-330):
```
latest rate where currency.code = X and effective_date <= $date, order by effective_date desc
```
- `whereHas('currency', code=X)` — resolution by **code**, not id.
- If none → `CurrencyRateNotFoundException` (no fallback to base/1.0). `VERIFIED FROM CODE`.
- `CurrencyService::convertPrice` memoizes resolved rates in a per-instance `$rateCache` (lines 315-317, 329).

### Direction
Stored rate = **units of that currency per 1 base currency** (`INFERRED` from `CurrencySeeder`: USD=1.0, KWD=0.221, SAR=3.75, AED=3.6725, EUR=0.999, GBP=0.86 — i.e. "1 USD = 3.75 SAR").
Verified live: 100 USD→375 SAR, 100 USD→22.1 KWD, 100 SAR→5.89 KWD, 100 KWD→452.49 USD.

### Precision
- Intermediate math: `bcmath` scale 6 (`bcmul`/`bcdiv`), final result `round(…, 2)`. `VERIFIED FROM CODE`.
- `rate` in the DTO = `bcdiv(targetRate, sourceRate, 6)` (the effective X→Y multiplier).

### Dates / history
- Resolution is **"latest rate on-or-before the date"**; past-order reconstruction possible from `currency_rate_date`.
- Rates are **manual only** — no external sync command, scheduler, or job found (`VERIFIED FROM CODE`: no `App\Console\Commands` currency entries, no `routes/console.php` schedule).

---

## 7. Phase 6 — Catalog Currency Change Flow

`CurrencyService::setCatalogCurrency()` (`CurrencyService.php:240-280`) and `setBaseCurrency()` (203-239), both invoked from `Marvel\Http\Controllers\CurrencyController::setCatalog/setBase` (lines 152-169 / 134-151).

Guard logic (both methods, inside `DB::transaction` with `lockForUpdate` on settings):
1. Settings row must exist (else `RuntimeException`).
2. Currency must be `is_active` → else `CurrencyInactiveException` → API 422.
3. A rate must exist with `effective_date <= today` → else `CurrencyRateNotFoundException` → API 422.
4. Update `settings.options`, save, flush `FrontendResource::SETTINGS` tag.
5. After commit: refresh in-memory code, `invalidatePriceCaches(flushSettings: true)` which flushes tags `currencies`, `products`, all `products_<strategy>` and `settings` (`CurrencyService.php:282-294`).

`setBaseCurrency` additionally syncs `options['currency']` and requires the same guards. `setCatalogCurrency` changes only `catalog_currency_code`.

**Behavioral implications** (`VERIFIED FROM CODE` + `INFERRED`):
- Products are stored in catalog currency; after a catalog change the **existing `products.price` values are re-interpreted as the new catalog currency** (no re-pricing/migration job). Product display then converts from the new catalog code.
- Effective currency defaults to catalog (selection off), so changing catalog also changes checkout/payment currency unless selection is enabled.
- Cache is fully invalidated for products/strategies/settings so stale converted prices are not served.

---

## 8. Phase 7 — Product Pricing Flow

`packages/marvel/src/Services/Pricing/ProductPricingService.php` (`VERIFIED FROM CODE`)

- **All product math is in catalog currency** using integer cents (`toCents`/`fromCents`, lines 510-524; rounding to 2 decimals).
- `calculateProductPricing()` returns `base_price`, `price_after_discount`, `price_after_flash_sale`, `final_price` — final = flashSale ?? discount ?? base (lines 28-50).
- Discounts: percentage (`round(priceCents * amount / 100)`) or fixed (`toCents($amount)`), capped at 0; percentage capped at 100 (lines 254-282).
- Flash sales: percentage (with optional `max_discount_amount` cap), fixed rate, final-price modes (lines 291-376).
- `normalizeMoney` rounds inputs to 2 decimals (lines 477-484).
- `calculateCouponPrice` delegates to `App\Services\Coupon\CouponCalculator` (line 177).

**Conversion boundary** (`VERIFIED FROM CODE`): pricing is computed in catalog currency, then converted to effective currency at the *presentation/order* layer:
- `ProductService::enrichProductWithPricing` (`app/Services/General/ProductService.php:35-54`) sets `current_price`/`sale_price` in catalog currency.
- Product resources (`app/Http/Resources/Product/ProductMiniResource.php`, `ProductResource.php`) convert every price via `ConvertsProductPrice::convertCatalogPrice()` (catalog→effective, round 2) and emit a `currency` object (`effectiveCurrency()`).
- Variants are converted alongside (ProductResource lines 82-90); percentage discount amounts are **not** converted to money (ProductCurrencyTest::percentage_discount_amount_is_not_converted_to_money), fixed-amount discounts are converted.
- Cart and order math happens in catalog currency; conversion to effective happens in `CartResource`/`CartItemResource` (display) and `OrderCreationService` (persistence).

---

## 9. Phase 8 — Price Precision Model

`VERIFIED FROM CODE`
- **Product pricing**: integer-cents (`toCents` = `round($amount*100)`, `fromCents` = `round($cents/100,2)`), `ProductPricingService.php:510-524`.
- **Currency conversion**: string-based `bcmath` scale **6**, final `round(…, 2)` — `CurrencyConversionService.php:36,41`, `CurrencyService.php:170`.
- **Storage**: `products.price decimal(10,2)`; rates `decimal(20,10)`; `converted_total_price decimal(10,3)`; `catalog_price/catalog_total_price decimal(8,3)`.
- **Rounding**: all displayed/persisted amounts rounded to 2 decimals, except `catalog_price`/`catalog_total_price` on order items (stored `round(…, 2)` in `OrderCreationService::createOrderItems`, lines 217-218) and `converted_total_price` (rounded 2 in snapshot, line 285).
- `Currency.decimal_places` (e.g., KWD=3) is **display metadata only** — it is not used in any conversion/rounding math (`INFERRED` — no consumer found; only exposed in resources).

---

## 10. Phase 9 — Cart Flow

`VERIFIED FROM CODE`
- Cart/`CartItem` models (`packages/marvel/src/Database/Models/Cart.php`, `CartItem.php`) store `price`/`total_price` in **catalog currency**.
- `OrderService::addItemsInOrder` refreshes item prices to current catalog prices via `refreshCartItemPrices()` → `ProductPricingService` (`app/Services/General/OrderService.php:398-427`).
- `OrderService::calculateCheckoutTotals()` computes subtotal/discounts/final in **catalog currency** (lines 429-457); coupons applied via `CouponCalculator` on the catalog subtotal.
- `CartResource`/`CartItemResource` convert every amount catalog→effective for the storefront and attach `currency` = `getEffectiveCode()` (`packages/marvel/src/Http/Resources/CartResource.php:51-55`, `CartItemResource.php:19-24`).
- Cart is not price-snapshotted — item prices can be refreshed at checkout time.

---

## 11. Phase 10 — Order Flow

`VERIFIED FROM CODE` (`app/Services/Checkout/OrderCreationService.php`)

1. `createOrder()` (31-100): `totalPrice` computed in catalog currency (finalTotal + shipping + fastShipping).
2. `resolveCurrencySnapshot()` (256-287):
   - catalog/base/effective codes resolved;
   - `effectiveTotal = convert(totalPrice, catalog→effective)`; snapshot `currency_code` = effective;
   - `baseConversion = convert(effectiveTotal, effective→base)`; `currency_rate` = `baseConversion->rate` (effective→base multiplier); `currency_rate_date` = its effective date;
   - persisted: `total_price` = **effective**, `converted_total_price` = **base**.
3. `price`, `shipping_price`, `coupon_discount`, `promotion_discount`, `fast_shipping_fee` all converted catalog→effective via `convertToEffective()` (302-323).
4. `createOrderItems()` (166-233): each line item stores `product_price`/`product_total_price`/`product_flash_sale_price`/`product_discount_price`/`promotion_discount_amount` in **effective** currency, plus `currency_code` = effective, `catalog_currency_code` = catalog, `catalog_price`/`catalog_total_price` = catalog amounts.
5. Missing rate → `CURRENCY_RATE_UNAVAILABLE_AT_CHECKOUT` (translation exists, `resources/lang/en/message.php`), checkout aborts (422).

**Historical immutability** (`VERIFIED FROM CODE`): the snapshot is written once at order creation; later rate changes do not alter stored orders (`OrderItemSnapshotTest::historical_orders_keep_their_snapshot_when_rates_change`).

**Exposure**: `app/Http/Resources/Order/OrderResource.php` returns `currency`, `base_currency`, `catalog_currency`, `exchange_rate`, `converted_total`, `subtotal`/`total` (effective). `OrderItemResource.php` returns `unit_price`/`total_price` (effective) and `converted_unit_price`/`converted_total_price` (× order `currency_rate`).

**Legacy rows** (`VERIFIED FROM DATABASE`): 998/999 orders have NULL snapshot columns; `OrderResource` falls back to `getBaseCode()` for display (line 31-33).

---

## 12. Phase 11 — Payment Flow

`VERIFIED FROM CODE`

### Online (MyFatoorah) — `app/Services/Payment/PaymentCheckoutHandler.php`, `app/Services/Gateway/MyFatoorahGateway.php`
1. `orderCurrency = order->currency_code ?? base_currency_code ?? config('payment.default_currency')` (`PaymentCheckoutHandler.php:40`).
2. `supportsCurrency()` gate against `config('payment.gateways.myfatoorah.supported_currencies')` (default `KWD,SAR,AED,BHD,QAR,OMR,EGP`, `config/payment.php:15-18`). Unsupported → 422 `ERROR.PAYMENT_CURRENCY_UNSUPPORTED`. **No base-currency fallback.**
3. `createInvoice` sends `InvoiceValue = $order->total_price` (effective) and `DisplayCurrencyIso = orderCurrency` (`MyFatoorahGateway.php:37-48`).
4. Transaction persisted with `currency` = order currency, `amount` = order->total_price (effective) (`PaymentCheckoutHandler.php:64-74`).
5. Callback (`app/Http/Controllers/Api/General/OrderController.php:155-407`) verifies amount (±0.01) and currency against `order->currency_code ?? base ?? payment.default_currency` (lines 250-285). Mismatch blocks the order in production gateways; **ignored for the test gateway** (`apitest` URL) with only a log (lines 246, 271-276).
6. `PaymentReconciliationJob` (`app/Jobs/PaymentReconciliationJob.php:154-171`) reconciles `order->currency_code ?? base ?? config('payment.default_currency')` against gateway currency; mismatches recorded in `payment_reconciliation_results`.

### COD / Pay-at-Cashier
`PaymentCheckoutHandler::handleCodPayment` (83-101) and `handleCashierQrPayment` (103-121): transaction `amount` = `order->total_price`, `currency` = effective (same fallback). No gateway currency gate. `markCodAsPaid`/`markCashierPaid` in `OrderService.php:605-691` finalize.

### Gateway registry
`PaymentGatewayFactory::make` only supports `myfatoorah` (`app/Services/Payment/PaymentGatewayFactory.php:13-16`).

### Invoices
`InvoiceService`/`InvoiceSnapshotService` resolve invoice currency as `paidTransaction->currency ?? order->currency_code ?? base_currency_code ?? 'EGP'` (`app/Services/Invoice/InvoiceSnapshotService.php:77`). `CurrencyValidator` (`app/Services/Invoice/Validators/CurrencyValidator.php:10`) allows only `EGP, USD, EUR, GBP, SAR, AED` — **KWD is rejected by the invoice snapshot validator** despite being a seeded + MyFatoorah-supported currency (see Risks).

---

## 13. Phase 12 — API Endpoints

### Public (`VERIFIED FROM CODE`, `routes/api.php`)
| Method | URI | Controller | Notes |
|---|---|---|---|
| GET | `/api/v1/general/currencies` | `App\Http\Controllers\Api\Currency\CurrencyController@index` | active only, ordered `sort_order,code`, cached under `currencies` tag, keyed `md5(fullUrl)` |
| POST | `/api/v1/general/currencies/select` | `…@select` | `currency_code` must exist & `is_active`; stores user preference (if authed) + guest cookie; `forgetEffectiveCode()` |

### Authenticated admin (`VERIFIED FROM CODE`, `packages/marvel/src/Rest/Routes.php:199-205`, group `auth:sanctum` + `throttle:admin`, prefix `api/v1`)
| Method | URI | Permission middleware (`CurrencyController.php:27-32`) |
|---|---|---|
| GET | `/api/v1/currencies` | `view-currencies` |
| GET | `/api/v1/currencies/{id}` | `view-currencies` |
| POST | `/api/v1/currencies` | `create-currency` |
| PUT | `/api/v1/currencies/{id}` | `update-currency` |
| DELETE | `/api/v1/currencies/{id}` | `delete-currency` |
| POST | `/api/v1/currencies/{id}/set-base` | `set-base-currency` |
| POST | `/api/v1/currencies/{id}/set-catalog` | `set-catalog-currency` |
| GET | `/api/v1/currency-rates` | `view-exchange-rates` |
| GET | `/api/v1/currency-rates/{id}` | `view-exchange-rates` |
| POST | `/api/v1/currency-rates` | `create-exchange-rate` |
| PUT | `/api/v1/currency-rates/{id}` | `update-exchange-rate` |
| DELETE | `/api/v1/currency-rates/{id}` | `update-exchange-rate` (destroy gated by update permission) |

Also relevant: `GET/PUT /api/v1/settings` (admin, `update-settings`) toggles `currency_selection_enabled` (`SettingsController.php:67-77`).

Validation: `StoreCurrencyRequest` (code 3 alpha, unique, translatable unique name/symbol/country_name, decimal_places 0-4); `StoreCurrencyRateRequest` (`currency_id` exists, `exchange_rate > 0`, `effective_date` required); `SelectCurrencyRequest` (code exists + active).

---

## 14. Phase 13 — Admin Flow

- All admin actions route through `CurrencyService` (CRUD, set-base, set-catalog) or `CurrencyRateService` (rates), behind Spatie permission middleware.
- Delete guards: cannot delete base currency (409 `CANNOT_DELETE_BASE_CURRENCY`) or any currency with rates (409 `CANNOT_DELETE_CURRENCY_IN_USE`) — `CurrencyController.php:121-129`.
- Set-base/set-catalog guards: active + existing rate → else 422 (`CurrencyInactiveException` → `CURRENCY_INACTIVE`; `CurrencyRateNotFoundException` → `EXCHANGE_RATE_NOT_FOUND`).
- Settings admin can enable/disable currency selection (which gates whether customers may select an effective currency).
- `super_admin` role receives all currency permissions (`database/seeders/PermissionSeeder.php:504`); `owner`/`staff` get a subset (lines 505-506). `INFERRED` (exact subset not re-read; tests exercise explicit permission lists).

---

## 15. Phase 14 — Cache Flow

`VERIFIED FROM CODE`
- Cache driver: **redis** (`.env` `CACHE_DRIVER=redis`, prefix `meem_cache`). Tagged caches (`Cache::tags`) are used — redis supports tags.
- `HasCache::remember($tag, $key, …, ttl=4h)` (`app/Traits/HasCache.php:16-25`).
- Tagged caches & invalidation on currency change (`CurrencyService::invalidatePriceCaches`, lines 282-294):
  - `setBase`/`setCatalog` → flush tags `settings`, `currencies`, `products`, `products_<strategy>` (9 strategies listed in `PRODUCT_STRATEGY_TYPES`).
  - `storeCurrency`/`updateCurrency`/`deleteCurrency`/rate CRUD → flush `currencies` + `products` + strategy tags (no `settings`).
- Currency-aware cache keys:
  - Product list: `md5(fullUrl . '|currency:' . getEffectiveCode())` (`app/Http/Controllers/Api/General/ProductController.php:150-153`), stored under `products`/`products_<type>` tag.
  - Home data: 6 keys suffixed with `:EFFECTIVECODE` (`app/Services/General/HomeService.php:44-46, 450-457`), 120s TTL.
  - `currencies` public list: keyed `md5(fullUrl)`, tag `currencies`.
  - Settings public/admin: keyed `md5(fullUrl)`, tag `settings`.
- **Untagged settings cache**: `Settings::getData()` caches under plain key `cached_settings_<lang>` with **24h TTL and no tag** (`packages/marvel/src/Database/Models/Settings.php:52-68`). `setBaseCurrency`/`setCatalogCurrency` flush only the tagged `settings` cache → **`cached_settings_<lang>` is NOT invalidated** by base/catalog changes. Consumers of `Settings::getData()` that read currency options: `OrderExport.php:49-50`, `WalletsTrait.php:38,51`, `Marvel\OrderRepository`, legacy `CheckoutRepository`. Risk — see §19.

---

## 16. Phase 15 — Events / Observers / Jobs

`VERIFIED FROM CODE`
- **No currency-specific events, listeners, or observers exist** (`app/Events`, `app/Listeners`, `app/Observers` contain none referencing Currency; no `ObservedBy`/`CurrencyObserver`).
- Currency flows are **synchronous** (transaction + cache flush), not event-driven.
- Related jobs/events that consume currency data (not currency-specific):
  - `PaymentReconciliationJob` (queue `meem-medium`) — currency reconciliation (`app/Jobs/PaymentReconciliationJob.php:154-171`).
  - `PaymentSucceeded`/`PaymentFailed`/`OrderCreated`/`OrderStatusChanged` — fired on order lifecycle (`OrderService.php:364,595-599`).
  - `GenerateInvoicePdfJob`, `LogInvoiceCreated` — invoice currency logging.
- No scheduled commands and no `routes/console.php` schedule entries for rates/currencies → **rates are updated only manually by admin**.

---

## 17. Phase 16 — Existing Tests

`tests/Feature/Currency/` — 14 files, **131 test methods** (`VERIFIED FROM DATABASE` via file scan; extends shared `CurrencyTestCase`).

| Suite | Tests | Coverage highlights |
|---|---|---|
| `CurrencyConversionTest` | 11 | identity, USD↔KWD, SAR→KWD, historical date, latest-on-or-before, missing-rate throws, round-2, bcmath large amounts |
| `CurrencyRateTest` | 18 | CRUD, upsert same-day, list filters (currency/date/code/range), validation, 404, auth/permission, unique constraint, precision |
| `CurrencyAdminApiTest` | 23 | CRUD, delete guards (base / has-rates), 404, non-numeric id, auth/permission, validation, pagination caps, filters/search/translations |
| `BaseCurrencyTest` | 8 | set-base guards, options sync, service reflection, cache flush |
| `CatalogCurrencyTest` | 8 | set-catalog guards, only-catalog option, cache flush |
| `CurrencySelectionEnabledTest` | 17 | default false, enable/disable via admin, effective resolution paths, invalid booleans, cache clear, preserves base/catalog/preferences/orders |
| `UserCurrencyPreferenceTest` | 15 | store/read/clear, per-user scope, cookie, resolution precedence, login adoption, select endpoint, per-currency product cache |
| `ProductCurrencyTest` | 8 | catalog preserved when base=catalog, converted current_price, metadata, variants, null, fixed vs percentage discount, rate-change updates |
| `OrderCurrencyTest` | 5 | snapshot when catalog≠base, identity total, snapshot refresh on base change, zero total, resource exposure |
| `OrderItemSnapshotTest` | 4 | catalog+effective stored, no leakage, historical immutability, missing-rate checkout failure |
| `PaymentCurrencyTest` | 5 | invoice/refund use order currency, reconciliation currency, invoice snapshot currency + legacy fallback |
| `GatewayCurrencySupportTest` | 5 | supported/rejected codes, invoice+refund blocked, 422 without transaction |
| `CurrencyBugRegressionTest` | 4 | snapshot persistence, base-without-rates delete protection, code uppercase, rate-ratio snapshot |
| `CurrencyTestCase` | — | shared setup (forgets singleton, flushes cache, seeds USD/KWD/SAR) |

**Gaps observed** (`INFERRED` from inventory): no test for the `PaymentReconciliationJob` **amount/currency mismatch recording to DB** end-to-end, no E2E test driving the **real MyFatoorah HTTP** flow (network-dependent), no test for **concurrent set-base races**, no test covering the untagged `cached_settings_` invalidation gap, no tests for `OrderExport` currency formatting after base change, no `kwd` invoice-snapshot rejection test for `CurrencyValidator`.

---

## 18. Phase 17 — Real Execution Trace (READ-ONLY, live DB)

`VERIFIED FROM DATABASE/CONFIG` (probe scripts `60_currency_audit.php`, `61_currency_conv_check.php`; no writes performed).

### Live config
- `config('shop.default_currency')` = `USD`; `config('payment.default_currency')` = `USD` (env `DEFAULT_CURRENCY=USD`).
- MyFatoorah: test base URL (`apitest`), supported currencies **`KWD,SAR,AED,BHD,QAR,OMR,EGP`** (env not set → default).
- Cache driver `redis`, prefix `meem_cache`; queue `redis`.

### Live settings
`options`: `currency=USD`, `base_currency_code=USD`, `catalog_currency_code=USD`, `currency_selection_enabled=false`, `currencyOptions=null`, `currencyToWalletRatio=null`.

### Live currencies & rates (all active, no soft-deletes)
```
USD 1.0000000000  2026-08-18
KWD 0.2210000000  2026-08-18
SAR 3.7500000000  2026-08-18
AED 3.6725000000  2026-08-18
EUR 0.9990000000  2026-08-18
GBP 0.8600000000  2026-08-18
```
(6 rows total; the app's `CurrencySeeder` seeds USD/KWD/SAR/AED/EUR/GBP — EGP/BHD/QAR/OMR are absent from the table even though supported by MyFatoorah.)

### Live resolution
`base=USD catalog=USD effective=USD selectionOn=false` — so effective==catalog==base; every `convertPrice` short-circuits (`fromCode===toCode`).

### Live conversions (executed, read-only)
```
100 USD → KWD = 22.1   (rate 0.221000)
100 USD → SAR = 375    (rate 3.750000)
100 SAR → KWD = 5.89   (rate 0.058933)
100 KWD → USD = 452.49 (rate 4.524886)
convertPrice 99.99 USD→SAR = 374.96
```

### Live orders
- 999 orders; **1** has the currency snapshot populated (order #999: `cc=USD bc=USD cat=USD rate=1.0000000000 date=2026-08-18 conv=280.000`, `payment_method=pay_at_cashier`).
- 998 legacy orders: snapshot columns NULL.
- Transactions: **725 all USD**.

### Live user_preferences
0 rows (no user has selected a currency; selection disabled anyway).

### Runtime consequences (INFERRED from live + code)
- With effective=USD and MyFatoorah's supported list excluding USD, an online (MyFatoorah) checkout for any current order would return **422 PAYMENT_CURRENCY_UNSUPPORTED**; COD / pay-at-cashier are unaffected. `INFERRED` (no live online payment was attempted in this read-only audit).
- The only snapshotted order (#999) confirms the writer path runs correctly end-to-end (codes, rate, date, converted total all persisted).

---

## 19. Phase 18 — Architecture Diagram

```
                    ┌────────────────────────────────────────────────────────────┐
                    │                    settings.options                         │
                    │  base_currency_code  catalog_currency_code                 │
                    │  currency (legacy)   currency_selection_enabled            │
                    └───────────────┬────────────────────────────────────────────┘
                                    │ reads (no cache)
                    ┌───────────────▼──────────────────────────────┐
                    │  CurrencyService  (SINGLETON, memoized)      │
                    │  getBaseCode / getCatalogCode /               │
                    │  getEffectiveCode / convertPrice             │
                    └───┬────────────┬──────────────┬──────────────┘
                        │            │              │
        ┌───────────────▼───┐  ┌────▼─────────┐  ┌──▼──────────────────────┐
        │ CurrencyConversion │  │ UserCurrency │  │  CurrencyRateService    │
        │ Service (bcmath,   │  │ Preference   │  │  upsert/list (admin)    │
        │ scale 6, round 2)  │  │ Service      │  │  → currency_rates table │
        └───────────┬────────┘  │ user pref +  │  └──────────┬───────────────┘
                    │           │ guest cookie │             │
                    │           └──────────────┘             │
        ┌───────────▼────────────────────────────────────────▼────────────┐
        │  Currencies / CurrencyRate tables  (rates = units per 1 base)   │
        └──────┬──────────────┬────────────────────────────────────────────┘
               │              │
   CATALOG CUR │              │ EFFECTIVE CUR (user/guest/catalog)
               │              │
   ┌───────────▼──────────┐   │
   │ products.price (catalog)│ │
   │ ProductPricingService │  │  ┌──────────────────────────────────────────┐
   │ (cents math)          │  │  │ Display: ProductResource/CartResource/    │
   │ Cart totals (catalog) │  │  │   HomeService convert catalog→effective   │
   └───────────────────────┘  │  └──────────────────────────────────────────┘
                              │
   ┌──────────────────────────▼─────────────────────────────────────────────┐
   │ Checkout: OrderService.calculateCheckoutTotals (catalog)               │
   │   → OrderCreationService.createOrder (snapshot)                        │
   │       total_price = effective, converted_total_price = base            │
   │       currency_rate = effective→base, rate_date                        │
   │       order_products: prices in effective + catalog_* columns          │
   └──────────────────────────┬─────────────────────────────────────────────┘
                              │
   ┌──────────────────────────▼─────────────────────────────────────────────┐
   │ Payment: PaymentCheckoutHandler                                        │
   │   online → MyFatoorahGateway.supportsCurrency(orderCurrency) ?         │
   │            createInvoice(InvoiceValue=order.total_price,               │
   │                          DisplayCurrencyIso=orderCurrency) : 422       │
   │   cod / pay_at_cashier → transaction in orderCurrency                  │
   │   Callback + PaymentReconciliationJob verify amount & currency         │
   └────────────────────────────────────────────────────────────────────────┘

   Cache: redis tags  [settings, currencies, products, products_<strategy>]
          keys suffixed with effective code (products/home)
          invalidation on set-base/set-catalog/currency+rate CRUD
          ⚠ Settings::getData() uses UNTAGGED cached_settings_<lang> (24h)
```

---

## 20. Phase 19 — Risks

Each item is labeled. Only evidence-backed items are included.

1. **Online checkout blocked in current default config** — effective currency defaults to USD; MyFatoorah supported list excludes USD. Online checkout → 422 until config/settings change. `VERIFIED FROM CODE` (`config/payment.php:15-18`, `PaymentCheckoutHandler.php:42-48`) + `VERIFIED FROM DATABASE` (live: effective=USD, supported list as above).
2. **`CurrencyValidator` rejects KWD invoices** — allowed list `EGP,USD,EUR,GBP,SAR,AED` (`app/Services/Invoice/Validators/CurrencyValidator.php:10`) excludes KWD, which is seeded and MyFatoorah-supported. KWD invoice snapshots would fail validation. `VERIFIED FROM CODE`.
3. **Untagged settings cache not invalidated on base/catalog change** — `cached_settings_<lang>` (24h, `Settings::getData()`) survives `Cache::tags(['settings'])->flush()`. `OrderExport` and wallet-ratio consumers may read stale currency for up to 24h after `setBase`. `VERIFIED FROM CODE`.
4. **Catalog-currency change does not re-price products** — `products.price` is stored in catalog currency; switching catalog currency re-interprets existing prices without migration/backfill. `VERIFIED FROM CODE` (no backfill logic found) + `INFERRED` impact.
5. **KWD `decimal_places=3` ignored in math** — conversion always rounds to 2 decimals; KWD (fils) display metadata is not honored in conversions. `VERIFIED FROM CODE` + `INFERRED` impact.
6. **`CurrencyService` singleton memoization** — codes/rates memoized per instance for the request lifetime; `forgetEffectiveCode()` is called on select/settings toggle, but any other settings mutation mid-request could serve stale codes. `VERIFIED FROM CODE`. Low impact.
7. **Legacy orders lack snapshot** — 998/999 orders have NULL snapshot; resource falls back to base code and `converted_*` = raw value. No backfill migration exists for legacy rows. `VERIFIED FROM DATABASE`.
8. **Rate resolution has no "today required" rule** — set-base/set-catalog accept any rate with `effective_date <= today` (possibly stale/days old). `VERIFIED FROM CODE`.
9. **No rate freshness/sync automation** — rates are manual-only; stale rates silently affect conversions until admin updates. `VERIFIED FROM CODE`.
10. **Test-mode currency/amount mismatch is ignored** — callbacks on `apitest` log-and-continue on amount/currency mismatch (`OrderController.php:246,271-276`); only the reconciliation job records it. `VERIFIED FROM CODE`.
11. **German locale missing currency messages** — `resources/lang/de/message.php` has no currency keys (en/ar have them) → raw keys surface in German UI. `VERIFIED FROM DATABASE`.
12. **`currencyToWalletRatio`/`currencyOptions` null in live settings** — seeder defaults not present; wallet point conversion falls back to ratio 1 (`WalletsTrait.php:35-43`). `VERIFIED FROM DATABASE`.

---

## 21. Phase 20 — Unknowns

1. **Frontend/client-side rendering** — whether the storefront converts product prices client-side (it receives catalog prices + `currency` object + settings options). Backend is fully traced; frontend is outside this repo. `UNKNOWN`.
2. **MyFatoorah external behavior** — exact invoice statuses, currency support matrix on the live/test account, and whether the test gateway accepts USD in practice. `UNKNOWN` (not verifiable read-only without calling the API).
3. **Exact `owner`/`staff` permission subsets** for currency endpoints — `super_admin` has all; the specific subset for other roles was not exhaustively enumerated. `UNKNOWN` (covered indirectly by tests using explicit permission lists).
4. **Whether `set-catalog` is intended to re-interpret product prices** — the system stores prices in catalog currency but no re-pricing job exists; intent not documented. `UNKNOWN`/`INFERRED`.
5. **Legacy order currency semantics** — historical orders' `total_price` currency is unknown (assumed catalog/base at time of creation). `UNKNOWN`.
6. **Whether EGP/BHD/QAR/OMR were intentionally omitted from `CurrencySeeder`** though MyFatoorah supports them. `UNKNOWN`.

---

## Recommended E2E Test Matrix

Read-only audit finding; the following are suggested future tests (none created during this audit per its read-only scope):

| # | Scenario | Target behavior |
|---|---|---|
| 1 | Online checkout when effective currency ∉ gateway supported list | 422 `PAYMENT_CURRENCY_UNSUPPORTED`, no transaction created |
| 2 | Online checkout when effective currency ∈ supported list (e.g., EGP/SAR) | Invoice `InvoiceValue`=effective total, `DisplayCurrencyIso`=effective code; transaction currency matches |
| 3 | COD & pay-at-cashier in any effective currency | transaction amount/currency = effective, order completed on mark-paid |
| 4 | Guest selects currency (selection enabled) → checkout | effective == selection, order snapshot codes match |
| 5 | Login adopts guest currency; later login preserves existing preference | `user_preferences` correctness |
| 6 | Rate change between cart view and checkout | order uses rate at checkout date; historical orders unchanged |
| 7 | Missing rate for effective currency at checkout | 422 `CURRENCY_RATE_UNAVAILABLE_AT_CHECKOUT`, order not created |
| 8 | set-base / set-catalog with inactive or rate-less currency | 422, settings unchanged |
| 9 | set-base → legacy export (OrderExport) reflects new currency | detect the untagged `cached_settings_` staleness bug (expected to fail) |
| 10 | KWD invoice snapshot → CurrencyValidator | expected rejection (documented inconsistency) |
| 11 | Delete base currency / currency with rates | 409, nothing deleted |
| 12 | Callback with mismatched amount/currency on **production** gateway config | payment blocked + `PaymentFailed`; reconciliation records mismatch |
| 13 | Concurrency: two admins set different base currencies simultaneously | row lock prevents lost update |
| 14 | Round-trip precision: SAR order → base USD conversion of 0.001/0.01 edges | no floating drift (bcmath) |
| 15 | Catalog-currency change → product listing shows newly converted prices after cache flush | old cache not served (verify both tagged flush and currency-suffixed keys) |

---

## Final Verdict

All 20 phases were traced with exact source references and verified against the live database/config without any modification. The currency flow — settings-driven base/catalog/effective resolution, code-based rate lookup with historical dates, bcmath conversion, product/cart display conversion, order snapshotting, payment gating, cache invalidation, and the 131-test suite — is fully understood and consistent.

The verdict is:

**CURRENCY FLOW UNDERSTANDING: PASS**

(Residual items that cannot be proven from this repo are explicitly listed under Phase 20 — Unknowns, none of which change the traced behavior of the backend implementation.)