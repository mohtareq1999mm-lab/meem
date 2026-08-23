# Invoice Module — Changelog

## [1.9.0] — 2026-08-22

### Changed (customer PDF flow — signed URLs)
- **`view_url` / `download_url` are now temporary SIGNED URLs** (`URL::temporarySignedRoute`, 10 min TTL, names `general.invoices.view` / `general.invoices.download`). Frontend opens them directly in a browser — no `Authorization` header required. Ownership is enforced when the URLs are generated (owner-scoped endpoints only).
- Routes moved out of the Sanctum group and now use `signed` middleware: `GET /general/invoices/view/{uuid}` → streams the PDF **inline**; `GET /general/invoices/download/{uuid}` → streams it as an **attachment**. Tampered/expired/missing signatures → 403 (`InvalidSignatureException` mapped in the app handler).
- Both signed endpoints stream the **actual PDF binary** (`application/pdf`) — never a JSON pointer.

### Fixed
- Removed the "Scan to verify this invoice" + verification-URL block from the generated PDF blade. API verification (`verify/{uuid}`, verification hashes/URLs) is untouched.
- Physical-disk existence check added before streaming (DB path without file → clean 404).

### Changed (PDF engine)
- **DomPDF → mPDF 8.2** for invoice generation: correct Arabic shaping/RTL + mixed Arabic/English via Segoe UI fonts registered from `storage/app/fonts` (deployments must supply an Arabic-capable TTF there). Job/storage architecture unchanged.

## [1.8.0] — 2026-08-22

### Added
- **Universal `view_url` key in every invoice response** (list, detail, verify, correct, cancel, admin index/show). Ready-made viewer link so the frontend never builds URLs:
  - `CustomerInvoiceResource` / `CustomerInvoiceListResource` / `InvoiceResource` → `/api/v1/general/orders/{order_id}/invoice` (canonical customer viewer)
  - `AdminInvoiceResource` → `/api/v1/invoices/{id}` (admin detail)
- Regression assertions added across `MyInvoicesEndpointTest`, `OrderIdInvoiceEndpointTest`, `InvoiceVerifyEndpointTest`, `AdminInvoiceShowTest`.

### Documentation
- `HOW-TO-USE.md`, `api.md`, `frontend.md`, `backend.md`, `flow.md` updated with `view_url` in all field tables and examples.

## [1.7.0] — 2026-08-22

### Changed
- **`GET /general/invoices/my-invoices` is now a lightweight summary list.** New `CustomerInvoiceListResource` (wired via `CustomerInvoiceCollection::$collects`) exposes exactly: uuid, invoice_number, status, subtotal, shipping_price, total_discount, total, currency, payment_method, payment_gateway, generated_at, pdf_generated_at, verification_url, download_url. **No snapshot / order / customer / addresses / items / pricing / payment / metadata / audit.**
- The new resource's `download_url` points to the **registered** route `/api/v1/invoices/{uuid}/download` (the legacy resource emitted a non-existent `/general/.../download` path — reported separately; detail endpoint untouched).

### Unchanged
- Snapshot storage & detail endpoints: `GET /orders/{orderId}/invoice` still returns the full `snapshot` via `CustomerInvoiceResource`.
- Query/pagination (`limit` default 15 max 100), owner scoping, 401 guest behavior.

### Added
- `tests/Feature/Invoice/MyInvoicesEndpointTest.php` — 6 tests / 59 assertions (pagination, field whitelist, zero-snapshot proof incl. subfields across multiple invoices, ownership isolation, guest 401, detail & admin endpoints unchanged).

## [1.6.0] — 2026-08-22

### Fixed
- **Verify endpoint HTTP 500 resolved.** `InvoiceResource::toArray()` restored (was fully commented out while declaring `: array` → TypeError on the authentic path). `data.invoice` now returns full invoice fields; the old broken `download_url` field was intentionally not re-added.
- **Missing customer routes restored** in `routes/api.php`: `GET /general/invoices/verify/{uuid}` (`throttle:5,1`) and `GET /general/invoices/uuid/{uuid}` were absent from this branch's route file — only `my-invoices` survived.
- Known issues #7 closed; #4 (contradiction list) obsolete.

### Added
- `tests/Feature/Invoice/InvoiceVerifyEndpointTest.php` — 5 tests: guest 401, authentic 200 (+ verify_count/last_verified_at/timeline side effects), tampered 409, unknown uuid 404 envelope, repeated verification counting.

## [1.5.0] — 2026-08-22

