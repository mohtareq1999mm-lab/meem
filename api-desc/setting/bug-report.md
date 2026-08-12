# Bug Report — Settings Module (Admin API)

---

## BUG-SETTING-ADMIN-001: No Tests for Admin Settings Endpoints

**Severity:** Medium

**Description:** No feature tests exist for `PUT /api/v1/settings`, `GET /api/v1/fast-shipping/settings`, or `PUT /api/v1/fast-shipping/settings`.

---

## BUG-SETTING-ADMIN-002: `PUT /api/v1/settings` Replaces Entire `options` JSON

**Severity:** Medium

**Component:** `packages/marvel/src/Http/Controllers/SettingsController.php` (line 110)

**Description:** `$settings->fill($request->only('options'))` replaces the entire `options` JSON. If the frontend sends only `{"options": {"minimumOrderAmount": 100}}`, all other options (like `fast_shipping`) are lost.

**Suggested Fix:** Merge new options into existing:
```php
$existingOptions = $settings->options ?? [];
$newOptions = $request->input('options', []);
$settings->options = array_merge($existingOptions, $newOptions);
```

Compare with `FastShippingRepository::updateSettings()` which correctly uses:
```php
$options[self::SETTINGS_KEY] = array_merge($this->defaults(), $data);
```

**Status:** **PARTIALLY RESOLVED.** The wholesale-replace behavior remains when the client sends a full `options` object, but the `currency_selection_enabled` flag added by the currency feature is now **merged** into `options` (it does not drop other keys). Any future fix should extend the same merge approach to the whole `options` payload.

---

## BUG-SETTING-ADMIN-004: Admin `GET /api/v1/settings` Auth Status

**Severity:** Low

**Component:** `packages/marvel/src/Rest/Routes.php` (lines 114-115)

**Description:** Docs previously described `GET /api/v1/settings` as public. The route actually sits inside the `auth:sanctum` + `throttle:admin` group, so unauthenticated callers receive 401.

**Status:** Documentation corrected (no code change). Consumers should use the public `GET /api/v1/general/settings` endpoint (`settings.front`).

---

## BUG-SETTING-ADMIN-005: `currency_selection_enabled` Response Exposure

**Severity:** Low

**Component:** `packages/marvel/src/Http/Resources/SettingResource.php`, `packages/marvel/src/Http/Requests/SettingsRequest.php`

**Description:** The `currency_selection_enabled` option was stored in settings but not exposed in the settings API response nor accepted on `PUT /api/v1/settings`.

**Status:** **RESOLVED.** `SettingResource` now returns top-level `currency_selection_enabled` (bool); `SettingsRequest` accepts `currency_selection_enabled => sometimes|boolean`; `SettingsController::update()` merges it into `options`, resets the effective-currency memo and flushes the `settings` tag.

---

## BUG-SETTING-ADMIN-003: `update-settings` Permission Not Listed in Enums

**Severity:** Low

**Description:** Need to verify that `update-settings`, `view-fast-shipping`, and `update-fast-shipping` permissions exist in the `Permission` enum.
