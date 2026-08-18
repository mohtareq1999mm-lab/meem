# Invoice Module — Backend Architecture

## Overview

The Invoice module is a comprehensive invoicing system with lifecycle management, tamper-proof verification, sequential numbering, PDF generation, correction/cancellation flows, and debit/credit note support. It follows a Service-oriented architecture with event-driven PDF generation.

## Endpoints

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/invoices` | Sanctum | `view-invoices` | List invoices (paginated, filterable, sortable) |
| GET | `/api/v1/invoices/{id}` | Sanctum | `view-invoice` | Show by ID (admin) |
| GET | `/api/v1/invoices/{uuid}/download` | Sanctum + throttle:30,1 | Inline: owner OR `view-invoice-download` | Download PDF (returns JSON URL) |
| GET | `/api/v1/general/invoices/my-invoices` | Sanctum | — | Current user's invoices (customer) |
| GET | `/api/v1/general/invoices/uuid/{uuid}` | Sanctum | `view-invoice` | Show by UUID (admin) |
| GET | `/api/v1/general/invoices/verify/{uuid}` | Sanctum + throttle:5,1 | — | Verify authenticity |
| GET | `/api/v1/general/orders/invoice/{uuid}` | Sanctum | Owner-only (inline, 403 otherwise) | Customer view of one invoice |
| POST | `/api/v1/invoices/{id}/regenerate` | Sanctum | `regenerate-invoice` | Regenerate PDF |
| POST | `/api/v1/invoices/{id}/correct` | Sanctum | `correct-invoice` | Create corrected invoice |
| POST | `/api/v1/invoices/{id}/cancel` | Sanctum | `cancel-invoice` | Cancel invoice |
| POST | `/api/v1/invoices/{id}/debit-note` | Sanctum | `issue-debit-note` | Issue debit note |

> **Route note (source-verified):** Admin routes live in **`packages/marvel/src/Rest/Routes.php`** (lines 390-399), loaded under `api/v1` by `RestApiServiceProvider`. Customer routes live in **`routes/api.php`** (lines 133-137) inside the `v1/general` prefix. Older docs pointing to `routes/api.php` lines 122-132 for the admin group were wrong.

## Route Definitions

**File:** `packages/marvel/src/Rest/Routes.php` (lines 390-399, loaded under `api/v1`)

```
Line 390: Route::prefix('invoices')->group(function () {
Line 391:     Route::middleware(['auth:sanctum'])->group(function () {
Line 392:         Route::get('/', [InvoiceController::class, 'index']);
Line 393:         Route::get('{uuid}/download', [InvoiceController::class, 'download'])->whereUuid('uuid')->middleware('throttle:30,1');
Line 394:         Route::get('{id}', [InvoiceController::class, 'show']);
Line 395:         Route::post('{id}/regenerate', [InvoiceController::class, 'regenerate']);
Line 396:         Route::post('{id}/correct', [InvoiceController::class, 'correct']);
Line 397:         Route::post('{id}/cancel', [InvoiceController::class, 'cancel']);
Line 398:         Route::post('{id}/debit-note', [InvoiceController::class, 'issueDebitNote']);
Line 399:     });
Line 400: });
```

**File:** `routes/api.php` (lines 133-137, inside `Route::prefix('v1/general')`)

```
Line 133: Route::prefix('invoices')->group(function () {
Line 134:     Route::get('my-invoices', [InvoiceController::class, 'myInvoices']);
Line 135:     Route::get('verify/{uuid}', [InvoiceController::class, 'verify'])->middleware('throttle:5,1');
Line 136:     Route::get('uuid/{uuid}', [InvoiceController::class, 'showByUuid']);
Line 137: });
```

The customer group inherits `auth:sanctum` + `throttle:authenticated` from the enclosing group at `routes/api.php` line 113. The `orders/invoice/{uuid}` route is at `routes/api.php` line 126 in the same group.

> **Verify throttle note:** The older docs claimed `verify` was public with `throttle:60,1`. Actual source: `auth:sanctum` + `throttle:5,1`.

> **Route order note:** `uuid/{uuid}` is not in the same file/group as `{id}`, so the old "must be defined before `{id}`" warning does not apply to the current structure.

## Controller Flow

**File:** `app/Http/Controllers/Api/InvoiceController.php`

```
GET /invoices
  → InvoiceController@index(Request)
    → Invoice::query()
      → with(['order', 'user'])
      → when(search) → where(invoice_number LIKE ... OR order HAS order_number LIKE ...)
      → when(status) → where('status', ...)
      → when(order_id) → where('order_id', ...)
      → when(user_id) → where('user_id', ...)
      → when(invoice_series) → where('invoice_series', ...)
      → when(currency) → where('currency', ...)
      → when(from/to) → whereDate('created_at', ...)
      → when(sort_by) → orderBy(field, direction) else orderBy('created_at', 'desc')
      → paginate(min(limit, 100))
    → AdminInvoiceCollection($invoices)

