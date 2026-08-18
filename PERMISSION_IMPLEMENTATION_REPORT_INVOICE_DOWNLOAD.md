# INVOICE DOWNLOAD PERMISSION — IMPLEMENTATION REPORT

**Date:** 2026-08-18
**Module:** Invoices
**Feature:** Dedicated `view-invoice-download` permission for `GET /api/v1/invoices/{uuid}/download`
**Verdict: PASS**

---

## 1. Problem Analysis

The invoice PDF download endpoint (`GET /api/v1/invoices/{uuid}/download`) used the shared `view-invoice` permission to authorize non-owners. This created two problems:

1. **Permission coupling** — A user who can *view* an invoice could also *download its PDF*, conflating read visibility with file download access. There was no way to grant read access while denying download.
2. **No granular auditability** — Download activity could not be tied to a dedicated capability; granting/revoking download access required touching the broader `view-invoice` permission.

**Required behavior:**
- The invoice owner can always download their own PDF (no permission required).
- Non-owners must hold a dedicated `view-invoice-download` permission.
- `view-invoice` alone must NOT authorize download.
- Unauthorized access preserves the existing 404 privacy behavior (don't reveal invoice existence).

---

## 2. Existing Architecture Review

| Component | Location | Before |
|-----------|----------|--------|
| Enum | `packages/marvel/src/Enums/Permission.php` | Invoice section had `VIEW_INVOICES`, `VIEW_INVOICE`, `REGENERATE_INVOICE`, `CORRECT_INVOICE`, `CANCEL_INVOICE`, `ISSUE_DEBIT_NOTE` |
| Seeder | `database/seeders/PermissionSeeder.php` | `view-invoices` / `view-invoice` in main list (line 58-59) and staff list (line 324-325) |
| Controller | `app/Http/Controllers/Api/InvoiceController.php` | `download()` authorized with `Permission::VIEW_INVOICE` |
| Translations | `resources/lang/{en,ar}/permissions.php` | No invoice permission translations existed |
| Route | `packages/marvel/src/Rest/Routes.php:393` | `GET invoices/{uuid}/download` — `auth:sanctum` + `throttle:30,1` |
| Docs | `api-desc/invoice/*` | Documented `view-invoice` as sufficient for download |

**Key architecture facts verified:**
- Route order is safe: `{uuid}/download` (2 segments) is declared before `{id}` (1 segment) inside the `invoices` prefix group; the `uuid/{uuid}` route lives in `routes/api.php` and does not collide.
- PDF storage: `GenerateInvoicePdfJob` writes to `Storage::disk('public')` → `invoices/<number>.pdf`, stores only the filename in `pdf_path`.
- Side effects on download: `downloaded_at` set only on first download; `InvoiceTimelineService::recordDownloaded()` writes a `downloaded` timeline event.
- `User` model (`Marvel\Database\Models\User`) uses `HasRoles` with `$guard_name = 'api'`.

---

## 3. Proposed Solution

Introduce a dedicated `VIEW_INVOICE_DOWNLOAD = 'view-invoice-download'` permission using the existing permission infrastructure:

1. Add the constant to the `Permission` enum (invoice section).
2. Add `'view-invoice-download'` to the seeder's main permission list and the staff/owner list (super_admin receives the full list automatically via `syncPermissions($permissionsData)`).
3. Add EN + AR translations under a new `// 🧾 Invoices` section in `permissions.php`.
4. Change `InvoiceController::download()` authorization from `Permission::VIEW_INVOICE` to `Permission::VIEW_INVOICE_DOWNLOAD`, keeping the owner bypass and the 404 privacy response.
5. Add a comprehensive zero-trust permission test suite with a real PDF on the public disk.
6. Update `api-desc/invoice` documentation.

No changes were made to any other invoice endpoint. The download endpoint URL, response shape, and privacy semantics (404 for unauthorized) are unchanged.

---

## 4. Trade-Offs

| Decision | Rationale | Alternative considered |
|----------|-----------|------------------------|
| New permission vs reusing `view-invoice` | Decouples download from read; grants can be scoped per capability; matches the module's one-permission-per-action pattern (`regenerate`, `correct`, `cancel`, `issue-debit-note`) | Reusing `view-invoice` keeps a smaller permission table but fails the stated requirement |
| 404 (not 403) for unauthorized non-owners | Preserves the existing privacy design — do not reveal invoice existence to unprivileged users | 403 would leak existence and break the documented contract |
| Owner bypass without permission | Customers must not need a permission row to download their own invoices | Requiring a permission would break the customer self-service flow |
| Real PDF file in tests | Verifies filesystem + DB + HTTP end-to-end (not a mocked response) | Mocked download would not prove `pdf_path`/file handling |

---

## 5. Changes Made

### 5.1 Enum — `packages/marvel/src/Enums/Permission.php`
```php
// 🧾 Invoices
public const VIEW_INVOICES = 'view-invoices';
public const VIEW_INVOICE = 'view-invoice';
public const VIEW_INVOICE_DOWNLOAD = 'view-invoice-download';
public const REGENERATE_INVOICE = 'regenerate-invoice';
```

### 5.2 Seeder — `database/seeders/PermissionSeeder.php`
`'view-invoice-download',` added to:
- Main permission list (after `'view-invoice',` — line 60)
- Staff/Owner list (`$staffAndOnwner` — line 326)

Super admin automatically receives it via `$roleSuperAdmin->syncPermissions($permissionsData)`.

### 5.3 Translations
`resources/lang/en/permissions.php`:
```php
// 🧾 Invoices
'view-invoice-download' => 'Download invoice',
```
`resources/lang/ar/permissions.php`:
```php
// 🧾 Invoices
'view-invoice-download' => 'تنزيل فاتورة PDF',
```
Runtime verified: `EN: Download invoice`, `AR: تنزيل فاتورة PDF`.

### 5.4 Controller — `app/Http/Controllers/Api/InvoiceController.php`
```php
public function download(string $uuid): JsonResponse
{
    $invoice = Invoice::with('order')->where('uuid', $uuid)->firstOrFail();

    if ($invoice->user_id !== request()->user()->id
        && !request()->user()->can(Permission::VIEW_INVOICE_DOWNLOAD)) {
        return $this->apiResponse(NOT_FOUND, 404, false);
    }
    // ... unchanged PDF handling, downloaded_at, timeline, response
}
```

### 5.5 Tests — `tests/Feature/Invoice/InvoiceDownloadPermissionTest.php` (NEW)
18 tests, 52 assertions. Uses `CreatesTestTables` + `DatabaseTransactions` (project pattern from `InvoiceLifecycleTest`), real PDF written to the public disk, `Sanctum::actingAs`, and permission-cache reset between authenticated requests.

---

## 6. Test Matrix (TC-DL)

| ID | Case | Result |
|----|------|--------|
| TC-DL-001 | Unauthenticated → 401 | ✅ PASS |
| TC-DL-002 | Owner downloads without any permission → 200 | ✅ PASS |
| TC-DL-003 | Non-owner WITH `view-invoice-download` → 200 | ✅ PASS |
| TC-DL-004 | **Non-owner with `view-invoice` only → 404 (DENIED)** | ✅ PASS |
| TC-DL-005 | Non-owner without any permission → 404 | ✅ PASS |
| TC-DL-006 | Super admin → 200 | ✅ PASS |
| TC-DL-007 | Real file verified (exists, readable, `%PDF-` content, URL, invoice_number) | ✅ PASS |
| TC-DL-008 | Invoice exists but PDF missing → 404 | ✅ PASS |
| TC-DL-009 | Unknown UUID → 404 | ✅ PASS |
| TC-DL-010 | Invalid UUID format → 404 | ✅ PASS |
| TC-DL-011 | `downloaded_at` NULL → set on first download | ✅ PASS |
| TC-DL-012 | Repeated download preserves original `downloaded_at` | ✅ PASS |
| TC-DL-013 | Timeline `downloaded` event recorded | ✅ PASS |
| TC-DL-014 | Permission exists in DB (name, guard `api`, no duplicates) | ✅ PASS |
| TC-DL-015 | Super admin role has the permission (`role_has_permissions`) | ✅ PASS |
| TC-DL-016 | Enum constant maps to `view-invoice-download` (no hardcoded string, distinct from `view-invoice`/`view-invoices`) | ✅ PASS |
| TC-DL-017 | Auth failure does not leak invoice existence (same uuid → 404 vs 200) | ✅ PASS |
| TC-DL-018 | Owner with the permission still downloads (no regression) | ✅ PASS |

**Result:** `php vendor/bin/phpunit tests/Feature/Invoice/InvoiceDownloadPermissionTest.php` → **OK (18 tests, 52 assertions)**.

---

## 7. Zero-Trust Matrix

Live HTTP verification against the real MySQL DB (`chawkbazar`) using real Sanctum tokens and a real PDF file on the public disk (`storage/realtest/invoice-download-live.log`):

| TC-LIVE | Case | Result |
|---------|------|--------|
| TC-LIVE-001 | Unauthenticated → 401 | ✅ PASS |
| TC-LIVE-002 | Owner → 200, file exists on disk, correct invoice_number | ✅ PASS |
| TC-LIVE-003 | Non-owner with `view-invoice-download` → 200 | ✅ PASS |
| TC-LIVE-004 | **Non-owner with `view-invoice` only → 404 (DENIED)** | ✅ PASS |
| TC-LIVE-005 | Non-owner with no permission → 404 | ✅ PASS |
| TC-LIVE-006 | Super admin (admin@demo.com) → 200 | ✅ PASS |
| TC-LIVE-007 | `downloaded_at` persisted in DB | ✅ PASS |
| TC-LIVE-008 | `invoice_timeline` `downloaded` event persisted | ✅ PASS |
| TC-LIVE-009 | Unknown UUID → 404 | ✅ PASS |
| TC-LIVE-010 | Invalid UUID format → 404 | ✅ PASS |
| TC-LIVE-011 | Permission row exists (no dupes) + assigned to super_admin role | ✅ PASS |

All 11 live checks PASS. All created test data was cleaned up (users, orders, transactions, invoices, timeline, tokens, PDF file). Post-verification DB state: invoices = 0, no leftover test users/orders.

---

## 8. Database Impact

| Item | Status |
|------|--------|
| `permissions` row | `view-invoice-download`, guard `api`, **id=439** (live DB) |
| Duplicate prevention | Verified: exactly 1 row; seeder uses `Permission::firstOrCreate` |
| `role_has_permissions` | super_admin role **9003** has permission 439 (220 total perms) |
| `invoices.downloaded_at` | Set on first download only |
| `invoice_timeline` | `downloaded` event per download |
| No schema migration | No table/column changes required |

**Note:** Re-running `PermissionSeeder` created duplicate role rows (ids 9008-9012) because `Role::firstOrCreate` includes `display_name` (an array) in the match and the stored JSON representation doesn't re-match. This is a **pre-existing seeder idempotency issue** (the earlier orphan roles 1,3,4,5,6,8,9,10,11 show the same behavior). The duplicates created during this task were deleted and the active super_admin role (9003) was confirmed to have the permission. The permission record itself is created idempotently.

---

## 9. Filesystem Impact

- Test suite writes a real PDF to `Storage::disk('public')` → `invoices/<invoice_number>.pdf` and asserts existence, readability, and `%PDF-` content.
- Live verification wrote and then deleted a real PDF on the public disk.
- `GenerateInvoicePdfJob` conventions unchanged (`pdf_path` stores only the filename; download URL is `url('storage/invoices/' . $invoice->pdf_path)`).

---

## 10. Regression & Code Search

| Suite | Result |
|-------|--------|
| `InvoiceDownloadPermissionTest` (new) | 18/18 ✅ |
| `OrderInvoiceEndpointTest` | 7/7 ✅ |
| `RoleAndPermissionTest` | 32/32 ✅ |
| `BugFixesValidationTest` | 13/13 (1 skipped) ✅ |
| `CategoryPermissionTest` | 15/15 ✅ |
| `CurrencyPermissionTest` | 15/15 ✅ |
| `InvoiceLifecycleTest` (unit) | 22/24 — **2 pre-existing failures** (confirmed by stashing my changes): `test_prevents_cancellation_of_verified_invoice` and `test_invoice_status_transitions_are_invalid` (invoice status transition assertions unrelated to download permission) |

Repo-wide search for stale authorization:
- `VIEW_INVOICE` / `view-invoice` used in `download()` paths: **none remaining**.
- All `view-invoice-download` / `VIEW_INVOICE_DOWNLOAD` references are correct (enum, seeder ×2, translations ×2, controller, tests).

---

## 11. Documentation Updated (`api-desc/invoice/`)

| File | Change |
|------|--------|
| `api.md` | Download section: authentication now `owner OR view-invoice-download`; business rules updated (`view-invoice` alone does NOT authorize download) |
| `backend.md` | Routes table row, flow (`Auth: owner check OR can('view-invoice-download')`), permission enum table (+`VIEW_INVOICE_DOWNLOAD`) |
| `flow.md` | Download authorization line updated |
| `README.md` | Permission table (+`view-invoice-download`) and routes table |
| `frontend.md` | Download auth + key consideration #2 |
| `test-cases.md` | Added FT-043..FT-059; checked off now-covered items |
| `changelog.md` | Added 1.1.0 entry |

---

## 12. Security Review

| Check | Status |
|-------|--------|
| Broken access control | ✅ Non-owners without the dedicated permission denied (404) |
| `view-invoice` insufficient for download | ✅ Verified by TC-DL-004, TC-LIVE-004 |
| Information leakage | ✅ Unauthorized response is 404 (no existence leak), verified by TC-DL-017 |
| Enum usage (no magic strings) | ✅ `Permission::VIEW_INVOICE_DOWNLOAD` |
| Mass assignment | ✅ No new mass-assignment surface |
| Permission cache | ✅ `permission:cache-reset` run; cache reset between HTTP requests in tests (`forgetGuards()` + `forgetCachedPermissions()`) |
| Super admin access | ✅ Granted via seeder / verified live (role 9003) |

---

## 13. Non-Blocking Issues

1. **Pre-existing seeder idempotency bug** — `Role::firstOrCreate` with array `display_name` doesn't re-match stored JSON values, so re-running `PermissionSeeder` creates duplicate role rows. Duplicates from this task were cleaned up manually. (Not introduced by this feature; pre-existing.)
2. **2 pre-existing `InvoiceLifecycleTest` failures** — status-transition / verified-cancellation assertions unrelated to download permissions. Verified pre-existing by stashing all working-tree changes.
3. **Pre-existing known issues from changelog 1.0.0** — hardcoded invoice response messages, uncaught `ModelNotFoundException`, inline validation in `cancel()`, 404-vs-403 inconsistency, etc. — out of scope, unchanged.

---

## Verdict: **PASS**

All implementation, tests, live verification, database verification, filesystem verification, and documentation updates are complete. The dedicated `view-invoice-download` permission is implemented, enforced, verified, and documented.