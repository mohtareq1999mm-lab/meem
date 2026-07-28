# Invoice Module — Backend Architecture

## Overview

The Invoice module is a comprehensive invoicing system with lifecycle management, tamper-proof verification, sequential numbering, PDF generation, correction/cancellation flows, and debit/credit note support. It follows a Service-oriented architecture with event-driven PDF generation.

## Endpoints

| Method | URL | Auth | Permission | Purpose |
|--------|-----|------|------------|---------|
| GET | `/api/v1/invoices` | Sanctum | `view-invoices` | List invoices (paginated, filterable, sortable) |
| GET | `/api/v1/invoices/{id}` | Sanctum | `view-invoice` | Show by ID |
| GET | `/api/v1/invoices/uuid/{uuid}` | Sanctum | `view-invoice` | Show by UUID |
| GET | `/api/v1/invoices/my-invoices` | Sanctum | — | Current user's invoices |
| GET | `/api/v1/invoices/verify/{uuid}` | Public | — | Verify authenticity |
| GET | `/api/v1/invoices/{uuid}/download` | Sanctum | Owner or `view-invoice` | Download PDF |
| POST | `/api/v1/invoices/{id}/regenerate` | Sanctum | `regenerate-invoice` | Regenerate PDF |
| POST | `/api/v1/invoices/{id}/correct` | Sanctum | `correct-invoice` | Create corrected invoice |
| POST | `/api/v1/invoices/{id}/cancel` | Sanctum | `cancel-invoice` | Cancel invoice |
| POST | `/api/v1/invoices/{id}/debit-note` | Sanctum | `issue-debit-note` | Issue debit note |

## Route Definitions

**File:** `routes/api.php` (lines 122-132)

```
Line 122: //======================== invoices ========================/
Line 123: Route::prefix('invoices')->group(function () {
Line 124:     Route::get('my-invoices', [InvoiceController::class, 'myInvoices'])->middleware('auth:sanctum');
Line 125:     Route::get('verify/{uuid}', [InvoiceController::class, 'verify'])->middleware('throttle:60,1');
Line 126:     Route::get('uuid/{uuid}', [InvoiceController::class, 'showByUuid'])->middleware('auth:sanctum');
Line 127:
Line 128:     Route::middleware(['auth:sanctum'])->group(function () {
Line 129:         Route::get('/', [InvoiceController::class, 'index']);
Line 130:         Route::get('{uuid}/download', [InvoiceController::class, 'download'])->whereUuid('uuid')->middleware('throttle:30,1');
Line 131:         Route::get('{id}', [InvoiceController::class, 'show']);
Line 132:         Route::post('{id}/regenerate', [InvoiceController::class, 'regenerate']);
Line 133:         Route::post('{id}/correct', [InvoiceController::class, 'correct']);
Line 134:         Route::post('{id}/cancel', [InvoiceController::class, 'cancel']);
Line 135:         Route::post('{id}/debit-note', [InvoiceController::class, 'issueDebitNote']);
Line 136:     });
Line 137: });
```

**Note:** The `uuid/{uuid}` route must be defined BEFORE `{id}` to prevent `"uuid"` being captured as `{id}`.

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
    → InvoiceCollection($invoices)

GET /invoices/my-invoices
  → InvoiceController@myInvoices(Request)
    → Invoice::where('user_id', auth()->id())
      → with('order') → orderBy('created_at', 'desc') → paginate(min(limit, 100))
    → InvoiceCollection($invoices)

GET /invoices/{id}
  → InvoiceController@show($id)
    → Invoice::with(['order.orderItems', 'transaction', 'user'])->findOrFail($id)
    → InvoiceResource::make($invoice)

GET /invoices/uuid/{uuid}
  → InvoiceController@showByUuid($uuid)
    → Invoice::with(['order.orderItems', 'transaction', 'user'])->where('uuid', $uuid)->firstOrFail()
    → InvoiceResource::make($invoice)