GET /general/invoices/my-invoices
  → InvoiceController@myInvoices(Request)
    → Invoice::where('user_id', auth()->id())
      → with('order') → orderBy('created_at', 'desc') → paginate(min(limit, 100))
    → CustomerInvoiceCollection($invoices)

GET /invoices/{id}
  → InvoiceController@show($id)
    → Invoice::with(['order.orderItems', 'transaction', 'user'])->findOrFail($id)
    → AdminInvoiceResource::make($invoice)

GET /general/invoices/uuid/{uuid}
  → InvoiceController@showByUuid($uuid)
    → Invoice::with(['order.orderItems', 'transaction', 'user'])->where('uuid', $uuid)->firstOrFail()
    → AdminInvoiceResource::make($invoice)

GET /general/orders/invoice/{uuid}
  → OrderController@invoice($request, $uuid)
    → Invoice::where('uuid', $uuid)->firstOrFail()
    → Auth: invoice.order.user_id === auth()->id() → else AuthorizationException (403)
    → CustomerInvoiceResource::make($invoice)

GET /general/invoices/verify/{uuid}
  → InvoiceController@verify($uuid)
    → InvoiceService::verifyInvoice($uuid)
      → Find invoice by UUID
      → Compute expected verification_hash (hash('sha256', snapshot_hash . secret))
      → Compare with stored verification_hash via hash_equals()
    → If null → 404
    → If tampered → 409 with { authentic: false, tampered: true }
    → If authentic → 200 with InvoiceResource + order data + QR content
      → Increment verify_count
      → Update last_verified_at / verified_at
      → Timeline: recordVerified
    → KNOWN ISSUE: InvoiceResource::toArray() is commented out → TypeError → HTTP 500

GET /invoices/{uuid}/download
  → InvoiceController@download($uuid)
    → Invoice::with('order')->where('uuid', $uuid)->firstOrFail()
    → Auth: owner check OR user can('view-invoice-download') → else 404 (privacy)
    → If no pdf_path → 404 with 'PDF not yet generated' + { status, pdf_generated_at }
    → Update downloaded_at on first download
    → Timeline: recordDownloaded
    → Return: { url: url('storage/invoices/' . pdf_path), invoice_number }

POST /invoices/{id}/regenerate
  → InvoiceController@regenerate($id)
    → Invoice::findOrFail($id)
    → Status check: must be failed|ready|generated → else 422
    → Update status to pdf_generating, increment generation_attempts
    → Timeline: recordPdfRegenerated
    → Dispatch GenerateInvoicePdfJob
    → Return: { invoice_id, status: pdf_generating }

