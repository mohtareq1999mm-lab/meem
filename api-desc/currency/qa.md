# QA - Currency Feature

## Test Matrix

### Currencies (Admin)

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-CUR-001 | List returns paginated data array | data[] + links{} |
| TC-CUR-002 | List respects limit param | Correct count |
| TC-CUR-003 | Create currency with valid data | 200 + `CURRENCY_CREATED_SUCCESSFULLY` |
| TC-CUR-004 | Create rejects duplicate code (case-insensitive) | 422 |
| TC-CUR-005 | Create rejects invalid code length/format (must be 3 letters) | 422 |
| TC-CUR-006 | Create uppercases lowercase code | Stored as uppercase |
| TC-CUR-007 | Create rejects invalid decimal_places (< 0 or > 4) | 422 |
| TC-CUR-008 | Create stores translatable name/symbol/country_name | {en, ar} persisted |
| TC-CUR-009 | Update currency metadata | 200 + `CURRENCY_UPDATED_SUCCESSFULLY` |
| TC-CUR-010 | Show currency by numeric ID | 200 + data |
| TC-CUR-011 | Show returns 404 for non-numeric / missing ID | 404 |
| TC-CUR-012 | Delete currency with no rates | 200 soft delete (`deleted_at` set) |
| TC-CUR-013 | Delete base currency | 409 `CANNOT_DELETE_BASE_CURRENCY` |
| TC-CUR-014 | Delete currency referenced by rates | 409 `CANNOT_DELETE_CURRENCY_IN_USE` |
| TC-CUR-015 | Set base to active currency with rate ≤ today | 200 `SET_BASE_CURRENCY_SUCCESSFULLY` |
| TC-CUR-016 | Set base to inactive currency | 422 `CURRENCY_INACTIVE` |
| TC-CUR-017 | Set base to currency without a rate ≤ today | 422 `EXCHANGE_RATE_NOT_FOUND` |
| TC-CUR-018 | Unauthenticated | 401 (all endpoints) |
| TC-CUR-019 | Forbidden without permission | 403 |

### Exchange Rates (Admin)

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-RATE-001 | List rates with pagination | data[] |
| TC-RATE-002 | List filters by `currency_id` | Only matching currency |
| TC-RATE-003 | List filters by `effective_date` | Only matching date |
| TC-RATE-004 | Create rate for currency/day | 200, row created |
| TC-RATE-005 | Post same rate for same currency/day (upsert) | Row updated, no duplicate |
| TC-RATE-006 | Create rate with exchange_rate ≤ 0 | 422 |
| TC-RATE-007 | Create rate with non-existent currency_id | 422 |
| TC-RATE-008 | Update rate `exchange_rate` | 200 `CURRENCY_RATE_UPDATED_SUCCESSFULLY` |
| TC-RATE-009 | Delete rate | Hard delete |
| TC-RATE-010 | Show/missing rate | 200 / 404 |

### Conversion Logic

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-CONV-001 | Convert same currency → identity | Amount unchanged, no DB query |
| TC-CONV-002 | Convert using latest rate on/before date | Correct rate chosen |
| TC-CONV-003 | Convert with no historical rate | `CurrencyRateNotFoundException` |
| TC-CONV-004 | Conversion precision (bcmath scale 6, round 2) | `0.221` ratio, `22.10` total |
| TC-CONV-005 | Latest-wins when multiple rates for same date | Most recent `effective_date` used |

### Order Snapshot

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-ORD-001 | Order stores currency_code / base_currency_code | Persisted |
| TC-ORD-002 | Order stores currency_rate (ratio) | e.g. `0.221000` |
| TC-ORD-003 | Order stores currency_rate_date | Effective date used |
| TC-ORD-004 | Order stores converted_total_price | e.g. `22.10` |
| TC-ORD-005 | OrderResource exposes currency/base_currency/exchange_rate/converted_total | Present |
| TC-ORD-006 | Update order after base change refreshes snapshot | New snapshot values |

### Public List

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-PUB-001 | Public list returns only active currencies | No inactive rows |
| TC-PUB-002 | Public list requires no auth | 200 |
| TC-PUB-003 | Public list cached under `currencies` tag | Cache hit on repeat call |
| TC-PUB-004 | Cache invalidated on currency/rate write | Fresh data on next call |

## Manual Test Checklist

- [ ] Verify delete guard order: base currency check happens before rates check
- [ ] Verify code is stored uppercase regardless of input casing
- [ ] Verify rate upsert: two posts same currency/day → one row, updated value
- [ ] Verify decimal precision: `0.2210000000` stored, `0.221000` ratio, `22.10` converted total
- [ ] Verify historical lookup picks rate `<=` the requested date (not the newest row globally)
- [ ] Verify set-base 422 messages shown when currency inactive or has no rate ≤ today
- [ ] Verify storefront list only shows active currencies, ordered by sort_order then code
- [ ] Verify price caches flushed when base currency changes or a rate is written
- [ ] Verify 409 delete errors return distinct codes/messages for base vs in-use currency
