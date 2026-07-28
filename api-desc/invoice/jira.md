# Invoice Module — Jira Tasks

---

## Task 1: Add Translation Keys and Constants for Invoice Messages

**Priority:** High
**Component:** Invoice API
**Effort:** Small
**Files:**
- `packages/marvel/config/constants.php`
- `resources/lang/en/message.php`
- `resources/lang/ar/message.php`
- `app/Http/Controllers/Api/InvoiceController.php`

**Description:** The controller uses hardcoded English strings. Define constants and add translation keys for all invoice messages.

**Acceptance Criteria:**
- [ ] Constants defined: `INVOICE_CORRECTED_SUCCESSFULLY`, `INVOICE_CANCELLED_SUCCESSFULLY`, `DEBIT_NOTE_ISSUED_SUCCESSFULLY`, `INVOICE_VERIFICATION_FAILED`, `PDF_NOT_YET_GENERATED`
- [ ] Translation keys added to both EN and AR message files
- [ ] Controller updated to use `__(CONSTANT_NAME)`

---

## Task 2: Add Exception Handling for ModelNotFoundException

**Priority:** High
**Component:** Invoice Controller
**Effort:** Small
**Files:**
- `app/Http/Controllers/Api/InvoiceController.php`
- `app/Exceptions/Handler.php`

**Description:** `findOrFail()` / `firstOrFail()` in `show()`, `showByUuid()`, `download()`, `regenerate()`, `correct()`, `cancel()`, `issueDebitNote()` are not caught — results in HTML 500 instead of JSON 404.

**Acceptance Criteria:**
- [ ] All findOrFail/firstOrFail calls return JSON 404 on ModelNotFoundException
- [ ] Response format: `{ status: 404, message: "Not found", success: false }`

---

## Task 3: Create CancelInvoiceRequest Form Request

**Priority:** Medium
**Component:** Invoice Controller
**Effort:** Trivial
**Files:**
- `app/Http/Requests/Invoice/CancelInvoiceRequest.php` (new)
- `app/Http/Controllers/Api/InvoiceController.php`

**Description:** The `cancel()` method uses `$request->validate()` inline instead of a Form Request. Extract to `CancelInvoiceRequest`.

**Acceptance Criteria:**
- [ ] `CancelInvoiceRequest` created with `reason: required|string|max:500`
- [ ] Controller type-hints `CancelInvoiceRequest $request`
- [ ] All cancel tests pass

---

## Task 4: Delegate Cancel/Correct Status Checks to Enum

**Priority:** Medium
**Component:** Invoice Service
**Effort:** Small
**Files:**
- `app/Services/Invoice/InvoiceService.php`
- `app/Http/Controllers/Api/InvoiceController.php`

**Description:** `InvoiceService::cancelInvoice()` and `InvoiceController::regenerate()/issueDebitNote()` maintain separate status allowlists that duplicate the enum's state machine. Use `InvoiceStatus::canTransitionTo()` instead.

**Acceptance Criteria:**
- [ ] `cancelInvoice()` uses `InvoiceStatus::tryFrom($status)?->canTransitionTo(CANCELLED)` instead of inline array
- [ ] `regenerate()` uses enum check
- [ ] `issueDebitNote()` uses enum check
- [ ] Duplicate logic removed

---

## Task 5: Add Rate Limiting to Invoice Endpoints

**Priority:** Medium
**Component:** Routes
**Effort:** Small
**Files:**
- `app/Providers/RouteServiceProvider.php`
- `routes/api.php`

**Description:** Only `verify` and `download` have rate limiting. Add throttle to the inner auth group.

**Acceptance Criteria:**
- [ ] `RateLimiter::for('invoice')` registered (30 req/min)
- [ ] Applied to the `auth:sanctum` invoice group

---

## Task 6: Add API Feature Tests for Invoice Controller

**Priority:** High
**Component:** Tests
**Effort:** Large
**Files:**
- `tests/Feature/Invoice/InvoiceApiTest.php` (new)
- `tests/Feature/Invoice/InvoiceAuthTest.php` (new)

**Description:** No API/feature tests exist. All current tests are unit tests. Add full feature test suite covering all 10 endpoints.

**Acceptance Criteria:**
- [ ] 30+ feature tests covering all endpoints
- [ ] Auth tests: 401, 403 for all relevant permissions
- [ ] Validation tests: all form requests
- [ ] Edge cases: non-existent, empty lists, max limits
- [ ] All tests pass

---

## Task 7: Add Snapshot Schema Migration Strategy

**Priority:** Low
**Component:** Invoice Snapshot
**Effort:** Medium
**Files:**
- `app/Services/Invoice/InvoiceSnapshotService.php`

**Description:** The snapshot has `snapshot_version` (2.1.0) and `snapshot_schema` (3) fields. If the snapshot structure changes in future migrations, existing invoices' snapshots will be in the old format. Define a migration strategy.

**Acceptance Criteria:**
- [ ] Document schema versioning approach
- [ ] Handle backward compatibility in snapshot resources
- [ ] Version migration logic if needed

---

## Task 8: Add Invoice Seeder for Development

**Priority:** Low
**Component:** Database
**Effort:** Small
**Files:**
- `database/seeders/InvoiceSeeder.php` (new)

**Description:** No seed data exists for invoices. Development would benefit from pre-seeded invoices in various statuses.

**Acceptance Criteria:**
- [ ] Seeder creates invoices with various statuses
- [ ] Generates PDF placeholders
- [ ] Creates timeline entries
- [ ] Idempotent
