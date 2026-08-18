# INVOICE FRONTEND DOCUMENTATION — FINAL REPORT

**Date:** 2026-08-18
**Task:** Update all `api-desc/invoice/*` documentation to give frontend developers a clear contract distinguishing VIEW INVOICE DATA vs PDF PREVIEW vs DOWNLOAD PDF.
**Scope:** Documentation only. No application code, routes, permissions, or migrations were created or modified.

---

## 1. Files Updated

| File | Change |
|------|--------|
| `api-desc/invoice/api.md` | Full rewrite: View/Download/Preview matrix, real URL paths, per-endpoint auth & responses, real resource shapes, contradictions section |
| `api-desc/invoice/frontend.md` | View-vs-Download section, "PDF preview NOT CURRENTLY PROVIDED", download authorization matrix, Quick Reference, decision tree, updated Key Considerations |
| `api-desc/invoice/backend.md` | Correct route files/lines, real resource mapping, disabled `InvoiceResource` note, permission usage table, queue/job details |
| `api-desc/invoice/flow.md` | Corrected flows for list/my-invoices/show/verify/download, PDF-generation flow, corrected verify auth & throttle |
| `api-desc/invoice/README.md` | Correct prefixes, permissions (7), routes table, PDF-preview note |
| `api-desc/invoice/test-cases.md` | Existing test files listed, `TC-FE-VIEW-*`, `TC-FE-DL-*`, `TC-FE-VERIFY-*`, `TC-FE-PREVIEW-*` rows, corrected FT rows |
| `api-desc/invoice/changelog.md` | New `[1.2.0]` docs-only entry + explicit "No new PDF preview endpoint introduced" statement + updated Known Issues |
| `api-desc/invoice/qa.md` | A7 guest-verify now 401; rate-limit note 5/min |
| `api-desc/invoice/bug-report.md` | BUG-INV-007 throttle corrected to 5/min |
| `api-desc/invoice/jira-frontend.md` | Task 6/7 endpoints corrected to `/api/v1/general/...` + verify-auth caveat |

## 2. View vs Download vs Preview

| Capability | Endpoint | Auth | Returns |
|-----------|----------|------|---------|
| **VIEW invoice data** | `GET /api/v1/general/orders/invoice/{uuid}` (customer, owner) · `GET /api/v1/invoices/{id}` (admin, `view-invoice`) | Sanctum | JSON fields + immutable snapshot — no PDF |
| **VERIFY authenticity** | `GET /api/v1/general/invoices/verify/{uuid}` | `auth:sanctum` + `throttle:5,1` | `{ authentic, order, qr_content }` |
| **DOWNLOAD PDF** | `GET /api/v1/invoices/{uuid}/download` | owner OR `view-invoice-download` | JSON `{ url, invoice_number }` → fetch `url` for the PDF |
| **PDF PREVIEW** | **NOT CURRENTLY PROVIDED** | — | — |

## 3. PDF Preview Exists?

**NO — NOT CURRENTLY PROVIDED.**
- The route dump (source-verified via `invoice_route_dump.php`) lists **no** `/preview` route for invoices (or anywhere).
- `download()` returns a JSON body containing a storage URL — it does **not** stream PDF bytes and there is no inline-preview variant.
- Documentation instructs frontend to render the download `url` in an `<iframe>`/`<embed>` if preview is desired, **under the same download authorization rule** (owner OR `view-invoice-download`).
- `TC-FE-PREVIEW-001` records this as a regression assertion (404 expected for any `/preview` path).

## 4. Download Authorization (source + tests `InvoiceDownloadPermissionTest` — 18/18 green)

| User | Result |
|------|--------|
| Owner, no permission | 200 |
| Owner + `view-invoice` | 200 |
| Non-owner + `view-invoice-download` | 200 |
| Non-owner + `view-invoice` only | **404** |
| Non-owner, no permission | **404** |
| Guest | 401 |
| Super admin (seeded `view-invoice-download`) | 200 |

Rules documented: authorization is **inline** in the controller (route has `[auth:sanctum, throttle:30,1]`, no `permission:` middleware); failures return 404 (privacy, no existence leak); 404 also for missing PDF with `{ status, pdf_generated_at }`.

## 5. Frontend Flow

