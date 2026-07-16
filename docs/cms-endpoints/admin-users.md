# Admin Users API

## Overview

Endpoints for managing admin users (SUPER_ADMIN role only). All endpoints require authentication via Sanctum and `email.verified` middleware.

The admin-user management feature implements 3 dedicated endpoints:
- `POST /admin-users/add` — Create a new user with role assignment
- `PUT /admin-users/update-activation` — Toggle a user's `is_active` status
- `DELETE /admin-users/delete/{id}` — Delete a user (with guards)

Two additional legacy endpoints exist for listing users:
- `GET /admin/list` — List admin users (raw paginator return)
- `GET /users` — List all users with filtering (raw paginator return)

**Soft delete is NOT implemented.** The `users` table has no `deleted_at` column. The `adminDeleteUsers` endpoint performs a hard delete, with guards against deleting super_admin users or yourself.

---

## 1. List Admin Users

**Endpoint**

`GET /admin/list`

**Purpose**

Retrieve a paginated list of all users with `super_admin` permission. Only returns active users (`is_active = true`).

**Authentication**

| Field | Value |
|---|---|
| Required | Yes |
| Guard | Sanctum |
| Permission | `view-admins` |

**Authorization**

- Route group: `permission:super_admin` (Spatie middleware)
- Constructor middleware: `permission:view-admins` on `admins` method
- Super admin users bypass all permission checks via `Gate::before`

**Query Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `limit` | integer | No | Items per page (default: 15) |

**Business Rules**

- Hard-filters `is_active = true` — inactive users are never returned
- Filters by `super_admin` permission using Spatie's `whereHas('permissions', ...)`
- Returns raw paginator (not wrapped in `apiResponse`)
- Eager loads `profile`, `address`, `permissions` relationships

**Success Response (200)**

```json
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com",
            "email_verified_at": "2024-01-01T00:00:00Z",
            "is_active": true,
            "shop_id": null,
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-01T00:00:00Z",
            "profile": {
                "id": 1,
                "avatar": null,
                "bio": null,
                "contact": null,
                "customer_id": 1
            },
            "address": [],
            "permissions": [
                {
                    "id": 1,
                    "name": "super_admin",
                    "guard_name": "api"
                }
            ]
        }
    ],
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
}
```

**Note:** This endpoint uses the legacy raw paginator format (NOT wrapped in `{status, message, success, data}`). It is the only admin-user endpoint that does NOT use the `ApiResponse` trait.

**Error Responses**

| Code | Description |
|---|---|
| 401 | Unauthenticated |
| 403 | Forbidden — requires SUPER_ADMIN route group |
| 500 | Something went wrong |

---

## 2. Add Admin User

**Endpoint**

`POST /admin-users/add`

**Purpose**

Create a new user and assign roles. Unlike the registration endpoint (which only creates customers), this endpoint allows assigning any role including `super_admin`, `store_owner`, etc.

**Authentication**

| Field | Value |
|---|---|
| Required | Yes |
| Guard | Sanctum |
| Permission | `create-user` |

**Authorization**

- Route group: `permission:super_admin`
- Constructor middleware: `permission:create-user` on `adminAddUsers`

**Request Body**

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | User's full name |
| `email` | string | Yes | User's email address |
| `password` | string | Yes | User's password (min 6, max 50) |
| `password_confirmation` | string | Yes | Must match `password` |
| `roles` | array | Yes | Array of role IDs from `roles` table |
| `roles.*` | integer | Yes | Each must exist in `roles` table |
| `is_active` | boolean | No | Defaults to `true` (DB default) |

**Validation Rules**

Based on `AdminCreateUserRequest`:
- `name`: required, string
- `email`: required, email
- `password`: required, min:6, confirmed, max:50
- `roles`: required, array
- `roles.*`: integer, exists:roles,id
- `is_active`: boolean (optional)

**Business Rules**

- User is created with `email_verified_at = now()` (auto-verified)
- Roles are assigned by **role ID** (not permission strings) from the `roles` table
- The user's effective permissions are derived from the roles assigned
- User must have at least one role (roles is required)

**Success Response (200)**

