# Changelog - Order Feature

## [Unreleased]

### Fixed
- **HIGH:** `GET /api/v1/general/orders?status={status}` now correctly filters by order status. The `status` query parameter was previously completely ignored — all orders were returned regardless of the specified status.
### Added
- Complete order lifecycle management: checkout → payment → fulfillment → delivery
- Customer endpoints: list my orders, checkout, view order details
- Admin endpoints: list all orders, manage status, export, invoice download
- Checkout with 3 payment methods: COD, online, pay-at-cashier
- Event-driven architecture with 11 events and 10 queued listeners
- Price snapshot system for order items (immutable at time of order)
- Auto-generated order numbers (`ORD-{id}`) and tracking numbers
- Payment gateway integration: 14 gateway types
- Fulfillment types: delivery and pickup
- Shipping methods: scheduled and fast
- Order export via Laravel Excel
- Token-based invoice download with expiry
- Inventory management: lock on checkout, restore on refund/cancellation
- Coupon and promotion integration with discount stacking
- Status transition validation (pending → processing → completed)
- Broadcasting via private channels for real-time updates

### Infrastructure
- Order, OrderProduct, Transaction, Refund models
- OrderRepository and CheckoutRepository
- OrderService and OrderCreationService
- OrderManagementTrait and OrderStatusManagerWithPaymentTrait
- OrderSmsTrait for SMS notifications
- GraphQL schema with queries and mutations

### Tests
- 43 tests across 2 test files covering checkout flow, status lifecycle, pricing, events, inventory

### Known Issues
- Dual model system (legacy Marvel columns + modern App columns)
- Commented apiResource routes
- Missing base orders table migration
- Duplicate checkout route definitions
- Missing English/Arabic translation files (only German exists)