POST /invoices/{id}/correct
  → InvoiceController@correct(CorrectInvoiceRequest, $id)
    → try:
      → InvoiceService::correctInvoice($id, $overrides, $reason, $adminId)
        → DB::transaction:
          → Invoice::lockForUpdate()->findOrFail($id)
          → Validate status (generated/ready/verified/downloaded/printed)
          → Generate new invoice number
          → Deep-clone snapshot with overrides applied via data_set()
          → Create correction invoice (is_correction=true, correction_to_id=original)
          → Mark original as corrected
          → Both timeline events (corrected + generated)
          → afterCommit: dispatch InvoiceCreated + GenerateInvoicePdfJob
        → Return correction invoice
    → catch RuntimeException → 422

POST /invoices/{id}/cancel
  → InvoiceController@cancel(Request, $id)
    → $request->validate(['reason' => 'required|string|max:500'])
    → try:
      → InvoiceService::cancelInvoice($id, $reason, $adminId)
        → DB::transaction:
          → Invoice::lockForUpdate()->findOrFail($id)
          → Validate status (generated/ready/failed/corrected/verified/downloaded/printed)
          → Update: status=cancelled, cancelled_at, cancellation_reason
          → Timeline: recordCancelled
        → Return fresh invoice
    → catch RuntimeException → 422

POST /invoices/{id}/debit-note
  → InvoiceController@issueDebitNote(DebitNoteRequest, $id)
    → Invoice::findOrFail($id)
    → Status check: generated/ready/verified/downloaded/printed → else 422
    → DebitNoteService::generate($invoice, $amount, $reason, $adminId)
      → DB::transaction:
        → InvoiceNumberService::generateNext('DN') → DN-2026-000001
        → DebitNote::create(...)
    → Return 201
```

## Invoice Service

**File:** `app/Services/Invoice/InvoiceService.php`

**Dependencies:** InvoiceSnapshotService, InvoiceSnapshotValidator, SnapshotIntegrityService, InvoiceNumberService, InvoiceTimelineService

| Method | Description |
|--------|-------------|
| `generateFromOrder(Order $order)` | Transactional: check for existing, build snapshot, validate, compute hash, generate number, create invoice, timeline, afterCommit dispatch event + PDF job |
| `verifyInvoice(string $uuid)` | Find invoice, compute HMAC, compare hashes, return authentic/tampered/null |
| `correctInvoice(int $id, array $overrides, string $reason, int $adminId)` | Transactional: lock original, create correction with new number, mark original as corrected |
| `cancelInvoice(int $id, string $reason, int $adminId)` | Transactional: lock, validate, update status |

**Key Architecture:**
- Uses `InvoiceSnapshotService` to capture full order state at time of generation (immutable record)
- Uses `SnapshotIntegrityService` to compute SHA-256 hash of sorted snapshot JSON
- Uses `computeVerificationHash()` to add HMAC-SHA256 (appended app key) for tamper detection
- Uses `InvoiceNumberService` for gapless sequential numbering with yearly reset
- Uses `InvoiceTimelineService` for audit trail
- `InvoiceCreated` event dispatched after commit for listener + PDF job

## Snapshot Integrity

```
Snapshot (array)
  ↓
snapshot_hash = SHA-256(JSON(sort(snapshot)))
  ↓
