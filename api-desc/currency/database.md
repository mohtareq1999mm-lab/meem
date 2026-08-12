# Database - Currency Feature

## Table: `currencies`

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| code | varchar(3) | | NOT NULL, UNIQUE (`currencies_code_unique`) | Uppercased ISO code |
| name | json | | NOT NULL | Translatable `{"en":..., "ar":...}` |
| symbol | json | NULL | | Translatable, nullable |
| country_name | json | NULL | | Translatable, nullable |
| numeric_code | varchar(3) | NULL | | e.g. 840 |
| decimal_places | unsignedTinyInteger | 2 | | 0–4 enforced at validation |
| icon | varchar | NULL | | |
| is_active | boolean | 1 | Index (`currencies_is_active_index`) | |
| sort_order | integer | 0 | | |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |
| deleted_at | timestamp | NULL | | Soft deletes |

### Translatable Columns
- `name`, `symbol`, `country_name` — JSON via Spatie `HasTranslations`.

### Soft Deletes
- `delete()` sets `deleted_at`; admin `show`/`update` use `withTrashed()`.
- `currency_rates` FK uses `cascadeOnDelete` — on **force** delete rates are removed; soft delete keeps them.

---

## Table: `currency_rates`

| Column | Type | Default | Constraints | Notes |
|--------|------|---------|-------------|-------|
| id | bigint unsigned | AUTO_INCREMENT | PK | |
| currency_id | bigint unsigned | | FK → currencies.id ON DELETE CASCADE | |
| exchange_rate | decimal(20,10) | | NOT NULL | Stored as string, 10 dp |
| effective_date | date | | NOT NULL | Rate applies from this day |
| created_at | timestamp | NULL | | |
| updated_at | timestamp | NULL | | |

### Indexes
- Unique: `(currency_id, effective_date)` — `currency_rates_currency_date_unique` (one rate per currency per day)
- Index: `effective_date` — `currency_rates_effective_date_index`

### Foreign Keys
- `currency_id` → `currencies.id` ON DELETE CASCADE

### Query Pattern (rate resolution)
```sql
SELECT exchange_rate FROM currency_rates
JOIN currencies ON currencies.id = currency_rates.currency_id
WHERE currencies.code = ?          -- currency code lookup (whereHas)
  AND DATE(currency_rates.effective_date) <= ?   -- whereDate
ORDER BY currency_rates.effective_date DESC
LIMIT 1
```

---

## Table: `orders` (currency snapshot columns)

Added by `2026_08_10_000004_add_currency_columns_to_orders_table.php` and `2026_08_11_000001_add_catalog_currency_code_to_orders_table.php`:

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| currency_code | varchar(3) | YES | **Base** currency at order time (== base_currency_code) |
| base_currency_code | varchar(3) | YES | Base currency at order time |
| catalog_currency_code | varchar(3) | YES | Catalog currency snapshot at order time (new) |
| currency_rate | decimal(20,10) | YES | Catalog→base ratio (target/source, 6dp computed) |
| currency_rate_date | date | YES | Effective date used |
| total_price | decimal(10,3) | YES | **Base-amount** total (converted) |
| converted_total_price | decimal(10,3) | YES | Converted base amount (kept for backward compat) |

Backfills on migrate: `converted_total_price = total_price` where null; `catalog_currency_code = currency_code`.

Model casts (`Order`): `currency_rate => string`, `currency_rate_date => date`, `converted_total_price => float`. `catalog_currency_code` added to `$fillable`.

---

## Related Tables

| Table | Relation | Column |
|-------|----------|--------|
| `settings` | options JSON | `base_currency_code`, `catalog_currency_code`, `currency` |
| `orders` | snapshot | currency snapshot columns (above) |
| `products` | price conversion | via `ConvertsProductPrice` (no DB column) |

---

## Seeder — `database/seeders/CurrencySeeder.php`

Seeds 6 currencies + today's rate via `firstOrCreate` (idempotent):

| Code | Rate |
|------|------|
| USD | 1.0000000000 |
| KWD | 0.2210000000 |
| SAR | 3.7500000000 |
| AED | 3.6725000000 |
| EUR | 0.9990000000 |
| GBP | 0.8600000000 |

---

## Migration Files

| File | Purpose |
|------|---------|
| `2026_08_10_000002_create_currencies_table.php` | `currencies` table |
| `2026_08_10_000003_create_currency_rates_table.php` | `currency_rates` table |
| `2026_08_10_000004_add_currency_columns_to_orders_table.php` | order snapshot columns + backfill |
| `2026_08_11_000001_add_catalog_currency_code_to_orders_table.php` | `catalog_currency_code` on orders + backfill from `currency_code` |

---

## Performance Notes

- Rate resolution query covered by unique `(currency_id, effective_date)` + `effective_date` index.
- Latest-rate lookup uses `whereDate(...) <= date` + `orderByDesc(effective_date)` — the index on `effective_date` serves both filter and sort.
- Public currency list is cached (tag `currencies`, 4h), avoiding repeated reads.
- `CurrencyService` holds an in-memory rate cache keyed by `code|date` to avoid duplicate queries within a request.
