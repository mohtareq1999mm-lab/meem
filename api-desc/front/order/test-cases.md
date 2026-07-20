# Test Cases - Order Feature

## Current Coverage

**43 tests across 2 files:**

### OrdersProductionHardenTest (25 tests)

| Category | Tests |
|----------|-------|
| Authentication | `guest_cannot_checkout`, `guest_cannot_access_promotions` |
| Checkout Flow | `checkout_creates_order_with_correct_totals`, `creates_transaction_for_cod`, `creates_order_items_with_price_snapshot`, `rejects_empty_cart`, `rejects_invalid_payment_method`, `rejects_cod_with_pickup` |
| Status Lifecycle | `pending_to_completed_transition_succeeds`, `pending_to_cancelled_transition_succeeds`, `completed_to_cancelled_transition_rejected`, `cancelled_to_completed_transition_rejected`, `pending_to_delivered_transition_rejected` |
| Payment Callback | `callback_missing_payment_id_returns_400` |
| Coupon Integration | `checkout_with_valid_coupon_applies_discount`, `checkout_with_expired_coupon_ignores_it`, `checkout_with_free_shipping_coupon_sets_shipping_to_zero` |
| Promotion Integration | `checkout_with_percentage_promotion_applies_discount`, `promotion_usage_increments_on_order` |
| Inventory | `checkout_finalizes_inventory_correctly`, `inventory_not_affected_if_checkout_fails`, `cancelled_order_restores_inventory` |
| Events | `order_created_event_dispatched_on_checkout`, `order_status_changed_event_dispatched`, `order_cancelled_event_dispatched` |
| Mark Paid | `mark_cod_as_paid_succeeds`, `rejects_when_no_pending_transaction`, `rejects_already_paid_transaction` |

### OrderCreationFlowTest (18 tests)

| Category | Tests |
|----------|-------|
| Flash Sale Pricing | Percentage, Fixed Rate, Final Price, Null cases (4) |
| Discount Pricing | Percentage, Fixed, Null, Computed price (4) |
| Variant Pricing | Flash sale for variants (percentage/fixed/final), Discount for variants (percentage/fixed) (5) |
| Edge Cases | Product without variant, Variant without price, Variant product unit price (3) |

## Recommended Additional Tests

| # | Test | Description |
|---|------|-------------|
| FT-001 | Order list returns only authenticated user's orders | Authorization |
| FT-002 | Order detail with items and transactions | Full resource |
| FT-003 | Admin order export | File download |
| FT-004 | Invoice download with valid token | PDF download |
| FT-005 | Invoice download with expired token | 401 |
| FT-006 | Cashier payment QR code generation | QR returned |
| FT-007 | Duplicate checkout with same cart | Error |
| FT-008 | Refund flow after completed order | Inventory restored |
