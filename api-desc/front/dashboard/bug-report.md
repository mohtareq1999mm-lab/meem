# Bug Report - Dashboard Feature

## Issue 1 (CRITICAL): Tests Hit Wrong URLs

- **File:** `tests/Feature/DashboardTest.php`
- **Description:** Uses `const PREFIX = '/api/v1'` missing `/general` segment. 24 of 28 tests will get 404.
- **Impact:** High — no test coverage actually runs.
- **Fix:** Change PREFIX to `/api/v1/general` and fix endpoint names (e.g., `/dashboard/sales` → `/dashboard/sales-analytics`).

## Issue 2 (MEDIUM): Order Stats Hardcoded Statuses

- **File:** `app/Services/Dashboard/DashboardService.php` lines 106-114
- **Description:** Only `pending`, `completed`, `cancelled` queried. `processing`, `refunded`, `failed`, `local_facility`, `out_for_delivery` hardcoded to 0.
- **Impact:** Medium — stats are incorrect for these statuses.

## Issue 3 (LOW): Recent Orders Missing orderBy

- **File:** `app/Services/Dashboard/DashboardService.php` line 130
- **Description:** `Order::with([...])->take($limit)->get()` without `orderBy('created_at', 'desc')`.
- **Impact:** Low — order depends on global scope.

## Issue 4 (LOW): Magic Number Thresholds

- **Description:** Low stock threshold (10), category limits (15), product limits (10) are hardcoded throughout.
- **Impact:** Low — not configurable.
