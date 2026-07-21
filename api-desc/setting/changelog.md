# Settings Module — Changelog (Admin API)

## [1.0.0] — 2026-07-21

### Added
- Admin API investigation documentation (`api-desc/setting/`)
- Settings endpoints: GET + PUT `/api/v1/settings`
- Fast shipping settings endpoints: GET + PUT `/api/v1/fast-shipping/settings`
- Fast shipping config cached (1 hour TTL) with cache invalidation on update
- Transaction-based update with `lockForUpdate()` to prevent race conditions
- `minimumOrderAmount` exposed as top-level field in SettingResource
- `minimumOrderAmount` enforced in CheckoutRepository (400 if cart total < minimum)
