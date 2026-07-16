# Admin Users Routes

All routes are inside the `/api/v1/` prefix group.

## Architecture Note

The Application layer (`app/`) identifies admins exclusively via `type = 'admin'`.  
Route authorization uses Marvel's `permission:super_admin` middleware for backward compatibility.  
This is a deliberate boundary — see `docs/cms-endpoints/admin-users.md` for details.

## SUPER_ADMIN Middleware Group

All routes require `auth:sanctum`, `email.verified`, and `permission:super_admin` middleware.

| Method | URI | Controller | Action | Purpose |
|--------|-----|------------|--------|---------|
| GET | `/admin/list` | `UserController@admins` | List admin users | Returns paginated list of active admin users with `type = 'admin'` |
| POST | `/admin-users/add` | `UserController@adminAddUsers` | Create admin user | Creates new user with `type = 'admin'` and assigns roles/permissions |
| PUT | `/admin-users/update-activation` | `UserController@adminUpdateActivationUsers` | Toggle activation | Toggles `is_active` status; cannot deactivate active admin users unless self |
| DELETE | `/admin-users/delete/{id}` | `UserController@adminDeleteUsers` | Delete user | Hard deletes a user; cannot delete admin users or self |
| GET | `/users` | `UserController@index` | List all users | Returns paginated list of all users with filters |
| POST | `/users/block-user` | `UserController@banUser` | Ban user | Deactivates user and their shops |
| POST | `/users/unblock-user` | `UserController@activeUser` | Unban user | Reactivates a banned user |
| POST | `/users/make-admin` | `UserController@makeOrRevokeAdmin` | Toggle admin | Toggles `SUPER_ADMIN` permission on a user |
| DELETE | `/users/{id}` | `UserController@destroy` | Delete user (legacy) | Hard deletes a user without business logic guards |
