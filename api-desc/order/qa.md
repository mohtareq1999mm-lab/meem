# QA - Order Feature

## Test Matrix

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

## Manual Test Checklist

- [ ] Verify all filter parameters work individually and combined
- [ ] Verify pagination links are correct
- [ ] Verify detail endpoint resolves by both ID and tracking number
- [ ] Verify conditional fields only appear on show route
- [ ] Verify customer relation loads correctly
