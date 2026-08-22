# Test Coverage — Invoice Module

## Existing Tests

**File:** `tests/Unit/Invoice/InvoiceLifecycleTest.php` (423 lines, 20 tests)

Uses `CreatesTestTables` + `DatabaseTransactions`.

**File:** `tests/Feature/Invoice/InvoiceDownloadPermissionTest.php` (18 tests, 52 assertions — added in 1.1.0, all green)

**File:** `tests/Feature/OrderInvoiceEndpointTest.php` (7 tests — customer invoice view via order + order resource invoice fields)

### Invoice Service Tests (5 tests)

| # | Test | Type | Description |
|---|------|------|-------------|
| 1 | `test_generates_invoice_from_order` | Unit | Invoice created with correct order_id, status=generated, uuid, invoice_number, hashes |
| 2 | `test_prevents_duplicate_invoice_generation` | Unit | Second call returns existing invoice (idempotent) |
| 3 | `test_generates_invoice_with_correct_snapshot_data` | Unit | Snapshot contains all expected sections |
| 4 | `test_verification_hash_is_consistent` | Unit | verifyInvoice returns authentic=true for valid invoice |
| 5 | `test_verify_returns_null_for_nonexistent_invoice` | Unit | Non-existent UUID returns null |

### Correction Tests (3 tests)

| # | Test | Type | Description |
|---|------|------|-------------|
| 6 | `test_corrects_invoice` | Unit | Correction created with is_correction=true, original marked as corrected |
| 7 | `test_corrected_invoice_has_unique_number` | Unit | Correction has different invoice_number |
| 8 | `test_prevents_correction_of_cancelled_invoice` | Unit | Cancelled invoice throws RuntimeException on correction |

### Cancellation Tests (2 tests)

| # | Test | Type | Description |
|---|------|------|-------------|
| 9 | `test_cancels_invoice` | Unit | Invoice cancelled with reason and timestamp |
| 10 | `test_prevents_cancellation_of_verified_invoice` | Unit | Verified invoice can be cancelled (but tests expects exception? — check logic) |

### Credit Note Tests (3 tests)

| # | Test | Type | Description |
|---|------|------|-------------|
| 11 | `test_generates_credit_note_for_refund` | Unit | CreditNote created with type=refund |
| 12 | `test_generates_credit_note_for_cancellation` | Unit | CreditNote created with type=cancellation |
| 13 | `test_credit_notes_have_unique_numbers` | Unit | Multiple credit notes get unique numbers |

### Debit Note Tests (1 test)

| # | Test | Type | Description |
|---|------|------|-------------|
| 14 | `test_generates_debit_note` | Unit | DebitNote created with correct amount and number |

### Invoice Status Transition Tests (2 tests)

| # | Test | Type | Description |
|---|------|------|-------------|
| 15 | `test_invoice_status_transitions_are_valid` | Unit | Enum-level allowed transitions verified |
| 16 | `test_invoice_status_transitions_are_invalid` | Unit | Enum-level disallowed transitions verified |

### Model Status Transition Enforcement (1 test)

| # | Test | Type | Description |
|---|------|------|-------------|
| 17 | `test_invoice_status_transition_is_enforced_on_save` | Unit | Model saving event blocks illegal transition |

### Shipment Tests (3 tests — co-located in same file)

| # | Test | Type | Description |
|---|------|------|-------------|
| 18 | `test_creates_shipment` | Unit | Shipment created from order |
| 19 | `test_shipment_status_transitions` | Unit | Full state machine walkthrough |
| 20 | `test_shipment_illegal_transition_throws` | Unit | Invalid transition throws |

### Coverage Summary

| Category | Count |
|----------|-------|
| Invoice Service Tests | 5 |
| Correction Tests | 3 |
| Cancellation Tests | 2 |
| Credit Note Tests | 3 |
| Debit Note Tests | 1 |
| Status Enum Tests | 3 |
| Shipment Tests | 3 |
| **Total** | **20** |

## Recommended Additional Tests

### API Controller Tests (Feature Tests)

