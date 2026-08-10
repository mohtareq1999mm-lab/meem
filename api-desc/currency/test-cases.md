# Test Cases - Currency Feature

## Current Coverage

Dedicated feature tests live in `tests/Feature/Currency/`:

| File | Covers |
|------|--------|
| `CurrencyAdminApiTest.php` | CRUD + set-base for currencies (admin auth/permission) |
| `CurrencyRateTest.php` | CRUD + upsert for exchange rates |
| `CurrencyConversionTest.php` | Conversion logic, historical/latest rate lookup |
| `OrderCurrencyTest.php` | Order snapshot persistence + OrderResource |
| `CurrencyBugRegressionTest.php` | Regression coverage for BUG-001..004 |

## Recommended Tests

| # | Test | Type | Priority |
|---|------|------|----------|
| FT-001 | Create currency stores uppercased code | Feature | High |
| FT-002 | Create currency rejects duplicate code case-insensitively | Validation | High |
| FT-003 | Create currency rejects code not exactly 3 letters | Validation | High |
| FT-004 | Create currency rejects invalid decimal_places | Validation | Medium |
| FT-005 | Create currency persists translatable fields ({en, ar}) | Feature | High |
| FT-006 | Update currency persists changed metadata | Feature | High |
| FT-007 | Delete currency with no rates soft-deletes (deleted_at set) | Feature | High |
| FT-008 | Delete base currency returns 409 `CANNOT_DELETE_BASE_CURRENCY` | Guard | High |
| FT-009 | Delete currency referenced by rates returns 409 `CANNOT_DELETE_CURRENCY_IN_USE` | Guard | High |
| FT-010 | Set-base success updates settings options + flushes caches | Feature | High |
| FT-011 | Set-base inactive currency → 422 `CURRENCY_INACTIVE` | Validation | High |
| FT-012 | Set-base currency without rate ≤ today → 422 `EXCHANGE_RATE_NOT_FOUND` | Validation | High |
| FT-013 | Show currency resolves withTrashed (soft-deleted still viewable) | Edge | Medium |
| FT-014 | Show/delete non-numeric ID returns 404 (not 500) | Regression | High |
| FT-015 | List respects limit (default 15, max 100) | Pagination | Medium |
| FT-016 | Rate list filters by currency_id | Filter | Medium |
| FT-017 | Rate list filters by effective_date | Filter | Medium |
| FT-018 | Rate create/update upserts on (currency_id, effective_date) | Feature | High |
| FT-019 | Rate create rejects exchange_rate ≤ 0 | Validation | High |
| FT-020 | Rate create rejects non-existent currency_id | Validation | Medium |
| FT-021 | Rate delete hard-deletes | Feature | High |
| FT-022 | Same-currency conversion is identity with no rate query | Feature | High |
| FT-023 | Conversion uses latest rate with effective_date ≤ date | Feature | High |
| FT-024 | Conversion throws `CurrencyRateNotFoundException` when no rate | Failure | High |
| FT-025 | Conversion returns scale-6 ratio and round-2 converted total | Precision | High |
| FT-026 | Order snapshot stores all 5 currency columns | Feature | High |
| FT-027 | OrderResource exposes currency/base_currency/exchange_rate/converted_total | Structure | High |
| FT-028 | Update order after base change refreshes snapshot | Feature | Medium |
| FT-029 | Public list returns active currencies only, no auth | Feature | High |
| FT-030 | Public list is cached under `currencies` tag and invalidated on writes | Cache | High |
| FT-031 | Unauthenticated access to admin endpoints → 401 | Auth | High |
| FT-032 | Missing permission → 403 | Auth | High |

## Missing / Weak Areas

- **OrderSnapshot regression tests** currently fail because `OrderCreationService` requires NOT NULL `user_phone`/`user_email`/`address`/`name`; order-currency tests must supply them.
- **Historical rate coverage** for USD is incomplete (seed past-day USD rate before asserting historical/latest lookups).
- **Rate string precision** assertions are DB-dependent (SQLite returns `'1'`/`'0.221'` vs MySQL `'1.0000000000'`/`'0.2210000000'`); normalize via `bcmul($rate,'1',10)` or relax expectations.
