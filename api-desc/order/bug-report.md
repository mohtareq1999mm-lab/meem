# Bug Report - Order Feature

## Issue 1 (LOW): No Explicit Order By on List

- **File:** `packages/marvel/src/Http/Controllers/Order/OrderController.php:55`
- **Description:** `paginate($limit)` applied without `orderBy`. Results depend on default DB ordering (primary key).
- **Impact:** Pagination may return inconsistent ordering across requests.

## Issue 2 (MEDIUM): Promotion Name Subquery Performance

- **File:** `packages/marvel/src/Http/Controllers/Order/OrderController.php:34-38`
- **Description:** `promotion_name` filter uses `whereIn('promotion_code', Promotion::query()->where('name', 'like', "...")->select('code'))`. This is a correlated subquery on a LIKE match with no index on `promotions.name`.
- **Impact:** Slow query on large promotion tables.