verification_hash = SHA-256(snapshot_hash + APP_KEY)  [HMAC-style]
```

## Invoice Number Service

**File:** `app/Services/Invoice/InvoiceNumberService.php`

- Series: `INV` (invoice), `CN` (credit note), `DN` (debit note)
- Format: `{SERIES}-{YEAR}-{SEQUENCE_PADDED_6}`
- Example: `INV-2026-000001`
- Uses `InvoiceSequence` table with `lockForUpdate` to prevent duplicate numbers
- Yearly auto-reset (new year creates new sequence row)

## Invoice Status State Machine

**Enum:** `App\Enums\InvoiceStatus`

```
pending → generating, cancelled
generating → generated, failed
generated → pdf_generating, ready, failed, verified, downloaded, printed, corrected, cancelled
pdf_generating → ready, failed
ready → downloaded, printed, verified, failed, corrected, cancelled, archived
failed → pdf_generating, cancelled
verified → downloaded, printed, cancelled, archived
downloaded → printed, verified, cancelled, archived
printed → downloaded, verified, cancelled, archived
corrected → cancelled, archived
cancelled → archived
archived → (terminal)
```

Transitions are enforced at TWO levels:
1. **Enum level:** `InvoiceStatus::canTransitionTo()`
2. **Model level:** `Invoice::saving()` event checks `$from->canTransitionTo($to)` and throws `RuntimeException`

## Resources

### Resource mapping (source-verified)

| Endpoint | Resource |
|----------|----------|
| GET `/invoices` (list) | `AdminInvoiceCollection` |
| GET `/invoices/{id}` | `AdminInvoiceResource` |
| GET `/general/invoices/uuid/{uuid}` | `AdminInvoiceResource` |
| GET `/general/invoices/my-invoices` | `CustomerInvoiceCollection` |
| GET `/general/orders/invoice/{uuid}` | `CustomerInvoiceResource` |
| GET `/general/invoices/verify/{uuid}` | `InvoiceResource` (**disabled — see note below**) |
| POST `/invoices/{id}/correct` | `AdminInvoiceResource` (correction) |
| POST `/invoices/{id}/cancel` | `AdminInvoiceResource` (fresh invoice) |
| POST `/invoices/{id}/debit-note` | raw `DebitNote` model (no resource) |
| GET `/invoices/{uuid}/download` | JSON `{ url, invoice_number }` (no resource) |

> **`InvoiceResource` is disabled.** `app/Http/Resources/Invoice/InvoiceResource.php::toArray()` has its entire body commented out. Any endpoint that serializes it (`verify()`) throws `TypeError` → HTTP 500. `InvoiceCollection` (`InvoiceCollection.php`) is likewise disabled (it collects `InvoiceResource`).

### AdminInvoiceResource (`app/Http/Resources/Invoice/AdminInvoiceResource.php`)

| Field | Type | Condition |
|-------|------|-----------|
| id | int | Always |
| uuid | string | Always |
| order_id | int | Always |
| invoice_number | string | Always |
| status | string | Always |
| subtotal | float (rounded 2dp) | Always |
| shipping_price | float (rounded 2dp) | Always |
| coupon_discount | float (rounded 2dp) | Always |
| promotion_discount | float (rounded 2dp) | Always |
| total_discount | float (rounded 2dp) | Always |
| total | float (rounded 2dp) | Always |
| amount_paid | float (rounded 2dp) | Always |
| currency | string | Always |
| payment_method | string | Always |
| payment_gateway | string | Always |
| snapshot_hash | string | Always |
| verification_hash | string | Always |
| pdf_generated_at | string (ISO8601) | Always |
| generated_at | string (ISO8601) | Always |
| generation_attempts | int | Always (default 0) |
| last_generation_error | string|null | Always |
| is_correction | bool | Always |
| correction_reason | string|null | Always |
| corrected_at | string (ISO8601)|null | Always |
| cancelled_at | string (ISO8601)|null | Always |
| cancellation_reason | string|null | Always |
| verified_at | string (ISO8601)|null | Always |
| downloaded_at | string (ISO8601)|null | Always |
| printed_at | string (ISO8601)|null | Always |
| archived_at | string (ISO8601)|null | Always |
| last_verified_at | string (ISO8601)|null | Always |
| verify_count | int | Always (default 0) |
| created_at | string (ISO8601) | Always |
| verification_url | string | When uuid exists |
| qr_content | object | When uuid exists |
| download_url | string | When uuid AND pdf_path |
| snapshot | InvoiceSnapshotResource | When data exists |
| timeline | array (last 10) | When relation loaded |
| credit_notes_summary | object | When relation loaded |
| debit_notes_summary | object | When relation loaded |

> **Note:** `download_url` emits `/api/v1/general/invoices/{uuid}/download`, which is **not a registered route**. The real download route is `GET /api/v1/invoices/{uuid}/download`.

### CustomerInvoiceResource (`app/Http/Resources/Invoice/CustomerInvoiceResource.php`)

| Field | Type | Condition |
|-------|------|-----------|
| uuid | string | Always |
| invoice_number | string | Always |
| status | string | Always |
| subtotal | float (rounded 2dp) | Always |
| shipping_price | float (rounded 2dp) | Always |
| total_discount | float (rounded 2dp) | Always |
| total | float (rounded 2dp) | Always |
| currency | string | Always |
| payment_method | string | Always |
| payment_gateway | string | Always |
| generated_at | string (ISO8601) | Always |
| pdf_generated_at | string (ISO8601) | Always |
| verification_url | string | When uuid exists |
| download_url | string | When uuid AND pdf_path |
| snapshot | InvoiceSnapshotResource | When data exists |

> Customer resource exposes **no** `id`, `order_id`, `amount_paid`, `coupon_discount`, `promotion_discount`, hashes, or lifecycle timestamps.

### InvoiceSnapshotResource (`app/Http/Resources/Invoice/InvoiceSnapshotResource.php`)

Exposes the frozen snapshot data with formatted sections: order, customer, billing_address, shipping_address, fulfillment, pickup_location, items, pricing_breakdown, payment, metadata, audit.

### Collections

`AdminInvoiceCollection`, `CustomerInvoiceCollection`, and `InvoiceCollection` wrap their item resources with pagination links: `current_page`, `from`, `to`, `last_page`, `path`, `per_page`, `total`, `next_page_url`, `prev_page_url`, `last_page_url`, `first_page_url`.

## Event Flow

```
PaymentSucceeded (event from payment system)
  ↓
