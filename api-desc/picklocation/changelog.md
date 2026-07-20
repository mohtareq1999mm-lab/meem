# Changelog - Pickup Location Feature

## [Unreleased]

### Added
- Admin CRUD: 5 endpoints for pickup location management
- Public API: 2 endpoints for active-only pickup locations
- SoftDeletes for safe deletion
- Working hours support (JSON array with day/open/close)
- Display ordering for custom sort
- Pickup location snapshot on order at checkout
- 4 Spatie permissions for granular access control
- Full translation support (EN + AR)
- 58+ test methods covering CRUD, validation, authorization, checkout integration

### Known Issues
- Pagination meta has duplicate `page`/`current_page` keys in admin list response
