# Bug Report - Pickup Location Feature

## Issue 1 (LOW): Duplicate Pagination Keys in Admin List Response

- **File:** `packages/marvel/src/Http/Controllers/PickupLocationController.php:53-67`
- **Description:** `index()` manually extracts pagination meta from the ResourceCollection response object, resulting in both `page` and `current_page` with the same value, and `last_page_url`, `first_page_url` alongside `last_page`, `path`. The API response has 14 pagination keys instead of the standard 4-5.
- **Impact:** Inconsistent pagination structure compared to other admin endpoints.
