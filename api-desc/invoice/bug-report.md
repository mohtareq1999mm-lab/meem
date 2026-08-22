# Bug Report — Invoice Module

---

## BUG-INV-001: Hardcoded English Strings Instead of Translation Keys

**Severity:** MEDIUM

**Component:** `App\Http\Controllers\Api\InvoiceController`

**Description:** Success and error messages use hardcoded English strings instead of constants with translation keys:
```php
'Invoice corrected successfully'      // correct() — line 232
'Invoice cancelled successfully'       // cancel() — line 254
'Debit note issued successfully'       // issueDebitNote() — line 279
'Invoice verification failed'          // verify() — line 126
'PDF not yet generated'                // download() — line 177
'Cannot issue debit note...'           // issueDebitNote() — line 269
```

**Code Locations:** `app/Http/Controllers/Api/InvoiceController.php` lines 126, 177, 232, 254, 269, 279

**Production Impact:**
- Arabic locale shows English messages
- Inconsistent with other modules that use `__(CONSTANT_KEY)` pattern
- Only `ERROR_CREATING_INVOICE` constant exists in constants.php

**Fix:**
1. Define constants: `INVOICE_CORRECTED_SUCCESSFULLY`, `INVOICE_CANCELLED_SUCCESSFULLY`, `DEBIT_NOTE_ISSUED_SUCCESSFULLY`, etc.
2. Add translation keys in both `en/message.php` and `ar/message.php`
3. Replace hardcoded strings with `__(CONSTANT_NAME)`

---

## BUG-INV-002: ModelNotFoundException Not Caught in Controller

**Severity:** HIGH

**Component:** `App\Http\Controllers\Api\InvoiceController`

**Description:** The `show()`, `regenerate()`, `correct()`, `cancel()`, and `issueDebitNote()` methods call `findOrFail()` which throws `Illuminate\Database\Eloquent\ModelNotFoundException` when the invoice does not exist. This exception is NOT caught, resulting in an HTML exception page instead of JSON 404.**Code Locations:**
- `show()` line 74 — `Invoice::with(...)->findOrFail($id)`
- `regenerate()` line 199 — `Invoice::query()->findOrFail($id)`
- `correct()` line 224 — via `InvoiceService::correctInvoice()` → `Invoice::lockForUpdate()->findOrFail()`
- `cancel()` line 247 — via `InvoiceService::cancelInvoice()` → `Invoice::lockForUpdate()->findOrFail()`
- `issueDebitNote()` line 266 — `Invoice::query()->findOrFail($id)`

**Note:** `showByUuid()` and `download()` use `firstOrFail()` which has the same issue.

**Production Impact:** Non-existent invoice IDs/UUIDs cause 500-level errors instead of proper 404 JSON responses.

**Fix:** Add try/catch for `ModelNotFoundException` or register global handler in `App\Exceptions\Handler::register()`.

**Status: RESOLVED (2026-08-22).** The app's exception handler renders every `/api/*` failure as JSON (`shouldReturnJson` path-check), so `ModelNotFoundException` maps to a JSON 404 `{"message":"Resource Not Found","status":false}`. Additionally, `correct()` and `cancel()` now **rethrow** `ModelNotFoundException` ahead of the broad `catch (\RuntimeException)` (which previously swallowed it into a 422 that leaked the `App\Models\Invoice` FQCN). Locked by regression tests: `AdminInvoiceShowTest::test_regression_inv001_*`, `AdminInvoiceCorrectTest`/`AdminInvoiceCancelTest::test_regression_inv003_*`.

---

## BUG-INV-002b (NEW, 2026-08-22): Malformed `{id}` Reached Controller as TypeError

**Severity:** HIGH

**Component:** `packages/marvel/src/Rest/Routes.php` invoice routes + `show(int $id)`

**Description:** `{id}` routes had no numeric constraint. A non-numeric segment (e.g. `not-an-id`, a UUID) was bound to the `int $id` parameter → PHP `TypeError` → HTTP 500.

