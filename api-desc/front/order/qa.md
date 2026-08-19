# QA - Order Feature

## Test Matrix

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-ORD-000 | List orders filtered by status | `?status=pending` returns only pending |
| TC-ORD-002 | Checkout with COD | 201, order created |
| TC-ORD-003 | Checkout with online payment | 201, transaction pending |
| TC-ORD-004 | Checkout with empty cart | 422 |
| TC-ORD-005 | Checkout with invalid payment method | 422 |
| TC-ORD-006 | Checkout with promotion | Discount applied |
| TC-ORD-007 | Checkout with coupon | Discount applied |
| TC-ORD-008 | Payment callback success | Order completed |
| TC-ORD-009 | Payment callback failure | Order cancelled |
| TC-ORD-010 | Mark COD as paid | Status updated |
| TC-ORD-011 | Status transition valid | Allowed |
| TC-ORD-012 | Status transition invalid | 422 |
| TC-ORD-013 | Admin order list | Paginated |
| TC-ORD-014 | Admin order detail | Full resource |
| TC-ORD-015 | Export orders | File download |
| TC-ORD-016 | Invoice download | PDF |
| TC-ORD-017 | Price snapshot immutable | Price changes don't affect |
| TC-ORD-018 | Guest cannot checkout | 401 |
| TC-ORD-019 | Guest cannot view orders | 401 |
| TC-ORD-020 | Cancelled order restores inventory | Stock restored |
| TC-ORD-021 | Authenticated user views own order detail | 200 + order resource |
| TC-ORD-022 | Authenticated user requests another user's order | 404 (no data leaked) |
| TC-ORD-023 | Authenticated user requests nonexistent order | 404 |
| TC-ORD-024 | Order detail ignores `user_id` query parameter | 404 (ownership from token only) |

## Manual Test Checklist

- [ ] Verify customer can see only their orders
- [ ] Verify customer can open their own order detail (200)
- [ ] Verify customer gets 404 (not 403/200) when opening another user's order
- [ ] Verify changing the order ID in the URL cannot reveal another user's order data
- [ ] Verify checkout creates order with correct totals
- [ ] Verify COD payment flow (pending → mark-paid → completed)
- [ ] Verify online payment flow (pending → callback → completed)
- [ ] Verify status transitions are enforced
- [ ] Verify inventory is restored on cancellation
- [ ] Verify export generates downloadable file
- [ ] Verify invoice download with token
