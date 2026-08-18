# Test Cases - Order Feature

## Current Coverage

- **Admin controller** (`Marvel\Http\Controllers\Order\OrderController`): no dedicated test file found. Existing order tests in `tests/Feature/OrderTest.php` test the general/customer `OrderController` (create/update/delete/payment).
- **Customer controller** (`App\Http\Controllers\Api\General\OrderController`): partially covered by `tests/Feature/OrderTest.php` and related payment tests.

## Recommended Tests

### Customer

| # | Test | Type | Priority |
|---|------|------|----------|
| TC-ORD-C001 | Customer list returns only authenticated user's orders | Feature | High |
| TC-ORD-C002 | Customer list returns 401 without token | Auth | High |
| TC-ORD-C003 | Customer list filters by status | Filter | Medium |
| TC-ORD-C004 | Customer list default limit is 15 | Pagination | Medium |
| TC-ORD-C005 | Customer list max limit is 100 | Pagination | Medium |
| TC-ORD-C006 | Customer list includes `order_has_invoice` and `invoice_id` | Structure | Medium |
| TC-ORD-C007 | Customer invoice endpoint returns 200 for owner | Feature | High |
| TC-ORD-C008 | Customer invoice endpoint returns 403 for non-owner | Auth | High |
| TC-ORD-C009 | Customer invoice endpoint returns 404 for unknown uuid | Edge | High |

### Admin

| # | Test | Type | Priority |
|---|------|------|----------|
| FT-001 | Index returns paginated list | Feature | High |
| FT-002 | Index returns 200 with valid token + permission | Feature | High |
| FT-003 | Index returns 401 without token | Auth | High |
| FT-004 | Index returns 403 without view-orders permission | Auth | High |
| FT-005 | Index filters by status | Filter | Medium |
| FT-006 | Index filters by user_id | Filter | Medium |
| FT-007 | Index filters by search (name, email, phone) | Filter | Medium |
| FT-008 | Index filters by date range (created_from, created_to) | Filter | Medium |
| FT-009 | Index filters by promotion_name (subquery) | Filter | Medium |
| FT-010 | Index filters by product_name (whereHas) | Filter | Medium |
| FT-011 | Index default limit is 15 | Pagination | Medium |
| FT-012 | Index max limit is 100 | Pagination | Medium |
| FT-013 | Index invalid limit returns 15 | Pagination | Low |
| FT-014 | Show returns order by ID | Feature | High |
| FT-015 | Show returns order by tracking number | Feature | High |
| FT-016 | Show returns 401 without token | Auth | High |
| FT-017 | Show returns 403 without view-order permission | Auth | High |
| FT-018 | Show returns 404 for non-existent order | Edge | High |
| FT-019 | Show includes financial fields + items + transactions | Structure | High |
| FT-020 | Index response includes links object | Structure | Medium |
| FT-021 | Index response customer field is conditional | Structure | Medium |
| FT-022 | Empty list returns empty data array | Edge | Medium |
| FT-023 | Index list does NOT include order_items/transactions | Structure | High |