### Removed
- **`GET /api/v1/general/orders/invoice/{uuid}`** (and `OrderController::invoice()`). Superseded by the canonical Order-ID endpoint introduced in 1.4.0; requests now fail routing with 404.
- Deleted obsolete suite `tests/Feature/OrderInvoiceEndpointTest.php`; its still-relevant order-list indicator coverage was ported into `OrderIdInvoiceEndpointTest::test_order_list_exposes_invoice_indicator_fields` (suite now **10 tests / 49 assertions, PASS**).

## [1.4.0] — 2026-08-22

### Added
- **Canonical customer invoice lookup by Order ID:** `GET /api/v1/general/orders/{orderId}/invoice` (`OrderController::invoiceByOrderId`, `whereNumber`). Ownership scoped inside the query (foreign order = same clean 404, no existence leak); pending orders return 404 (no invoice yet); resolves `latestInvoice` — the same document the customer order list advertises via `invoice_id`.
- Frontend contract simplified: Order ID in → Invoice out. No UUID extraction required.
- Feature suite: `tests/Feature/Order/OrderIdInvoiceEndpointTest.php` — 9 tests (pending→404, first-leave→200, lifecycle stability ×1, cancelled/completed first-leave, ownership isolation, missing order, payload parity with legacy route, correction resolution).

### Unchanged
- Legacy `GET /orders/invoice/{uuid}` retained for backward compatibility.
- `transactions[].invoice_id` remains a gateway reference string — unrelated to the Order's Invoice.

### Documentation
- Customer-view sections across `api.md`, `backend.md`, `frontend.md`, `flow.md`, `README.md` now document the canonical Order-ID route (uuid route removed in 1.5.0).

## [1.3.0] — 2026-08-22

### Fixed (production)
- **INV-001:** All admin invoice `{id}` routes now carry `whereNumber('id')`. Malformed ids fail routing with the standard **404** instead of surfacing a `TypeError`/500 from `show(int $id)`.
- **INV-002:** `InvoiceStatus` state machine now permits `READY → PDF_GENERATING`, aligning the enum with the documented regenerate contract ("allowed only from `failed|ready|generated`"). Regenerating a `ready` invoice previously crashed with an unhandled `RuntimeException` (HTTP 500); it now returns 200 + `pdf_generating` and dispatches `GenerateInvoicePdfJob` on `meem-medium`.
- **INV-003:** `correct()` and `cancel()` rethrow `ModelNotFoundException` ahead of the broad `catch (\RuntimeException)` (`ModelNotFoundException extends RuntimeException`). Missing invoices now return the handler's JSON **404** envelope without leaking the `App\Models\Invoice` FQCN; business-rule failures on existing invoices still return 422.

### Added (tests)
- Split feature suites: Auth(14), Index(7), Show+INV-001(5), Regenerate+INV-002(5), Correct(10), Cancel(6), DebitNote(7), E2E with real DomPDF execution(1)
- Shared bootstrap trait `tests/Concerns/WithAdminInvoiceContext.php`
- Repaired 2 stale unit expectations in `InvoiceLifecycleTest` (VERIFIED→CANCELLED is legal per enum)

### Regression result
- Invoice Feature suite **73/73** · Unit/Invoice **34/34** · Order-Invoice Endpoint **7/7**

### Known Issues updated
- Issue #2 (ModelNotFoundException → HTML page): **RESOLVED** for the API surface — the app exception handler renders all `/api/*` failures as JSON, and correct/cancel now explicitly rethrow to guarantee 404.
- Issues #7 (disabled `InvoiceResource` / verify TypeError 500) and #8 (`download_url` broken in resources) remain **OPEN**.

## [1.2.0] — 2026-08-18

### Changed (documentation only)
- Rewrote `api.md`, `frontend.md`, `backend.md`, `flow.md`, `README.md`, `test-cases.md` as a **frontend contract** that clearly separates:
  1. **View invoice data** — `GET /api/v1/general/orders/invoice/{uuid}` (customer, owner) and `GET /api/v1/invoices/{id}` (admin, `view-invoice`)
  2. **Verify authenticity** — `GET /api/v1/general/invoices/verify/{uuid}`
  3. **Download PDF** — `GET /api/v1/invoices/{uuid}/download` (owner OR `view-invoice-download`)
  4. **PDF preview** — **NOT CURRENTLY PROVIDED** (no `/preview` route exists)
