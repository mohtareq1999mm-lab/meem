# Bug Report - Shipping Feature

## Issue 1 (HIGH): Missing Translation Keys for Country & City Messages

- **Description:** The following translation keys are missing from both `resources/lang/en/message.php` and `resources/lang/ar/message.php`:
  - `MESSAGE.COUNTRY_CREATED_SUCCESSFULLY`
  - `MESSAGE.COUNTRY_UPDATED_SUCCESSFULLY`
  - `MESSAGE.COUNTRY_DELETED_SUCCESSFULLY`
  - `ERROR.COUNTRY_NOT_FOUND`
  - `MESSAGE.GOVERNORATES_FETCHED_SUCCESSFULLY`
  - `MESSAGE.CITY_CREATED_SUCCESSFULLY`
  - `MESSAGE.CITY_UPDATED_SUCCESSFULLY`
  - `MESSAGE.CITY_DELETED_SUCCESSFULLY`
- **Impact:** `__($key)` falls through to return the raw key name as message (e.g., `"MESSAGE.COUNTRY_CREATED_SUCCESSFULLY"` instead of "Country created successfully").

## Issue 2 (MEDIUM): No auth Middleware on Route Group

- **File:** `packages/marvel/src/Rest/Routes.php`
- **Description:** These routes are not wrapped in `auth:sanctum`. Authentication relies solely on the Spatie `permission:` middleware in each controller constructor. If permission middleware is misconfigured, endpoints could be accessible without authentication.

## Issue 3 (LOW): Generic Error on Governorate Delete

- **File:** `packages/marvel/src/Http/Controllers/GovernorateController.php:130`
- **Description:** When deleting a governorate with cities, the repository throws `InvalidArgumentException('Cannot delete a governorate that has cities.')`, but the controller catches no exception and will propagate a 500 error instead of a clean 409/422 response.