```json
{
    "status": 200,
    "message": "User added successfully",
    "success": true,
    "data": {
        "id": 2,
        "name": "New Admin",
        "email": "newadmin@example.com",
        "email_verified_at": "2024-01-01T00:00:00Z",
        "is_active": true,
        "shop_id": null,
        "roles": [
            {
                "id": 1,
                "name": "super_admin",
                "display_name": "{\"en\":\"Super Admin\",\"ar\":\"مدير النظام\"}",
                "guard_name": "api"
            }
        ],
        "permissions": [
            {
                "id": 1,
                "name": "super_admin",
                "guard_name": "api"
            }
        ]
    }
}
```

**Note:** This endpoint uses the wrapped `apiResponse()` format. The `roles` and `permissions` fields are included via `UserResource` using `whenLoaded('roles')` and `getPermissionsViaRoles()`.

**Error Responses**

| Code | Description |
|---|---|
| 401 | Unauthenticated |
| 403 | Forbidden — requires `create-user` permission |
| 422 | Validation error (invalid fields, password mismatch, etc.) |
| 500 | Something went wrong |

---

## 3. Update User Activation Status

**Endpoint**

`PUT /admin-users/update-activation`

**Purpose**

Toggle a user's `is_active` status. Cannot deactivate a `super_admin` user unless the request is made by that same user or the user is already inactive.

**Authentication**

| Field | Value |
|---|---|
| Required | Yes |
| Guard | Sanctum |
| Permission | `edit-user` |

**Authorization**

- Route group: `permission:super_admin`
- Constructor middleware: `permission:edit-user` on `adminUpdateActivationUsers`

**Request Body**

| Field | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | Yes | ID of the user to toggle activation |

**Validation Rules**

- `user_id`: required, integer, exists:users,id

**Business Rules**

- If the target user has the `super_admin` role:
  - The operation is **blocked** with 400 "User cannot be updated" UNLESS:
    - The requesting user is the same as the target user (self-deactivation allowed), OR
    - The target user is already inactive (re-toggling an inactive super_admin is blocked)
- Otherwise, toggles the `is_active` boolean field (`true` → `false` or `false` → `true`)

**Success Response (200)**

```json
{
    "status": 200,
    "message": "User updated successfully",
    "success": true
}
```

**Error Responses**

| Code | Description |
|---|---|
| 400 | User cannot be updated (super_admin guard triggered) |
| 401 | Unauthenticated |
| 403 | Forbidden — requires `edit-user` permission |
| 404 | User not found |
| 422 | Validation error |
| 500 | Something went wrong |

---

## 4. Delete User

**Endpoint**

`DELETE /admin-users/delete/{id}`

**Purpose**

Hard delete a user from the database. Cannot delete users with the `super_admin` role or yourself. This is a permanent delete — there is no soft delete or restore functionality.

**Authentication**

| Field | Value |
|---|---|
| Required | Yes |
| Guard | Sanctum |
| Permission | `delete-user` |

**Authorization**

- Route group: `permission:super_admin`
- Constructor middleware: `permission:delete-user` on `adminDeleteUsers`

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | Yes | User ID to delete |

**Business Rules**

- Cannot delete a user with the `super_admin` role — returns 400
- Cannot delete yourself — returns 400
- Performs a hard delete via `$user->delete()`
- Soft delete is NOT implemented on the User model

**Success Response (200)**

```json
{
    "status": 200,
    "message": "User deleted successfully",
    "success": true
}
```

**Error Responses**

| Code | Description |
|---|---|
| 400 | User cannot be deleted (super_admin or self-deletion) |
| 401 | Unauthenticated |
| 403 | Forbidden — requires `delete-user` permission |
| 404 | User not found |
| 500 | Something went wrong |

---

## 5. List All Users

**Endpoint**

`GET /users`

**Purpose**

Retrieve a paginated, filterable list of all users. Supports filtering by type, active status, search, sorting, and pagination.

**Authentication**

| Field | Value |
|---|---|
| Required | Yes |
| Guard | Sanctum |
| Permission | `view-users` |

**Authorization**

- Route group: `permission:super_admin`
- Constructor middleware: `permission:view-users` on `index`, `show`

**Query Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `limit` | integer | No | Items per page (default: 15) |
| `page` | integer | No | Page number |
| `active` | boolean | No | Filter by `is_active = true` |
| `in_active` | boolean | No | Filter by `is_active = false` |
| `search` | string | No | LIKE search on `name` and `email` fields |
| `order_by` | string | No | Column to sort by (default: `created_at`) |
| `sort` | string | No | Sort direction `asc` or `desc` (default: `desc`) |
| `type` | string | No | Filter by user type (e.g., `user`, `admin`) |
| `users` | string | No | `true` to filter by type `user` |
| `admins` | string | No | `true` to filter by type `admin` |

