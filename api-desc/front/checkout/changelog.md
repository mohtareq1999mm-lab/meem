# Checkout Module — Changelog

## [1.0.0] — 2026-07-20

### Added
- Comprehensive API investigation documentation (`api-desc/front/checkout/`)
- 7 checkout endpoints (promotions, place order, COD/cashier mark-paid, QR, callbacks)
- 3 payment methods: online (gateway redirect), COD, pay at cashier (QR code)
- Real-time price recalculation at checkout (flash sales, promotions, coupons)
- Pricing snapshot immutability (order stores all pricing at checkout time)
- Atomic inventory finalization with pessimistic locking
- Payment gateway callback handling with amount/currency mismatch detection
- Order status state machine with allowed transitions
- Coupon usage recording (firstOrCreate + assignment consumption)
- Extensive existing test coverage

### Known Issues
1. **Duplicated callback logic** — ~230 lines shared (BUG-CHK-002)
2. **No cart vs empty cart** — Same 400 response (BUG-CHK-001)
3. **FAST items not locked** — Only SCHEDULED items (BUG-CHK-003)
4. **Locale lost on callback** — Uses app()->getLocale() (BUG-CHK-006)
5. **FAST prices not refreshed** — Only SCHEDULED in scope (BUG-CHK-007)
6. **Hardcoded status transitions** (BUG-CHK-005)
7. **Deleted order with valid transaction** — 404 instead of 500 (BUG-CHK-004)
