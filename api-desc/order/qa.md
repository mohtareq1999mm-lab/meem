# QA - Order Feature

## Test Matrix

### Customer

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-ORD-101 | Customer list returns only own orders | data[] + links{} |
| TC-ORD-102 | Customer list includes `order_has_invoice` / `invoice_id` | Present |
| TC-ORD-103 | Customer list respects limit (15, 50, 100) | Correct count |
| TC-ORD-104 | Customer list caps limit > 100 | Capped to 100 |
| TC-ORD-105 | Customer invoice view returns 200 for owner | 200 + CustomerInvoiceResource |
| TC-ORD-106 | Customer invoice view returns 403 for non-owner | 403 |
| TC-ORD-107 | Customer invoice view returns 404 for unknown uuid | 404 |
| TC-ORD-108 | Unauthenticated customer requests return 401 | Both endpoints |

### Admin

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-ORD-001 | List returns paginated data array | data[] + links{} |
| TC-ORD-002 | List respects limit param (15, 50, 100) | Correct count |
| TC-ORD-003 | List rejects limit > 100 | Caps to 100 |
| TC-ORD-004 | List filters by status | Only matching |
| TC-ORD-005 | List searches by name/email/phone | Matching records |
| TC-ORD-006 | List date range filter works | Date-bound results |
| TC-ORD-007 | Detail returns full order by ID | 200 + data |
| TC-ORD-008 | Detail returns full order by tracking number | 200 + data |
| TC-ORD-009 | Detail returns 404 for invalid ID | 404 |
| TC-ORD-010 | Detail includes financial/items/transactions | Conditional fields present |
| TC-ORD-011 | Unauthenticated returns 401 | Both endpoints |
| TC-ORD-012 | Forbidden returns 403 | Without permission |
| TC-ORD-013 | List does NOT include order_items/transactions | Absent from list items |
| TC-ORD-014 | Detail envelope shape | `{status, message, success, data}` |

## Manual Test Checklist

- [ ] Verify all admin filter parameters work individually and combined
- [ ] Verify pagination links are correct (customer has `last_page_url`/`first_page_url`)
- [ ] Verify admin detail endpoint resolves by both ID and tracking number
- [ ] Verify conditional fields only appear on admin show route
- [ ] Verify customer list is scoped to authenticated user
- [ ] Verify `order_has_invoice` reflects invoice existence and `invoice_id` is the uuid
- [ ] Verify customer invoice endpoint rejects non-owner with 403
- [ ] Verify all monetary values are numbers rounded to 2 decimals