**Status: RESOLVED (2026-08-22).** All five `{id}` routes now use `->whereNumber('id')`; malformed ids fail routing with 404.

---

## BUG-INV-002c (NEW, 2026-08-22): Regenerate From `ready` Crashed on State Machine

**Severity:** HIGH

**Description:** Controller allowlist accepted `ready`, but the `InvoiceStatus` enum forbade `READY → PDF_GENERATING`; the model saving hook threw an unhandled `RuntimeException` → HTTP 500 with no job dispatched.

**Status: RESOLVED (2026-08-22).** Enum now permits the edge; documented contract ("allowed from `failed|ready|generated`", see api.md) and implementation agree. Regression: `AdminInvoiceRegenerateTest::test_regression_inv002_*`.

---

## BUG-INV-003: Download Authorization Returns 404 for Privacy (Inconsistent)

**Severity:** LOW

**Component:** `App\Http\Controllers\Api\InvoiceController::download()`

**Description:** When the user is not the invoice owner AND does not have `view-invoice` permission, the endpoint returns 404 "Not found" instead of 403 "Forbidden". This is intentional for privacy (don't reveal existence), but inconsistent with other permission-denied responses across the API that return 403.

**Code Location:** `app/Http/Controllers/Api/InvoiceController.php:170-173`

**Production Impact:** Low — intentional privacy measure, but may confuse API consumers expecting 403.

---

## BUG-INV-004: `cancel()` Uses Inline Validation Instead of Form Request

**Severity:** LOW

**Component:** `App\Http\Controllers\Api\InvoiceController::cancel()`

**Description:** The `cancel()` method uses `$request->validate([...])` inline instead of a dedicated Form Request class. All other invoice operations (correction, debit note) use Form Requests.

**Code Location:** `app/Http/Controllers/Api/InvoiceController.php:244`

```php
$request->validate(['reason' => 'required|string|max:500']);
```

**Fix:** Create `CancelInvoiceRequest` Form Request.

---

## BUG-INV-005: No Unique Constraint on `debit_notes.debit_note_number` in Test Tables

**Severity:** LOW

**Component:** `tests/Concerns/WithInvoiceTables.php`

**Description:** The `createInvoiceTables()` trait creates the `debit_notes` table with `$table->unique('debit_note_number', 'uq_debit_notes_number')` — this IS correct. Verified.

---

## BUG-INV-006: `verified` Status in `Controller::cancel()` May Differ From Enum

**Severity:** MEDIUM

**Component:** `App\Http\Controllers\Api\InvoiceController::cancel()` vs `App\Services\Invoice\InvoiceService::cancelInvoice()`

**Description:** The controller calls `InvoiceService::cancelInvoice()` which has its own status allowlist:
```php
$allowed = ['generated', 'ready', 'failed', 'corrected', 'verified', 'downloaded', 'printed'];
```
This list is a DUPLICATE of the state machine in `InvoiceStatus::allowedTransitions()`. If the enum changes, the service method might become inconsistent.

According to the enum: `InvoiceStatus::VERIFIED->allowedTransitions()` includes `downloaded`, `printed`, `cancelled`, `archived`. So `verified → cancelled` IS allowed by the enum. However, if the enum changes, this list in the service must also be updated.

**Fix:** Delegate to the enum instead of maintaining a separate allowlist:
```php
if (!InvoiceStatus::tryFrom($invoice->status)?->canTransitionTo(InvoiceStatus::CANCELLED)) {
    throw new RuntimeException(...);
}
```

---

## BUG-INV-007: No Rate Limiting on Invoice List/Show Endpoints

**Severity:** MEDIUM

**Component:** `routes/api.php`

**Description:** Only `verify` (5/min) and `download` (30/min) have rate limiting. The list, show, my-invoices, regenerate, correct, cancel, and debit-note endpoints have no throttle middleware.

**Code Location:** `routes/api.php:123-136`

**Fix:** Add `RateLimiter::for('invoice')` in `RouteServiceProvider` and apply throttle to the inner auth group.
