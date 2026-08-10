# Frontend - Currency Feature

## Status

Admin SPA + storefront consume these endpoints for currency/rate management and price display.

## Consumption

```javascript
// Admin
export const currencyApi = {
  list(params)        // GET /api/v1/currencies?limit=&page=
  show(id)            // GET /api/v1/currencies/{id}
  create(payload)     // POST /api/v1/currencies
  update(id, payload) // PUT /api/v1/currencies/{id}
  remove(id)          // DELETE /api/v1/currencies/{id}
  setBase(id)         // POST /api/v1/currencies/{id}/set-base
}

export const currencyRateApi = {
  list(params)            // GET /api/v1/currency-rates?currency_id=&effective_date=&limit=
  show(id)                // GET /api/v1/currency-rates/{id}
  create(payload)         // POST /api/v1/currency-rates
  update(id, exchange_rate) // PUT /api/v1/currency-rates/{id}
  remove(id)              // DELETE /api/v1/currency-rates/{id}
}

// Storefront
export const publicCurrencyApi = {
  list()                  // GET /api/v1/general/currencies
}
```

## Expected Frontend Components

```
CurrenciesTable.vue      → index    (table, search, pagination)
CurrencyFormDialog.vue   → create/update (translatable name/symbol/country_name)
CurrencyDetailDrawer.vue → show    (base/catalog flags)
SetBaseCurrencyDialog.vue → setBase (confirmation)
ExchangeRatesTable.vue   → index    (filter by currency, date)
ExchangeRateFormDialog.vue → create/update
```

## UI Mapping

| UI Concept | API Field / Endpoint |
|------------|---------------------|
| Currency code input | `code` (3 letters, uppercased) |
| Name / Symbol / Country | `name`, `symbol`, `country_name` (locale objects `{en, ar}`) |
| Decimals selector | `decimal_places` (0–4) |
| Active toggle | `is_active` |
| Base currency badge | `is_base` in `CurrencyResource` |
| Catalog currency badge | `is_catalog` in `CurrencyResource` |
| Exchange rate form | `currency_id`, `exchange_rate`, `effective_date` |
| Base currency switch | `POST /currencies/{id}/set-base` (shows 422 error for inactive/no-rate) |
| Currency delete | 409 with `CANNOT_DELETE_BASE_CURRENCY` or `CANNOT_DELETE_CURRENCY_IN_USE` |

## Notes

- `code` is stored uppercase; server uppercases it during validation.
- `is_base` / `is_catalog` are computed against current settings, not stored flags.
- Storefront currency selector uses the cached `GET /api/v1/general/currencies` list (active only).
