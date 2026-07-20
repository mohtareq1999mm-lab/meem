# QA - Pickup Location Feature

## Test Matrix

| TC ID | Endpoint | Expected |
|-------|----------|----------|
| TC-PL-001 | GET /pickup-locations | Paginated list, ordered, filterable |
| TC-PL-002 | POST /pickup-locations | 200 + resource |
| TC-PL-003 | GET /pickup-locations/{id} | 200 + resource |
| TC-PL-004 | PUT /pickup-locations/{id} | 200 + updated resource |
| TC-PL-005 | DELETE /pickup-locations/{id} | 200, soft deleted |
| TC-PL-006 | GET /general/pickup-locations | Only active, no auth |
| TC-PL-007 | GET /general/pickup-locations/{id} | Active only, 404 if inactive |
| TC-PL-008 | POST (no store_name) | 422 |
| TC-PL-009 | POST (no address) | 422 |
| TC-PL-010 | POST (no phone) | 422 |
| TC-PL-011 | POST (invalid email) | 422 |
| TC-PL-012 | POST (negative display_order) | 422 |
| TC-PL-013 | Unauthenticated access | 401 admin, 200 public |
| TC-PL-014 | Customer access | 403 on CRUD |
| TC-PL-015 | Soft-deleted show | 404 |
| TC-PL-016 | Working hours structure | Validates day+open+close |

## Manual Test Checklist

- [ ] Verify ordering by display_order ASC
- [ ] Verify search finds by store_name
- [ ] Verify public list excludes inactive and soft-deleted
- [ ] Verify checkout saves pickup snapshot to order
- [ ] Verify pickup_location shows in order detail for pickup orders
- [ ] Verify uploaded coordinates render on map
