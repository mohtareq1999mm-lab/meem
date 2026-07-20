# Bug Report - Notification Feature

## Issue 1 (HIGH): Missing Translation Keys

- **Description:** All 6 notification message constants are defined in `packages/marvel/config/constants.php` but the corresponding keys are **missing** from both `resources/lang/en/message.php` and `resources/lang/ar/message.php`.
- **Affected Keys:**
  - `MESSAGE.NOTIFICATIONS_FETCHED`
  - `MESSAGE.UNREAD_NOTIFICATIONS_FETCHED`
  - `MESSAGE.NOTIFICATION_MARKED_READ`
  - `MESSAGE.ALL_NOTIFICATIONS_MARKED_READ`
  - `MESSAGE.NOTIFICATION_DELETED`
  - `MESSAGE.ALL_NOTIFICATIONS_DELETED`
- **Impact:** `__($key)` falls through to return the raw constant key as the response message (e.g., `"MESSAGE.NOTIFICATIONS_FETCHED"` instead of "Notifications fetched successfully.").
