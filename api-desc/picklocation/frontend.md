# Frontend - Pickup Location Feature

## Status

Admin SPA manages pickup locations. Checkout SPA selects them during fulfillment.

## Consumption (Admin)

```javascript
export const pickupLocationApi = {
  list(params)        // GET /api/v1/pickup-locations?search=&active=&inactive=&per_page=
  create(data)        // POST /api/v1/pickup-locations
  show(id)            // GET /api/v1/pickup-locations/{id}
  update(id, data)    // PUT /api/v1/pickup-locations/{id}
  delete(id)          // DELETE /api/v1/pickup-locations/{id}
}
```

### Update Request Body (all optional)

| Field | Type | Example |
|-------|------|---------|
| `store_name` | string | `"Downtown Branch"` |
| `address` | string | `"123 Main St"` |
| `phone` | string | `"01000000001"` |
| `email` | string (nullable) | `"branch@example.com"` |
| `latitude` | string (nullable) | `"30.0444"` |
| `longitude` | string (nullable) | `"31.2357"` |
| `working_hours` | array | `[{ "day": { "ar": "الاثنين", "en": "Monday" }, "open": "09:00", "close": "21:00" }]` |
| `status` | bool | `true` |
| `display_order` | int | `1` |

**Note:** `working_hours.*.day` uses translatable keys: `day.ar` (Arabic) and `day.en` (English), both strings.

## Consumption (Public/Checkout)

```javascript
// No auth required
export const publicPickupApi = {
  list()              // GET /api/v1/general/pickup-locations
  show(id)            // GET /api/v1/general/pickup-locations/{id}
}
```

## Expected Frontend Components

```
PickupLocationsList.vue   → admin list with search, active/inactive filter
PickupLocationForm.vue    → admin create/edit (store_name, address, phone, hours, map)
PickupLocationSelector.vue → public dropdown for checkout (active only)
```
