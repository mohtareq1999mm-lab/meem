# Invoice Module — Changelog

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
