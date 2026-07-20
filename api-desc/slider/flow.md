# Request Flows — Slider Module

## Flow 1: List Sliders (Admin)

```
Client → GET /api/v1/sliders?active=1&page=1&limit=10
         ↓
    [auth:sanctum] middleware → authenticate token
         ↓
    [permission:view-slider] middleware → check Spatie permission
         ↓
    SliderController@index(Request)
         ↓
    Apply filters:
      - active → scopeActive() where('status', true)
      - order/sortedBy → orderBy
         ↓
    SliderRepository → paginate($limit)
         ↓
    SliderResource::collection($sliders) → transform (translated strings on index)
         ↓
    Return: { status:200, message, success:true, data: { data[], pagination_meta } }
```

## Flow 2: Create Slider (Admin)

```
Client → POST /api/v1/sliders (multipart/form-data)
         ↓
    [auth:sanctum] → [permission:create-slider]
         ↓
    SliderCreateRequest → validation (title.en/ar, images, products)
         ↓
    Fail? → 422 with field errors
         ↓
    SliderController@store($request)
         ↓
    SliderRepository::createSlider($request)
         ↓
    DB::transaction:
      1. Slider::create($data)  → saving event auto-generates slug
      2. Upload image_desktop → 'slider-image-desktop' collection
      3. Upload image_mobile → 'slider-image-mobile' collection
      4. Sync products if provided (slider_product pivot)
         ↓
    SliderResource::make($slider)
         ↓
    Return: { status:201, message, success:true, data }
```

## Flow 3: Show Slider (Admin)

```
Client → GET /api/v1/sliders/1
         ↓
    [auth:sanctum] → [permission:view-slider]
         ↓
    SliderController@show($id)
         ↓
    SliderRepository → findOrFail($id)
         ↓
    Found? → SliderResource::make($slider) → 200 (raw JSON with all locales)
    Not found or soft-deleted? → 404
```

## Flow 4: Update Slider (Admin)

```
Client → PUT /api/v1/sliders/1 (multipart/form-data)
         ↓
    [auth:sanctum] → [permission:update-slider]
         ↓
    SliderUpdateRequest → validation (all fields sometimes)
         ↓
    SliderController@update($request, $id)
         ↓
    SliderRepository::updateSlider($request, $id)
         ↓
    DB::transaction:
      1. findOrFail($id)
      2. Update slider fields → saving event re-generates slug if title changed
      3. If new image_desktop → updateSingleImage() [clears old + uploads new]
      4. If new image_mobile → updateSingleImage() [clears old + uploads new]
      5. Sync products if provided (replaces all)
         ↓
    SliderResource::make($slider)
         ↓
    Return: { status:200, message, success:true, data }
```

## Flow 5: Soft Delete Slider (Admin)

```
Client → DELETE /api/v1/sliders/1
         ↓
    [auth:sanctum] → [permission:delete-slider]
         ↓
    SliderController@destroy($id)
         ↓
    SliderRepository → findOrFail($id)
         ↓
    $slider->delete()  → soft delete (sets deleted_at)
         ↓
    MediaCleanupObserver@deleting → returns early (SoftDeletes guard)
         ↓
    Return: { status:200, message, success:true }
```

## Flow 6: Toggle Status (Admin)

```
Client → PATCH /api/v1/sliders/change-status (JSON: { id: 1 })
         ↓
    [auth:sanctum] → [permission:update-slider]
         ↓
    SliderController@changeStatus(Request)
         ↓
    SliderRepository::changeStatus($id)
         ↓
    findOrFail($id) → toggle status → save
         ↓
    Return: { status:200, message, success:true, data: { id, status } }
```

## Flow 7: Reorder Sliders (Admin)

```
Client → PUT /api/v1/sliders/reorder (JSON: { sliders: [3, 1, 2] })
         ↓
    [auth:sanctum] → [permission:update-slider]
         ↓
    SliderController@reorder(Request)
         ↓
    SliderRepository::reorder($sliders)
         ↓
    $this->setNewOrder($sliders)  → Spatie Sortable updates order column
         ↓
    Return: { status:200, message, success:true }
```

## Flow 8: List Active Sliders (Public)

```
Client → GET /api/v1/general/sliders
         ↓
    SliderController@index()  [no auth]
         ↓
    If slug query param → delegate to getSliderBySlug()
         ↓
    SliderService::getSliders($request)
         ↓
    Slider::active()
      ├─ where('status', true)
      ├─ optional date/ID filters
      ├─ orderBy('id', 'desc')
      └─ limit (default 50)
         ↓
    SliderResource::collection($sliders)
         ↓
    Return: { status:200, message, success:true, data[] }
```

## Flow 9: Get Slider by Slug (Public)

```
Client → GET /api/v1/general/sliders/summer-sale
         ↓
    SliderController@getSliderBySlug($slug)  [no auth]
         ↓
    SliderService::getSliderBySlug($slug)
         ↓
    Slider::active()
      ├─ where('slug', $slug)
      └─ first()
         ↓
    If found:
      ├─ load products with channel filter (HasChannelFilter)
      └─ enrich with pricing
         ↓
    SliderResource::make($slider) → 200
         ↓
    Not found → Return: { status:404, message, success:false }
```

## Flow 10: Product Filter by Slider

```
Client → GET /api/v1/products?slider=summer-sale
         ↓
    ProductController@index(Request)
         ↓
    If slider param present:
      ├─ products_query->whereHas('sliders', fn($q) => $q->where('slug', $sliderSlug))
      └─ filters products belonging to that slider
         ↓
    Paginated product list
```