1. Customer lists invoices → `GET /api/v1/general/invoices/my-invoices` (Sanctum).
2. Customer views one → `GET /api/v1/general/orders/invoice/{uuid}` (owner-only, 403 otherwise).
3. Admin lists/views → `GET /api/v1/invoices` (`view-invoices`) / `GET /api/v1/invoices/{id}` or `/api/v1/general/invoices/uuid/{uuid}` (`view-invoice`).
4. PDF ready when `status=ready` and `pdf_path` present (poll after create/regenerate).
5. Download → `GET /api/v1/invoices/{uuid}/download` → JSON `{ url, invoice_number }` → fetch `url`.
6. Any preview UI → embed the `url` (same auth rule). No dedicated preview endpoint.

## 6. API Contract (source-verified)

- Admin routes: `packages/marvel/src/Rest/Routes.php` lines 390-399 under `api/v1`.
- Customer routes: `routes/api.php` lines 133-137 (+ line 126 `orders/invoice/{uuid}`) under `v1/general`.
- Resources: `AdminInvoiceCollection`/`AdminInvoiceResource` (admin list/show/correct/cancel), `CustomerInvoiceCollection`/`CustomerInvoiceResource` (my-invoices + order invoice), raw `DebitNote` model (debit-note 201).
- Statuses: `pending/generating/generated/pdf_generating/ready/failed/verified/downloaded/printed/corrected/cancelled/archived` with `InvoiceStatus::allowedTransitions()` state machine.
- PDF job: queue `meem-medium`, tries 3, backoff [30,120,300], timeout 120; listener queue `meem-high`, afterCommit, tries 5, backoff [10,30,60,120,300]; file `storage/app/public/invoices/{invoice_number with /→-}.pdf`.
- Requests: `CorrectInvoiceRequest` (reason required max 500; overrides.*) · `DebitNoteRequest` (amount min 0.01, reason max 500) · cancel uses inline `$request->validate`.

## 7. Documentation Consistency

- All 11 invoice doc files now use the **real** URLs (`/api/v1/general/invoices/...` customer; `/api/v1/invoices/...` admin).
- No doc equates `view-invoice` with download authorization (audit grep clean).
- No doc invents a `/preview` endpoint (all references state it does not exist).
- Verify auth/throttle corrected everywhere: `auth:sanctum` + `throttle:5,1`.
- Resource field tables match actual `AdminInvoiceResource` / `CustomerInvoiceResource` / `InvoiceSnapshotResource` output.

## 8. Source Verification

- Route dump executed fresh (`GET|HEAD`/`POST` list above) — matches documented paths/middleware exactly.
- `InvoiceResource::toArray()` confirmed commented out via direct invocation → `TypeError` (HTTP 500 on verify authentic path).
- `download()` source re-read: inline auth (owner OR `VIEW_INVOICE_DOWNLOAD`), first-download-only `downloaded_at`, `recordDownloaded` per download, JSON URL response.
- Download auth matrix backed by 18 passing tests + live HTTP verification from 1.1.0.

## 9. Unknowns

| Item | Status |
|------|--------|
| `storage` symlink presence in each deployment | UNKNOWN — documented as a requirement for the download URL to resolve |
| Whether `verify` being authenticated is intended or an oversight | UNKNOWN — reported as contradiction; source documented as-is |
| Whether `InvoiceResource` disablement is deliberate | UNKNOWN — flagged as HIGH-impact known issue |

## 10. FINAL VERDICT

**PASS WITH NON-BLOCKING ISSUES**

Documentation now correctly distinguishes VIEW INVOICE DATA / PDF PREVIEW / DOWNLOAD PDF, reflects the actual source, reports every contradiction found, and does not invent a preview endpoint. Non-blocking issues (all backend, none introduced by this task):

1. `InvoiceResource::toArray()` disabled → `verify()` returns HTTP 500 on the authentic path (frontend must not consume `data.invoice`).
2. Resource-emitted `download_url` points to `/api/v1/general/invoices/{uuid}/download`, which is not a registered route (frontend must use `/api/v1/invoices/{uuid}/download`).
3. `verify` currently requires `auth:sanctum` + `throttle:5,1` (older "public / 60 per min" design not implemented).
