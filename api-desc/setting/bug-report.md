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

---

## BUG-SETTING-ADMIN-003: `update-settings` Permission Not Listed in Enums

**Severity:** Low

**Description:** Need to verify that `update-settings`, `view-fast-shipping`, and `update-fast-shipping` permissions exist in the `Permission` enum.
