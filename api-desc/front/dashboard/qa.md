# QA - Dashboard Feature

## Test Matrix

| TC ID | Description | Expected |
|-------|-------------|----------|
| TC-DB-001 | Overview returns correct fields | 7 KPI fields |
| TC-DB-002 | Revenue returns 12-month breakdown | All months present |
| TC-DB-003 | Order stats has 4 period groups | today, weekly, monthly, yearly |
| TC-DB-004 | Order stats statuses correct | 8 status fields |
| TC-DB-005 | Recent orders sorted by date | newest first |
| TC-DB-006 | Top products ordered by sold_quantity | DESC |
| TC-DB-007 | Category stats has 2 distributions | product + sales |
| TC-DB-008 | Low stock only products < 10 | Correct threshold |
| TC-DB-009 | All analytics endpoints return 200 | Smoke test |
| TC-DB-010 | Unauthenticated returns 401 | All 16 endpoints |
| TC-DB-011 | Invalid endpoint returns 404 | Unknown route |
| TC-DB-012 | Empty database returns valid structure | Zero values, not errors |

## Manual Test Checklist

- [ ] Verify all 16 endpoints return correct data structure
- [ ] Verify data matches actual database records
- [ ] Verify cache refreshes after 300 seconds
- [ ] Verify unauthenticated access returns 401
