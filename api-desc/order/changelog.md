# Changelog - Order Feature

## [Unreleased]

### Added
- Admin order list endpoint `GET /api/v1/orders` with 10 filter parameters
- Admin order detail endpoint `GET /api/v1/orders/{id}` with conditional full detail response
- Permission-based access control (`VIEW_ORDERS` / `VIEW_ORDER`)
- Pagination with configurable limit (default 15, max 100)
- `OrderCollection` + `OrderResource` API resource classes
- 5 eager-loaded relations (user, items/products, variants, transactions, pickup location)
- Dual resolution for detail endpoint (ID or tracking number)

### Known Issues
- No explicit `orderBy` on list query
- Promotion name filter uses unindexed LIKE subquery
