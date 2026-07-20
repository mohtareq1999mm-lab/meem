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
