# Cart Module — Changelog (Authenticated API)

## [1.0.0] — 2026-07-20

### Added
- Comprehensive API investigation documentation (`api-desc/front/cart/`)
- 7 authenticated cart endpoints (CRUD + bulk items)
- CartInventoryService with pessimistic locking (`lockForUpdate()`) for inventory reservation
- 3-day reservation TTL with `expireCarts()` batch cleanup
- Shipping method split (SCHEDULED / FAST) in CartResource
- Promotion revalidation on cart mutations
- Gift item support via promotions (zero price, is_gift flag)
- Coupon-aware cart clearing with warning flow
- ProductPricingService integration for real-time price calculation

### Known Issues
1. **Duplicate `current_page` in index response** (BUG-CART-001)
2. **Manual pagination extraction** — not using standard resource pagination (BUG-CART-002)
3. **Generic error for auth vs not-found on item delete** (BUG-CART-003)
4. **Request cloning in bulk add** (BUG-CART-004)
5. **No tests** — zero test coverage for cart endpoints (BUG-CART-005)
6. **Aggressive promotion revalidation** resets all items (BUG-CART-006)
7. **Stranded inventory on coupon warning path** (BUG-CART-007)