GET /invoices/verify/{uuid}
  → InvoiceController@verify($uuid)
    → InvoiceService::verifyInvoice($uuid)
      → Find invoice by UUID
      → Compute expected verification_hash (HMAC-SHA256 of snapshot_hash + app key)
      → Compare with stored verification_hash via hash_equals()
    → If null → 404
    → If tampered → 409 with { authentic: false, tampered: true }
    → If authentic → 200 with InvoiceResource + order data + QR content
      → Increment verify_count
      → Update last_verified_at / verified_at
      → Timeline: recordVerified

GET /invoices/{uuid}/download
  → InvoiceController@download($uuid)
    → Invoice::with('order')->where('uuid', $uuid)->firstOrFail()
    → Auth: owner check OR user can('view-invoice') → else 404
    → If no pdf_path → 404 with 'PDF not yet generated'
    → Update downloaded_at on first download
    → Timeline: recordDownloaded
    → Return: { url: storage/invoices/{pdf_path}, invoice_number }

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

### InvoiceResource (`app/Http/Resources/Invoice/InvoiceResource.php`)

| Field | Type | Condition |
|-------|------|-----------|
| id | int | Always |
| uuid | string | Always |
| order_id | int | Always |
| invoice_number | string | Always |
| status | string | Always |
| subtotal | float | Always |
| shipping_price | float | Always |
| coupon_discount | float | Always |
| promotion_discount | float | Always |
| total_discount | float | Always |
| total | float | Always |
| amount_paid | float | Always |
| currency | string | Always |
| payment_method | string | Always |
| payment_gateway | string | Always |
| snapshot_hash | string | Always |
| verification_hash | string | Always |
| pdf_generated_at | string (ISO8601) | Always |
| generated_at | string (ISO8601) | Always |
| generation_attempts | int | Always |
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
| verify_count | int | Always |
| created_at | string (ISO8601) | Always |
| verification_url | string | When uuid exists |
| qr_content | object | When uuid exists |
| download_url | string | When uuid AND pdf_path |
| snapshot | InvoiceSnapshotResource | When data exists |
| timeline | array (last 10) | When relation loaded |
| credit_notes_summary | object | When relation loaded |
| debit_notes_summary | object | When relation loaded |

### InvoiceSnapshotResource (`app/Http/Resources/Invoice/InvoiceSnapshotResource.php`)

Exposes the frozen snapshot data with formatted sections: order, customer, billing_address, shipping_address, fulfillment, pickup_location, items, pricing_breakdown, payment, metadata, audit.

### InvoiceCollection

Wraps `InvoiceResource` with pagination links.

## Event Flow

```
PaymentSucceeded (event from payment system)
  ↓
GenerateInvoiceListener (queued, high priority, 5 retries)
  ↓
InvoiceService::generateFromOrder($order)
  ↓
InvoiceCreated (dispatched after commit)
  ├── LogInvoiceCreated (sync — logs to Laravel log)
  └── GenerateInvoicePdfJob (queued, low priority, 3 retries, 120s timeout)
        ↓
      DomPDF renders from view → saves to storage/invoices/ → updates invoice status to 'ready'
```

## Permissions

**Enum:** `Marvel\Enums\Permission`

| Constant | Value |
|----------|-------|
| `VIEW_INVOICES` | `view-invoices` |
| `VIEW_INVOICE` | `view-invoice` |
| `REGENERATE_INVOICE` | `regenerate-invoice` |
| `CORRECT_INVOICE` | `correct-invoice` |
| `CANCEL_INVOICE` | `cancel-invoice` |
| `ISSUE_DEBIT_NOTE` | `issue-debit-note` |

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
| `routes/api.php` | Route definitions |
| `app/Http/Controllers/Api/InvoiceController.php` | Controller |
| `app/Http/Requests/Invoice/CorrectInvoiceRequest.php` | Correction validation |
| `app/Http/Requests/Invoice/DebitNoteRequest.php` | Debit note validation |
| `app/Http/Resources/Invoice/InvoiceResource.php` | API resource |
| `app/Http/Resources/Invoice/InvoiceCollection.php` | Paginated collection |
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
