# Request Flows — Settings Module (Admin API)

## Flow 1: GET /settings — Success

```
Client → GET /api/v1/settings
         ↓
    [api] middleware group
         ↓
    SettingsController@index
         ↓
    Settings::first()
         ↓
    SettingResource::make($setting)
         ↓
    Response: 200 with settings object
```

## Flow 2: PUT /settings — Update

```
Client → PUT /api/v1/settings (auth: sanctum + permission:update-settings)
         ↓
    SettingsRequest validation
         ↓
    SettingsController@update
         ↓
    $settings = Settings::first()
         ↓
    $settings->fill($request->only([...all fields...]))
    $settings->save()
         ↓
    SettingResource::make($settings->fresh())
         ↓
    Response: 200
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
