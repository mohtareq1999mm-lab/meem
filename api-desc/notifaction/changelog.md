# Changelog - Notification Feature

## [Unreleased]

### Added
- 6 admin notification endpoints under `/api/v1/admin/notifications`
- Paginated list with user-scoping
- Unread-only endpoint
- Mark as read (single + all)
- Delete (single + all)
- `auth:sanctum` + `admin` middleware + Spatie permissions
- 3 event-driven notification types (OrderCreated, ContactMessageReceived, AdminLoggedIn)
- Custom `formatNotification()` extracting typed fields from data JSON
- 34 test methods covering auth, permissions, CRUD, events, and JSON structure

### Known Issues
- All 6 translation keys missing from EN and AR language files
- No real-time delivery (polling via frontend)
