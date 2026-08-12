# Request Flows — Settings Module (Admin API)

## Flow 1: GET /settings — Success

```
Client → GET /api/v1/settings   (auth: sanctum + permission:view-settings)
         ↓
    [auth:sanctum, throttle:admin] middleware group
         ↓
    SettingsController@index
         ↓
    Settings::first()
         ↓
    HasCache::remember('settings', md5(fullUrl), $settings, 4h)
         ├── Cache HIT → return cached
         └── Cache MISS → store & return
         ↓
    SettingResource::make($setting)   [includes top-level currency_selection_enabled]
         ↓
    Response: 200 with settings object
```

> **Public variant:** `GET /api/v1/general/settings` (no auth) → `App\Http\Controllers\Api\General\SettingController@index` → `SettingService::getSetting()` → same `SettingResource` shape, cached under the `settings` tag.

## Flow 2: PUT /settings — Update

```
Client → PUT /api/v1/settings (auth: sanctum + permission:update-settings)
         ↓
    SettingsRequest validation   [currency_selection_enabled => sometimes|boolean]
         ↓
    SettingsController@update
         ↓
    $settings = Settings::first()
         ↓
    $data = $request->only([...all fields..., 'options', 'minimum_order_amount'])
         ↓
    if (request has currency_selection_enabled):
         options = array_merge(existing options, request options)
         options['currency_selection_enabled'] = request->boolean('currency_selection_enabled')
         data['options'] = options
         ↓
    $settings->update($data)
         ↓
    if (request has currency_selection_enabled):
         app(CurrencyService::class)->forgetEffectiveCode()
         ↓
    flushTag(FrontendResource::SETTINGS->value)        [settings cache cleared]
         ↓
    $settings = Settings::first()  (fresh)
    SettingResource::make($settings)
         ↓
    Response: 200 "Settings updated successfully"
```

## Flow 3: GET /fast-shipping/settings

```
Client → GET /api/v1/fast-shipping/settings (auth: sanctum + permission:view-fast-shipping)
         ↓
    FastShippingController@getSettings
         ↓
    FastShippingRepository@getSettings
         ↓
    Cache::remember('fast_shipping_settings', 3600, ...)
         ├── Cache HIT → return cached
         └── Cache MISS → Settings::first() → data_get(options, 'fast_shipping', defaults)
         ↓
    Response: 200 { enabled, duration_minutes, fee, start_hour, end_hour }
```

## Flow 4: PUT /fast-shipping/settings

```
Client → PUT /api/v1/fast-shipping/settings (auth: sanctum + permission:update-fast-shipping)
         ↓
    Inline validation (enabled, duration_minutes, fee, start_hour, end_hour)
         ↓
    FastShippingController@updateSettings
         ↓
    FastShippingRepository@updateSettings
         ↓
    DB::transaction:
        Settings::lockForUpdate()->first()
        Merge data into $options['fast_shipping']
        $settings->update(['options' => $options])
         ↓
    Cache::forget('fast_shipping_settings')
         ↓
    Response: 200 "Fast shipping settings updated successfully"
```
