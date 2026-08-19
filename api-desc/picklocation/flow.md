# Data Flow - Pickup Location Feature

## Flow: Admin List with Search

```
Admin Client
  |
  GET /api/v1/pickup-locations?search=Downtown&active=true&per_page=15
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware
  |
  v
permission:VIEW_PICKUP_LOCATIONS middleware
  |
  v
PickupLocationController@index($request)
  |  -- $limit = 15
  |  -- $search = 'Downtown'
  |
  +-- PickupLocationRepository::orderBy('display_order')->orderBy('id')
  +-- where('store_name', 'like', '%Downtown%')
  +-- where('status', true)
  +-- paginate(15)
  |
  v
PickupLocationResource::collection($paginator)
  |
  +-- Manual extraction of pagination meta
  |     response()->getData(true)
  |     -> data['data'], data['meta'], data['links']
  |
  v
JSON Response (with duplicate page/current_page keys)
```

## Flow: Order Checkout with Pickup

```
Customer
  |
  POST /api/v1/general/checkout
  Body: { fulfillment_type: 'pickup', pickup_location_id: 1, ... }
  |
  v
[Checkout Service]
  |  -- Reads PickupLocation (even if soft-deleted)
  |  -- Snapshots: store_name, address, phone, coordinates
  |  -- Stores: pickup_location_id + snapshot columns on orders table
  |
  v
Order created with pickup snapshot
```

## Flow: Delete Pickup Location

```
Admin Client
  |
  DELETE /api/v1/pickup-locations/5
  |
  v
permission:DELETE_PICKUP_LOCATION
  |
  v
PickupLocationController@destroy(5)
  |  -- repository->findOrFail(5)
  |  -- $location->delete()  (soft delete)
  |
  v
[Model deleted hook]
  |  -- was the deleted location the default (is_default = true)?
  |  -- yes → promote remaining location with lowest id
  |         UPDATE pickup_locations SET is_default = 1 WHERE id = (lowest remaining id)
  |
  v
200 + message
  -- Existing orders retain snapshot data
```

## Flow: Switching the Default Location

```
Admin Client
  |
  PUT /api/v1/pickup-locations/9
  Body: { "is_default": true }
  |
  v
permission:UPDATE_PICKUP_LOCATION
  |
  v
PickupLocationController@update(9)
  |  -- $location->update({ is_default: true })
  |
  v
[Model saving hook]
  |  -- is_default dirty & true?
  |  -- yes → single atomic UPDATE clears flag on all other rows
  |         UPDATE pickup_locations SET is_default = 0 WHERE is_default = 1 AND id <> 9
  |
  v
Location 9 persisted as the only default
  |
  v
Controller flushes PICKUP_LOCATIONS cache tag
  |
  v
200 + updated resource (is_default: true)
```

Updating other fields of the current default does NOT clear its flag (hook only runs when `is_default` itself changes).
