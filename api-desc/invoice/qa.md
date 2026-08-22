# Invoice Module — QA Test Cases

## Test Files

- `tests/Unit/Invoice/InvoiceLifecycleTest.php` — 20 unit tests (423 lines)

---

## API Functionality Tests

| # | Test | Expected |
|---|------|----------|
| F1 | List invoices | 200, paginated structure |
| F2 | List invoices filtered by status | Filtered results |
| F3 | List invoices sorted by total | Correct sort order |
| F4 | Search invoices by invoice_number | Matching results |
| F5 | Show invoice by ID | 200, full InvoiceResource |
| F6 | Show invoice by UUID | 200, full InvoiceResource |
| F7 | My invoices (scoped to user) | 200, only user's invoices |
| F8 | Verify authentic invoice | 200, authentic:true |
| F9 | Verify tampered invoice | 409, tampered:true |
| F10 | Verify non-existent invoice | 404 |
| F11 | Download PDF (owner) | 200, PDF URL |
| F12 | Download PDF (admin with permission) | 200, PDF URL |
| F13 | Download PDF not yet generated | 404, status info |
| F14 | Regenerate PDF | 200, status:pdf_generating |
| F15 | Correct invoice | 200, correction created |
| F16 | Cancel invoice | 200, cancelled status |
| F17 | Issue debit note | 201, debit note created |

---

## Validation Tests

| # | Test | Expected |
|---|------|----------|
| V1 | Regenerate from wrong status (cancelled) | 422 |
| V2 | Correct missing reason | 422 |
| V3 | Correct with empty reason | 422 |
| V4 | Correct from wrong status (cancelled) | 422 |
| V5 | Cancel missing reason | 422 |
| V6 | Cancel from wrong status (archived) | 422 |
| V7 | Debit note missing amount | 422 |
| V8 | Debit note amount = 0 | 422 |
| V9 | Debit note from wrong status (archived) | 422 |
| V10 | Download unauthorized (not owner, no permission) | 404 |

---

## Authorization Tests

| # | Test | Expected |
|---|------|----------|
| A1 | Guest lists invoices | 401 |
| A2 | Guest shows invoice | 401 |
| A3 | Guest regenerates invoice | 401 |
| A4 | Guest corrects invoice | 401 |
| A5 | Guest cancels invoice | 401 |
| A6 | Guest issues debit note | 401 |
| A7 | Guest verifies invoice | 401 (verify now requires auth:sanctum) |
| A8 | No view-invoices permission | 403 |
| A9 | No view-invoice permission | 403 |
| A10 | No correct-invoice permission | 403 |
| A11 | No cancel-invoice permission | 403 |
| A12 | No issue-debit-note permission | 403 |
| A13 | No regenerate-invoice permission | 403 |

---

## Edge Case Tests

| # | Test | Expected |
|---|------|----------|
| E1 | Show non-existent invoice ID | 404 |
| E2 | Show non-existent UUID | 404 |
| E3 | Empty invoice list | 200, empty data |
| E4 | Limit exceeds max (200 → 100) | Capped to 100 |
| E5 | Verify invoice after data modification (tamper) | 409 |
| E6 | Concurrent correction + cancellation | One succeeds |
| E7 | Duplicate verify calls — verify_count increments | Count increases |
| E8 | Full state machine walkthrough | All valid transitions |
| E9 | Correct invoice then cancel correction | Correction cancelled |
| E10 | Multiple debit notes on same invoice | All issued |
| E11 | Download same invoice multiple times | downloaded_at only set once |

---

## Status Transition Matrix

| From | To | Expected |
|------|----|----------|
| pending | generating | ✓ OK |
| pending | cancelled | ✓ OK |
| generating | generated | ✓ OK |
| generating | failed | ✓ OK |
| generated | pdf_generating | ✓ OK |
| generated | cancelled | ✓ OK |
| pdf_generating | ready | ✓ OK |
| pdf_generating | failed | ✓ OK |
| ready | **pdf_generating** | ✓ OK — **INV-002 fix**: regenerate-from-ready is documented contract and now legal in the enum |
| ready | downloaded | ✓ OK |
| ready | archived | ✓ OK |
| ready | corrected | ✓ OK |
| ready | cancelled | ✓ OK |
| failed | pdf_generating | ✓ OK |
| cancelled | anything except archived | ✗ 422 |
| archived | anything | ✗ 422 |

---

## Executed Regression Results (2026-08-22)

| Suite | Tests | Result |
|-------|------:|--------|
| AdminInvoiceAuthTest (auth 401 ×6, permission 403 ×7, cross-permission isolation) | 14 | PASS |
| AdminInvoiceIndexTest (filters, search incl. order_number, sort whitelist fallback, limit clamp 100) | 7 | PASS |
| AdminInvoiceShowTest (+ INV-001 route-constraint regressions) | 5 | PASS |
| AdminInvoiceRegenerateTest (+ INV-002 ready→pdf_generating→job→real PDF→ready; queue meem-medium asserted) | 5 | PASS |
| AdminInvoiceCorrectTest (correction chain side effects + INV-003 404-no-leak) | 10 | PASS |
| AdminInvoiceCancelTest (terminal state, idempotency, INV-003) | 6 | PASS |
| AdminInvoiceDebitNoteEndpointTest (DN series, guards, validation) | 7 | PASS |
| AdminInvoiceEndToEndTest (index→correct→queued job executed via DomPDF→ready→cancel→timeline order) | 1 | PASS |
| InvoiceDownloadPermissionTest (pre-existing) | 18 | PASS |
| OrderInvoiceEndpointTest (pre-existing) | 7 | PASS |
| Unit/Invoice (2 stale expectations repaired against enum) | 34 | PASS |

**Total: 114 invoice-scope tests passing, 0 failures.**

---

## Integrity Verification Tests

| # | Test | Expected |
|---|------|----------|
| IV-1 | Snapshot hash matches after generation | hash_equals |
| IV-2 | Verification hash matches after generation | hash_equals |
| IV-3 | Verification fails after snapshot tampered (in DB) | 409 |
| IV-4 | Correction has unique snapshot_hash | Different from original |
| IV-5 | Verify returns null for missing UUID | null/404 |

---

## Missing Coverage

- [x] PDF generation job execution — **CLOSED**: E2E executes the real DomPDF job (`AdminInvoiceEndToEndTest`, `AdminInvoiceRegenerateTest::test_regression_inv002_*`)
- [ ] Storage disk availability check
- [ ] Snapshot version migration (current: 2.1.0, schema: 3)
- [ ] Large invoice data payloads (performance)
- [ ] Concurrent generation for same order (lockForUpdate test)
- [ ] Rate limiting: verify 5 req/min, download 30 req/min
- [x] `verify()` authentic-path HTTP test — **CLOSED 2026-08-22**: `InvoiceVerifyEndpointTest` (5 tests: 401 guest, 200 authentic + side effects, 409 tampered, 404 unknown, verify_count increments)
