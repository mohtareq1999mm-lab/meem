# Frontend - Order Feature

## Status

Admin SPA consumes these endpoints for order management screens.

## Consumption

```javascript
export const orderApi = {
  list(params)       // GET /api/v1/orders?status=&user_id=&search=&page=&limit=...
  show(id)           // GET /api/v1/orders/{id}
}
```

## Expected Frontend Components

```
OrdersTable.vue         → index   (data table, filters, pagination)
OrderDetailPage.vue     → show    (full order detail, items, transactions)
OrderFilters.vue        → index   (status, date range, search, user, product)
```

## Filter UI Mapping

| Query Param | Frontend Component |
|-------------|-------------------|
| `status` | Dropdown select |
| `search` | Search input (name/email/phone) |
| `user_email` | Email field |
| `created_from` / `created_to` | Date range picker |
| `product_name` | Product autocomplete |
| `promotion_name` | Promotion autocomplete |
| `shipping_method` | Dropdown select |