GenerateInvoiceListener (queued — queue `meem-high`, afterCommit, 5 retries, backoff [10,30,60,120,300])
  ↓
InvoiceService::generateFromOrder($order)
  ↓
InvoiceCreated (dispatched after commit)
  ├── LogInvoiceCreated (sync — logs to Laravel log)
  └── GenerateInvoicePdfJob (queue `meem-medium`, 3 tries, backoff [30,120,300], 120s timeout)
        ↓
      DomPDF renders from view → saves to storage/invoices/{filename}.pdf → updates invoice status to 'ready'
      On failure → status 'failed' + last_generation_error (job throws → 3 retries → failed())
```

PDF filename: `str_replace('/', '-', invoice_number) . '.pdf'` on the `public` disk (`storage/app/public/invoices/`).

## Permissions

**Enum:** `Marvel\Enums\Permission`

| Constant | Value |
|----------|-------|
| `VIEW_INVOICES` | `view-invoices` |
| `VIEW_INVOICE` | `view-invoice` |
| `VIEW_INVOICE_DOWNLOAD` | `view-invoice-download` |
| `REGENERATE_INVOICE` | `regenerate-invoice` |
| `CORRECT_INVOICE` | `correct-invoice` |
| `CANCEL_INVOICE` | `cancel-invoice` |
| `ISSUE_DEBIT_NOTE` | `issue-debit-note` |

**Permission usage (source-verified):**

| Permission | Middleware on | Inline (controller) |
|-----------|---------------|---------------------|
| `view-invoices` | `index` | — |
| `view-invoice` | `show`, `showByUuid` | — |
| `view-invoice-download` | — (route has no permission middleware) | `download()` — owner OR permission |
| `regenerate-invoice` | `regenerate` | — |
| `correct-invoice` | `correct` | — |
| `cancel-invoice` | `cancel` | — |
| `issue-debit-note` | `issueDebitNote` | — |

All seeded to all roles by `PermissionSeeder` (incl. `super_admin`).

## Constants & Translations

Only one constant exists in `packages/marvel/config/constants.php`:
```php
define('ERROR_CREATING_INVOICE', APP_NOTICE_DOMAIN . 'ERROR.ERROR_CREATING_INVOICE');
```

The controller uses hardcoded English strings:
- `'Invoice corrected successfully'`
- `'Invoice cancelled successfully'`
- `'Debit note issued successfully'`
- `'Invoice verification failed'`
- `'PDF not yet generated'`
- `'Cannot issue debit note for invoice in status: ...'`

**Missing:** No translation keys for invoice messages in `resources/lang/{en,ar}/message.php`.

## Dependencies

| File | Role |
|------|------|
| `routes/api.php` (lines 126, 133-137) | Customer/general routes (`v1/general`) |
| `packages/marvel/src/Rest/Routes.php` (lines 390-399) | Admin invoice routes (`api/v1`) |
| `packages/marvel/src/Providers/RestAPIServiceProvider.php` | Loads admin routes under `api/v1` |
| `app/Http/Controllers/Api/InvoiceController.php` | Controller |
| `app/Http/Controllers/Api/General/OrderController.php` | `invoice()` — customer invoice view |
| `app/Http/Requests/Invoice/CorrectInvoiceRequest.php` | Correction validation |
| `app/Http/Requests/Invoice/DebitNoteRequest.php` | Debit note validation |
| `app/Http/Resources/Invoice/AdminInvoiceResource.php` | Admin show/correct/cancel resource |
| `app/Http/Resources/Invoice/AdminInvoiceCollection.php` | Admin list collection |
| `app/Http/Resources/Invoice/CustomerInvoiceResource.php` | Customer invoice resource |
| `app/Http/Resources/Invoice/CustomerInvoiceCollection.php` | Customer list collection |
| `app/Http/Resources/Invoice/InvoiceResource.php` | **Disabled** (verify only — commented out) |
| `app/Http/Resources/Invoice/InvoiceCollection.php` | **Disabled** (collects InvoiceResource) |
| `app/Http/Resources/Invoice/InvoiceSnapshotResource.php` | Snapshot resource |
| `app/Services/Invoice/InvoiceService.php` | Core invoice service |
| `app/Services/Invoice/InvoiceSnapshotService.php` | Snapshot builder |
| `app/Services/Invoice/InvoiceSnapshotValidator.php` | Snapshot validation |
| `app/Services/Invoice/SnapshotIntegrityService.php` | Hash computation |
| `app/Services/Invoice/InvoiceNumberService.php` | Number generation |
| `app/Services/Invoice/InvoiceTimelineService.php` | Timeline/audit |
| `app/Services/Invoice/DebitNoteService.php` | Debit note generation |
| `app/Services/Invoice/CreditNoteService.php` | Credit note generation |
| `app/Models/Invoice.php` | Model |
| `app/Models/InvoiceTimeline.php` | Timeline model |
| `app/Models/InvoiceSequence.php` | Sequence model |
| `app/Models/DebitNote.php` | Debit note model |
| `app/Models/CreditNote.php` | Credit note model |
| `app/Enums/InvoiceStatus.php` | Status enum + state machine |
| `app/Events/InvoiceCreated.php` | Event |
| `app/Listeners/GenerateInvoiceListener.php` | Queued listener |
| `app/Listeners/LogInvoiceCreated.php` | Sync listener |
| `app/Jobs/GenerateInvoicePdfJob.php` | PDF generation job |
| `packages/marvel/src/Enums/Permission.php` | Permissions |
| `resources/views/pdf/invoice.blade.php` | PDF template |
| `tests/Unit/Invoice/InvoiceLifecycleTest.php` | Unit tests |
| `tests/Feature/Invoice/InvoiceDownloadPermissionTest.php` | Download permission feature tests (18) |
| `tests/Feature/OrderInvoiceEndpointTest.php` | Customer invoice view feature tests (7) |
