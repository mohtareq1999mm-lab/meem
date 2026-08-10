# Changelog - Currency Feature

## [Unreleased]

### Added
- `currencies` table (translatable, soft-deletes, unique code) + `currency_rates` table (unique per currency/day, indexed date).
- Admin currency CRUD endpoints (`GET/POST/PUT/DELETE /api/v1/currencies`) with permission gating.
- Admin exchange-rate CRUD endpoints (`/api/v1/currency-rates`) with upsert-on-same-day behavior.
- Set-base-currency endpoint `POST /api/v1/currencies/{id}/set-base`.
- Public read-only endpoint `GET /api/v1/general/currencies` (active only, tag-cached 4h).
- `CurrencyService` (singleton), `CurrencyConversionService` (bcmath scale 6), `CurrencyRateService`.
- `CurrencyConversionResult` DTO + `CurrencyRateNotFoundException`, `CurrencyInactiveException`, `CurrencyInUseException`.
- Currency snapshot columns on `orders` (currency_code, base_currency_code, currency_rate, currency_rate_date, converted_total_price) with migration backfill.
- Order snapshot logic in `OrderCreationService`; currency fields exposed in `OrderResource`.
- Product price conversion trait `ConvertsProductPrice` (catalog → base).
- `CurrencyResource` / `CurrencyRateResource` API resources.
- 4 form requests with validation (incl. unique translation for translatable fields).
- 8 new Spatie permissions (`view-currencies` ... `set-base-currency`).
- `CurrencySeeder` (6 currencies with today's rates).
- Translation keys (en/ar) for all currency messages/errors.
- Full feature test suite under `tests/Feature/Currency`.

### Changed
- `AppServiceProvider` registers `CurrencyService` as singleton.
- `FrontendResource` enum gains `CURRENCIES` case.
- Constants file defines currency message/error keys.

### Fixed
- Route numeric constraints (`whereNumber('currency')`, `whereNumber('currency_rate')`) so non-numeric IDs return 404 instead of a 500 TypeError.
- Delete guard ordering: base-currency check runs before the rates-exists check (base currency without rates is still protected).
- SQLite-safe date comparisons via `whereDate()` in rate resolution and base-set validation.

### Known Issues
- Rate resolution returns DB-native string precision (`'1'`, `'0.221'`) on SQLite vs padded `'1.0000000000'` on MySQL — string-format assertions are DB-dependent.