- Corrected route paths to source: customer routes under `/api/v1/general/invoices/...`, admin routes under `/api/v1/invoices/...`
- Corrected route file references: admin routes in `packages/marvel/src/Rest/Routes.php` (lines 390-399), customer routes in `routes/api.php` (lines 133-137)
- Added the customer invoice-view endpoint `GET /api/v1/general/orders/invoice/{uuid}` (403 for non-owner)
- Documented actual resource mapping (`AdminInvoiceResource`/`AdminInvoiceCollection`, `CustomerInvoiceResource`/`CustomerInvoiceCollection`)
- Added full download authorization matrix (owner / `view-invoice` only / `view-invoice-download` / super admin / guest) to `frontend.md`
- Added frontend decision tree and Quick Reference (`TC-FE-*` contract rows in `test-cases.md`)
- Added `TC-FE-PREVIEW-001` asserting no PDF preview endpoint exists

### Reported Contradictions (source is authoritative)
1. **Verify is not public.** Actual: `auth:sanctum` + `throttle:5,1` (old docs said public / `throttle:60,1`).
2. **Customer URL prefix.** Actual: `/api/v1/general/invoices/...` (old docs used `/api/v1/invoices/...`).
3. **`download_url` broken in resources.** `AdminInvoiceResource`/`CustomerInvoiceResource` emit `/api/v1/general/invoices/{uuid}/download`, which is not a registered route. Real download route: `/api/v1/invoices/{uuid}/download`.
4. **`InvoiceResource::toArray()` disabled.** Fully commented out → `verify()` throws `TypeError` (HTTP 500) on the authentic path. `InvoiceCollection` likewise disabled.
5. **Route file mismatch.** Admin routes live in `packages/marvel/src/Rest/Routes.php`, not `routes/api.php` lines 122-132.

### Explicit Statement
- **No new PDF preview endpoint was introduced by this change.** This is a documentation-only update; no application code, endpoints, permissions, or migrations were added or modified.

## [1.1.0] — 2026-08-18

### Added
- Dedicated `view-invoice-download` permission for `GET /api/v1/invoices/{uuid}/download`
- Permission added to `Permission` enum (`VIEW_INVOICE_DOWNLOAD`), `PermissionSeeder` (all roles), and EN/AR `permissions.php` translations
- Non-owner download now requires `view-invoice-download`; `view-invoice` alone no longer authorizes download
- Full zero-trust permission test suite: `tests/Feature/Invoice/InvoiceDownloadPermissionTest.php` (18 tests, real PDF on public disk)
- Live HTTP verification (11 checks) against real DB + real PDF file

### Changed
- `InvoiceController::download()` authorization now checks `Permission::VIEW_INVOICE_DOWNLOAD` (owner OR permission)

## [1.0.0] — 2026-07-28

### Added
- Invoice API investigation documentation (`api-desc/invoice/`)
- Full API documentation for all 10 endpoints

### Known Issues

1. **Hardcoded response messages** — Success/error messages are literal English strings. No translation keys exist for invoice messages (except `ERROR_CREATING_INVOICE`).
2. **ModelNotFoundException not caught** — `show()`, `showByUuid()`, `download()`, `regenerate()`, `correct()`, `cancel()`, `issueDebitNote()` call `findOrFail()`/`firstOrFail()` without catching the exception. Non-existent records return HTML exception page instead of JSON 404.
3. **Duplicate status allowlists** — `cancelInvoice()` in service, `regenerate()`, and `issueDebitNote()` in controller maintain separate status arrays that duplicate the enum's state machine. Enum changes require manual sync.
4. **Inline validation in cancel()** — `cancel()` uses `$request->validate()` instead of a dedicated Form Request.
5. **Download returns 404 instead of 403** — Unauthorized download returns 404 (privacy) instead of 403, inconsistent with other modules.
6. **No rate limiting on auth group** — Only `verify` (5/min) and `download` (30/min) have throttles. List, show, my-invoices, regenerate, correct, cancel, debit-note have no limit.
7. **`InvoiceResource` disabled** — `app/Http/Resources/Invoice/InvoiceResource.php::toArray()` is fully commented out; `verify()` throws `TypeError` (HTTP 500) on the authentic path. `InvoiceCollection` is likewise disabled.
8. **Resource `download_url` points to a non-existent route** — `AdminInvoiceResource`/`CustomerInvoiceResource` emit `/api/v1/general/invoices/{uuid}/download`; the real route is `/api/v1/invoices/{uuid}/download`.
9. **No seeder** — No seed data for development.
10. **Self-referencing correction FK** — `correction_to_id` references `invoices.id` with `SET NULL` on delete — if the original invoice is ever deleted (blocked by restrict), corrections would lose their parent reference.

> **Resolved since 1.0.0:** Item 7 ("No feature/API tests") — feature tests now exist (`InvoiceDownloadPermissionTest.php` 18 tests, `OrderInvoiceEndpointTest.php` 7 tests). The `view-invoice-download` permission (1.1.0) and frontend contract docs (1.2.0) are tracked above.
