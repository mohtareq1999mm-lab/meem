# Bug Fix: User Listing Filters Ignored

**Date:** 2026-07-20

**Affected Endpoint:** `GET /api/v1/users`

**Bugs Fixed:**

| # | Parameter | Issue | Fix |
|---|---|---|---|
| BUG-1 | `?admins=1` | Filter ignored — returned all users | `$request->query('admins', 'false')` changed to `$request->boolean('admins')` |
| BUG-2 | `?in_active=1` | Filter ignored — returned all users | `$request->query('in_active', 'false')` changed to `$request->boolean('in_active')` |
| BUG-3 | `?trash=1` | Filter ignored — returned all users | `$request->query('trash', 'false')` changed to `$request->boolean('trash')` |

**Root Cause:**

All 5 boolean filters in `UserController::index()` (`users`, `admins`, `trash`, `active`, `in_active`) used `$request->query('key', 'false')` with strict `=== 'true'` comparison. When curl passes `?admins=1`, the value is the string `'1'`, which does not strictly equal `'true'`, so all conditions silently evaluated to `false`.

**File Changed:** `packages/marvel/src/Http/Controllers/UserController.php` (lines 119-142)

**Before:**
```php
$filterUsers = $request->query('users', 'false');
$filterAdmins = $request->query('admins', 'false');
$filterTrash = $request->query('trash', 'false');
$active = $request->query('active', 'false');
$inActive = $request->query('in_active', 'false');

if ($filterTrash === 'true') { ... }
if ($filterUsers === 'true') { ... }
elseif ($filterAdmins === 'true') { ... }
if ($active === 'true') { ... }
if ($inActive === 'true') { ... }
```

**After:**
```php
$filterUsers = $request->boolean('users');
$filterAdmins = $request->boolean('admins');
$filterTrash = $request->boolean('trash');
$active = $request->boolean('active');
$inActive = $request->boolean('in_active');

if ($filterTrash) { ... }
if ($filterUsers) { ... }
elseif ($filterAdmins) { ... }
if ($active) { ... }
if ($inActive) { ... }
```

**Why `$request->boolean()`:**

Laravel's `boolean()` method returns `true` for any of: `true`, `1`, `on`, `yes` — making the endpoint compatible with both `?admins=1` and `?admins=true`.

**Scope:** The `users` and `active` parameters had the same bug and were fixed as well for consistency.