**Business Rules**

- Uses `withQueryString()` so pagination links preserve all active filters
- Eager loads `profile`, `address`, `permissions` relationships
- Returns raw paginator (not wrapped in `apiResponse`)
- `active` and `in_active` are applied as query scopes on `is_active` column

**Success Response (200)**

```json
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "email_verified_at": "2024-01-01T00:00:00Z",
            "is_active": true,
            "shop_id": null,
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-01T00:00:00Z",
            "profile": { ... },
            "address": [ ... ],
            "permissions": [ ... ]
        }
    ],
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 72,
    "next_page_url": "http://example.com/api/users?page=2",
    "prev_page_url": null,
    "last_page_url": "http://example.com/api/users?page=5",
    "first_page_url": "http://example.com/api/users?page=1",
    "path": "http://example.com/api/users"
}
```

**Note:** This is a legacy endpoint returning the raw paginator format. Filtering support is currently limited — see the test suite for the exact filter implementations that are tested.

**Error Responses**

| Code | Description |
|---|---|
| 401 | Unauthenticated |
| 403 | Forbidden — requires `view-users` permission |
| 500 | Something went wrong |

---

## Database Impact

| Table | Relation | Type |
|---|---|---|
| `users` | Main table | Hard deletes only (no `deleted_at` column) |
| `roles` | Role assignment via `model_has_roles` | Read by `adminAddUsers` |
| `permissions` | Permission assignment via `model_has_permissions` | Read via `getPermissionsViaRoles()` |

**No soft delete infrastructure exists.** If soft-delete functionality is required in the future, the following changes would be needed:
1. Add `SoftDeletes` trait to `Marvel\Database\Models\User`
2. Add `$table->softDeletes()` to the users migration
3. Create new endpoints for restore, force-delete, and trashed listing

---

## Dependencies

| Component | File |
|---|---|
| Controller | `packages/marvel/src/Http/Controllers/UserController.php` |
| Repository | `packages/marvel/src/Database/Repositories/UserRepository.php` |
| Model | `packages/marvel/src/Database/Models/User.php` |
| Request (Add) | `packages/marvel/src/Http/Requests/AdminCreateUserRequest.php` |
| Resource | `packages/marvel/src/Http/Resources/UserResource.php` |
| Resource (Role) | `packages/marvel/src/Http/Resources/RoleResource.php` |
| Resource (Permission) | `packages/marvel/src/Http/Resources/PermissionResource.php` |
| Trait (API Response) | `packages/marvel/src/Traits/ApiResponse.php` |
| Permission Enum | `packages/marvel/src/Enums/Permission.php` |
| Role Enum | `packages/marvel/src/Enums/Role.php` |
| Routes | `packages/marvel/src/Rest/Routes.php` |
| Translations (EN) | `resources/lang/en/activity.php` |
| Constants | `packages/marvel/config/constants.php` |

---

## Implementation Status

| # | Endpoint | Main Branch | feature/admin-users Branch | Status |
|---|---|---|---|---|
| 1 | `GET /admin/list` | Exists | Exists | ✅ Active |
| 2 | `POST /admin-users/add` | ❌ Missing | Exists | 🔄 Pending merge |
| 3 | `PUT /admin-users/update-activation` | ❌ Missing | Exists | 🔄 Pending merge |
| 4 | `DELETE /admin-users/delete/{id}` | ❌ Missing | Exists | 🔄 Pending merge |
| 5 | `DELETE /admin-users/delete-forever/{id}` | ❌ Missing | ❌ Missing | ❌ Not planned |
| 6 | `PUT /admin-users/restore/{id}` | ❌ Missing | ❌ Missing | ❌ Not planned |
| 7 | `GET /admin-users/trashed` | ❌ Missing | ❌ Missing | ❌ Not planned |
| 8 | `GET /users` | Exists | Exists | ✅ Active |

Endpoints 5-7 were removed from the requirements. Soft delete is not implemented and not planned. The intended implementation is the `feature/admin-users` branch which provides hard-delete with business logic guards.