| # | Test | Type | Description |
|---|------|------|-------------|
| FT-001 | GET /invoices returns paginated list | Feature | |
| FT-002 | GET /invoices filters by status | Feature | |
| FT-003 | GET /invoices sorts by total/status/invoice_number | Feature | |
| FT-004 | GET /invoices searches by invoice_number | Feature | |
| FT-005 | GET /invoices/{id} returns AdminInvoiceResource | Feature | |
| FT-006 | GET /general/invoices/uuid/{uuid} returns AdminInvoiceResource | Feature | |
| FT-007 | GET /general/invoices/my-invoices scoped to user | Feature | |
| FT-008 | GET /general/invoices/verify/{uuid} returns authentic=true | Feature | |
| FT-009 | GET /general/invoices/verify/{uuid} returns 409 for tampered | Feature | |
| FT-010 | GET /general/invoices/verify/{uuid} returns 404 | Feature | |
| FT-011 | GET /invoices/{uuid}/download returns PDF URL | Feature | |
| FT-012 | GET /invoices/{uuid}/download returns 404 if no PDF | Feature | |
| FT-013 | GET /invoices/{uuid}/download returns 404 if unauthorized | Auth | |
| FT-014 | POST /invoices/{id}/regenerate dispatches job | Feature | |
| FT-015 | POST /invoices/{id}/regenerate returns 422 from wrong status | Validation | |
| FT-016 | POST /invoices/{id}/correct with overrides | Feature | |
| FT-017 | POST /invoices/{id}/correct validates reason required | Validation | |
| FT-018 | POST /invoices/{id}/cancel with reason | Feature | |
| FT-019 | POST /invoices/{id}/cancel validates reason required | Validation | |
| FT-020 | POST /invoices/{id}/cancel returns 422 from wrong status | Validation | |
| FT-021 | POST /invoices/{id}/debit-note | Feature | |
| FT-022 | POST /invoices/{id}/debit-note validates amount | Validation | |
| FT-023 | POST /invoices/{id}/debit-note returns 422 from wrong status | Validation | |

### Authorization Tests

| # | Test | Type |
|---|------|------|
| FT-024 | Guest cannot list invoices | 401 |
| FT-025 | Guest cannot show invoice | 401 |
| FT-026 | Guest cannot regenerate | 401 |
| FT-027 | Guest cannot correct | 401 |
| FT-028 | Guest cannot cancel | 401 |
| FT-029 | Guest cannot issue debit note | 401 |
| FT-030 | Guest cannot verify (verify now requires auth) | 401 |
| FT-031 | No view-invoices permission → 403 | Auth |
| FT-032 | No correct-invoice permission → 403 | Auth |
| FT-033 | No cancel-invoice permission → 403 | Auth |
| FT-034 | No issue-debit-note permission → 403 | Auth |
| FT-043 | Guest cannot download invoice | 401 |
| FT-044 | Owner can download without permission | 200 |
| FT-045 | Non-owner with `view-invoice-download` → 200 | Auth |
| FT-046 | Non-owner with `view-invoice` only → 404 (DENIED) | Auth |
| FT-047 | Non-owner without any permission → 404 | Auth |
| FT-048 | Super admin can download | 200 |
| FT-049 | Real PDF file exists + readable + URL + invoice_number | Filesystem |
| FT-050 | Invoice without PDF → 404 | Edge Case |
| FT-051 | Unknown UUID → 404 | Edge Case |
| FT-052 | Invalid UUID format → 404 | Edge Case |
| FT-053 | `downloaded_at` set on first download only | DB |
| FT-054 | Timeline `downloaded` event recorded | DB |
| FT-055 | `view-invoice-download` permission exists in DB (no dupes) | DB |
| FT-056 | Super admin role assigned the permission | DB |
| FT-057 | Enum constant used (not hardcoded) | Code |
| FT-058 | Auth failure does not leak invoice existence | Security |
| FT-059 | Owner with permission still downloads (no regression) | Auth |

### Edge Case Tests

| # | Test | Type |
|---|------|------|
| FT-035 | Limit exceeds max (limit=200 → capped to 100) | Edge Case |
| FT-036 | Generate invoice for order without paid transaction | Edge Case |
| FT-037 | Correct invoice with all override fields | Edge Case |
| FT-038 | Cancel invoice from every valid starting status | Edge Case |
| FT-039 | Verify invoice after correction (verification_hash should differ) | Edge Case |
| FT-040 | Download invoice that is still pdf_generating | Edge Case |
| FT-041 | Concurrent correction + cancellation race condition | Edge Case |
| FT-042 | Verify count increments on each verification | Edge Case |

### Frontend Contract Tests (View vs Download vs Preview)

