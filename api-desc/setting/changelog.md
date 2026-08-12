# Settings Module — Changelog (Admin API)

## [1.1.0] — 2026-08-12

### Changed
- `SettingResource` now exposes a top-level `currency_selection_enabled` boolean (`options.currency_selection_enabled`, default `false`).
- `PUT /api/v1/settings` accepts `currency_selection_enabled` (`sometimes|boolean`); when sent it is merged into `options` (preserving other keys), resets the `CurrencyService` effective-currency memo, and flushes the `settings` cache tag.
- Admin settings GET/PUT auth documentation corrected: `GET /api/v1/settings` requires `auth:sanctum` + `view-settings`; public reads use `GET /api/v1/general/settings`.
- `SettingSeeder` seeds `currency_selection_enabled ??= false`.

## [1.0.0] — 2026-07-21

### Added
- Admin API investigation documentation (`api-desc/setting/`)
- Settings endpoints: GET + PUT `/api/v1/settings`
- Fast shipping settings endpoints: GET + PUT `/api/v1/fast-shipping/settings`
- Fast shipping config cached (1 hour TTL) with cache invalidation on update
- Transaction-based update with `lockForUpdate()` to prevent race conditions
- `minimumOrderAmount` exposed as top-level field in SettingResource
- `minimumOrderAmount` enforced in CheckoutRepository (400 if cart total < minimum)
