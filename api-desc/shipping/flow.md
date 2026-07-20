# Data Flow - Shipping Feature

## Flow: List Governorates (index)

```
Admin Client
  |
  GET /api/v1/governorates?country_id=1&status=1&search=cairo&per_page=15
  Authorization: Bearer <token>
  |
  v
auth:sanctum middleware
  |
  v
permission:VIEW_GOVERNORATE middleware
  |
  v
GovernorateController@index($request)
  |
  +-- GovernorateRepository::paginate(15, 'cairo', true, 1)
  |     +-- Governorate::query()
  |     +-- applySearch(): LOWER(name->"$.en") LIKE '%cairo%' OR LOWER(name->"$.ar") LIKE '%cairo%'
  |     +-- where('status', true)
  |     +-- where('country_id', 1)
  |     +-- orderByDesc('id')
  |     +-- paginate(15)
  |
  v
GovernorateResource::collection($paginator)
  |  -- each item: id, name (translated), status, is_fast_shipping_enabled
  |
  v
JSON Response
```

## Flow: Create Governorate with Shipping Price

```
POST /api/v1/governorates
Body: { country_id: 1, name: {...}, shipping_price: { price: 50, estimated_days: 3 } }
  |
  v
permission:CREATE_GOVERNORATE middleware
  |
  v
GovernorateStoreRequest validation
  |  -- country_id exists:countries,id
  |  -- name.* required, UniqueTranslationRule
  |
  v
GovernorateRepository::create($data)
  |  -- ensureCountryExists(1)
  |  -- DB::transaction:
  |        INSERT governorates (...)
  |        INSERT shipping_prices (governorate_id, price, estimated_days)
  |
  v
GovernorateResource::make($governorate)
  |
  v
201 Created
```

## Flow: Toggle Fast Shipping

```
PUT /api/v1/governorates/5/fast-shipping
Body: { is_fast_shipping_enabled: true }
  |
  v
GovernorateController@toggleFastShipping($request, 5)
  |  -- findById(5) || 404
  |  -- $request->validate(['is_fast_shipping_enabled' => 'required|boolean'])
  |  -- GovernorateRepository::update($gov, ['is_fast_shipping_enabled' => true])
  |
  v
200 + GovernorateResource
```