| # | Test | Type | Status |
|---|------|------|--------|
| TC-FE-VIEW-001 | `GET /general/orders/{orderId}/invoice` returns CustomerInvoiceResource fields + snapshot (owner) | Feature | ✅ `OrderIdInvoiceEndpointTest` |
| TC-FE-VIEW-002 | `GET /orders/invoice/{uuid}` → 403 for non-owner | Auth | ✅ `OrderInvoiceEndpointTest` |
| TC-FE-VIEW-003 | `GET /orders/invoice/{uuid}` → 401 guest, 404 unknown | Auth | ✅ `OrderInvoiceEndpointTest` |
| TC-FE-VIEW-004 | `GET /invoices/{id}` (admin) returns AdminInvoiceResource (no snapshot-field leak in customer resource) | Feature | ⬜ Not yet implemented |
| TC-FE-VIEW-005 | Customer resource omits `id`, `order_id`, `amount_paid`, `coupon_discount`, `promotion_discount`, hashes | Feature | ⬜ Not yet implemented |
| TC-FE-DL-001 | Owner downloads without any permission | 200 | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-002 | Non-owner with `view-invoice-download` downloads | 200 | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-003 | Non-owner with `view-invoice` only → 404 | Auth | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-004 | Non-owner with no permission → 404 | Auth | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-005 | Guest → 401 | Auth | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-006 | Super admin downloads | 200 | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-007 | No PDF → 404 `{ status, pdf_generated_at }` | Edge | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-DL-008 | Download returns JSON `{ url, invoice_number }` (not binary), url points to `storage/invoices/{pdf_path}` | Feature | ✅ `InvoiceDownloadPermissionTest` |
| TC-FE-PREVIEW-001 | **No PDF preview endpoint exists** — assert 404 for `/invoices/{uuid}/preview` and `/general/invoices/{uuid}/preview` (documents that preview is NOT provided) | Regression | ⬜ Not yet implemented |
| TC-FE-VERIFY-001 | Verify endpoint requires auth (401 guest) and throttle 5/min | Auth | ⬜ Not yet implemented |
| TC-FE-VERIFY-002 | Verify authentic path returns `{ authentic:true, order, qr_content }` — assert invoice field NOT relied upon (currently broken 500) | Feature | ⬜ Blocked by disabled `InvoiceResource` |

## Missing Coverage

- [x] Feature/API tests (added `InvoiceDownloadPermissionTest.php` — 18 tests)
- [x] PDF download flow tests with real PDF file on public disk
- [x] Timeline event count/order assertions (downloaded event)
- [x] Customer invoice view endpoint tests (`OrderInvoiceEndpointTest.php` — 7 tests)
- [x] PDF generation flow tests — **CLOSED 2026-08-22**: E2E executes the real DomPDF job (`AdminInvoiceEndToEndTest`; `AdminInvoiceRegenerateTest::test_regression_inv002_*`)
- [ ] No snapshot override field format validation
- [x] Debit note number series isolation (`DN` sequence asserted, sequential numbers) — `AdminInvoiceDebitNoteEndpointTest`
- [x] Archived → terminal state enforcement (regenerate rejected) — `AdminInvoiceRegenerateTest`
- [ ] No rate limiting test for verify (5/min) and download (30/min)
- [ ] No test asserting PDF preview endpoint absence (TC-FE-PREVIEW-001)
- [ ] No test for verify authentic-path 500 (disabled `InvoiceResource`) — TC-FE-VERIFY-002 blocked
- [x] **INV-001 regression:** malformed `{id}` (non-numeric / uuid) → 404 route-level, no TypeError/500; valid-missing numeric id → 404 — `AdminInvoiceShowTest`
- [x] **INV-002 regression:** regenerate from `ready` → 200 pdf_generating + job on `meem-medium` + timeline + real PDF execution → ready — `AdminInvoiceRegenerateTest`
- [x] **INV-003 regression:** correct/cancel missing invoice → 404 with NO `App\Models\Invoice` FQCN; existing-invoice business rules still 422 — Correct/Cancel suites
- [x] Index sort whitelist fallback + `limit` clamp to 100 — `AdminInvoiceIndexTest`
- [x] Cross-permission isolation (view-only cannot mutate) — `AdminInvoiceAuthTest`

---

## Regression Suites Added 2026-08-22 (production fixes INV-001/002/003)

| Suite | Tests | Notes |
|-------|------:|-------|
| `tests/Feature/Invoice/AdminInvoiceAuthTest.php` | 14 | 401 ×6 endpoints, 403 without permission, wrong-permission cannot unlock mutations |
| `tests/Feature/Invoice/AdminInvoiceIndexTest.php` | 7 | Contract shape `{data[],links{}}`, filters, search, sort whitelist fallback |
| `tests/Feature/Invoice/AdminInvoiceShowTest.php` | 5 | Resource contract, conditional download_url, INV-001 regressions |
| `tests/Feature/Invoice/AdminInvoiceRegenerateTest.php` | 5 | State transitions, attempts counter, queue `meem-medium`, INV-002 full chain |
| `tests/Feature/Invoice/AdminInvoiceCorrectTest.php` | 10 | Correction chain DB side effects, event+job after commit, validations, INV-003 |
| `tests/Feature/Invoice/AdminInvoiceCancelTest.php` | 6 | Persisted terminal state, idempotency, timeline count, INV-003 |
| `tests/Feature/Invoice/AdminInvoiceDebitNoteEndpointTest.php` | 7 | DN series/sequence, status guard, amount/reason validation |
| `tests/Feature/Invoice/AdminInvoiceEndToEndTest.php` | 1 | index→correct→real DomPDF→ready→cancel→timeline order→final states |

Shared bootstrap: `tests/Concerns/WithAdminInvoiceContext.php`.
