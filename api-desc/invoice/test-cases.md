# Test Coverage — Invoice Module

## Existing Tests

**File:** `tests/Unit/Invoice/InvoiceLifecycleTest.php` (423 lines, 20 tests)

Uses `CreatesTestTables` + `DatabaseTransactions`.

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
| FT-005 | GET /invoices/{id} returns InvoiceResource | Feature | |
| FT-006 | GET /invoices/uuid/{uuid} returns InvoiceResource | Feature | |
| FT-007 | GET /invoices/my-invoices scoped to user | Feature | |
| FT-008 | GET /invoices/verify/{uuid} returns authentic=true | Feature | |
| FT-009 | GET /invoices/verify/{uuid} returns 409 for tampered | Feature | |
| FT-010 | GET /invoices/verify/{uuid} returns 404 | Feature | |
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
| FT-030 | Guest can verify (public) | 200 |
| FT-031 | No view-invoices permission → 403 | Auth |
| FT-032 | No correct-invoice permission → 403 | Auth |
| FT-033 | No cancel-invoice permission → 403 | Auth |
| FT-034 | No issue-debit-note permission → 403 | Auth |

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

## Missing Coverage

- [ ] No Feature/API tests (all tests are unit tests)
- [ ] No PDF generation flow tests (job is dispatched but not executed in test)
- [ ] No timeline event count/order assertions
- [ ] No snapshot override field format validation
- [ ] No debit note number series isolation
- [ ] No archived → terminal state enforcement
- [ ] No rate limiting test for verify (60/min) and download (30/min)
