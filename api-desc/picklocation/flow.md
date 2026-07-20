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
200 + message
  -- Existing orders retain snapshot data
```
