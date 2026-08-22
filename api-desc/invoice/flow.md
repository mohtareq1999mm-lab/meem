# Request Flows — Invoice Module

> **Note:** Customer routes live under `/api/v1/general/...`. Admin routes live under `/api/v1/...` (defined in `packages/marvel/src/Rest/Routes.php`). Responses use `AdminInvoiceResource`/`AdminInvoiceCollection` for admin endpoints and `CustomerInvoiceResource`/`CustomerInvoiceCollection` for customer endpoints. `InvoiceResource` is currently disabled (see Flow 4).

## Flow 1: List Invoices (Admin)

```
Client → GET /api/v1/invoices?status=generated&sort_by=created_at&limit=15
         ↓
    [auth:sanctum] → [permission:view-invoices]
         ↓
    InvoiceController@index(Request)
         ↓
    Invoice::query()
      → with(['order', 'user'])
      → when(status) → where('status', 'generated')
      → orderBy('created_at', 'desc')  [default]
      → paginate(min(15, 100))
         ↓
    AdminInvoiceCollection($paginator)
         ↓
    Return: { status:200, message, success:true, data: { data[], links{} } }
```

## Flow 2: My Invoices (Customer)

```
Client → GET /api/v1/general/invoices/my-invoices?limit=15
         ↓
    [auth:sanctum] middleware
         ↓
    InvoiceController@myInvoices(Request)
         ↓
    Invoice::where('user_id', auth()->id())
      → with('order')
      → orderBy('created_at', 'desc')
      → paginate(min(15, 100))
         ↓
    CustomerInvoiceCollection($paginator)
         ↓
    CustomerInvoiceListResource per item   [v1.7.0 — summary-only, NO snapshot]
         ↓
    Return: { status:200, message, success:true, data:{data[], links{}} }
```

## Flow 3: Show Invoice

```
Client → GET /api/v1/invoices/1            (admin, permission: view-invoice)
    or   GET /api/v1/general/invoices/uuid/{uuid}   (admin, permission: view-invoice)
    or   GET /api/v1/general/orders/{orderId}/invoice (customer, canonical Order-ID lookup)
         [legacy /orders/invoice/{uuid} removed 2026-08-22]
         ↓
    [auth:sanctum] → permission middleware (admin) OR inline owner check (customer)
         ↓
    admin: Invoice::with(['order.orderItems', 'transaction', 'user'])->findOrFail($id)
           → AdminInvoiceResource::make($invoice)
    customer: Invoice::where('uuid', $uuid)->firstOrFail()
           → order.user_id !== auth()->id() → 403 (AuthorizationException)
           → CustomerInvoiceResource::make($invoice)
         ↓
    Return: { status:200, message, success:true, data }
```

## Flow 4: Verify Invoice

> **Source-verified:** Requires `auth:sanctum` + `throttle:5,1`. The older docs described this as public with `throttle:60,1` — that is no longer accurate.

```
Client → GET /api/v1/general/invoices/verify/550e8400-...
         ↓
    [auth:sanctum] → [throttle:5,1]
         ↓
    InvoiceController@verify($uuid)
         ↓
    InvoiceService::verifyInvoice($uuid)
         ↓
    Invoice::where('uuid', $uuid)->with(['order', 'user'])->first()
         ↓
    Not found? → Return: { status:404, message:NOT_FOUND, success:false }
         ↓
    Compute expected verification_hash:
      hash('sha256', snapshot_hash . $secret)
         ↓
    Compare with stored verification_hash via hash_equals()
         ↓
    Mismatch? → Return: { status:409, message, success:false, data: { authentic:false, tampered:true } }
         ↓
    Match:
      → $invoice->increment('verify_count')
      → $invoice->update(['last_verified_at' => now(), 'verified_at' => $invoice->verified_at ?? now()])
      → InvoiceTimelineService::recordVerified($invoice)
         ↓
    Return: { status:200, message, success:true, data: { authentic:true, invoice, order, qr_content } }

    KNOWN ISSUE: `invoice` is built from InvoiceResource::make(), but InvoiceResource::toArray()
    is fully commented out → TypeError → HTTP 500 on this path. qr_content = verification URL string.
```

## Flow 5: Download PDF

```
Client → GET /api/v1/invoices/550e8400-.../download
         ↓
    [auth:sanctum] → [throttle:30,1]
         ↓
    InvoiceController::download($uuid)
         ↓
    Invoice::with('order')->where('uuid', $uuid)->firstOrFail()
         ↓
    Authorization (inline, NO permission middleware):
      $invoice->user_id === auth()->id()  OR  auth()->user()->can('view-invoice-download')
      → Fail: Return 404 (privacy — don't reveal existence)
         ↓
    PDF exists? ($invoice->pdf_path)
      → Fail: Return 404 + { status, pdf_generated_at }
         ↓
    Update downloaded_at on first download
         ↓
    Timeline: recordDownloaded
         ↓
    Return: { status:200, message, success:true, data: { url: url('storage/invoices/' . pdf_path), invoice_number } }
```

## Flow 6: Generate Invoice (Automatic — via Event)

```
PaymentSucceeded event dispatched (from payment system)
         ↓
    GenerateInvoiceListener (queue: meem-high, afterCommit, 5 retries, backoff [10,30,60,120,300])
         ↓
    InvoiceService::generateFromOrder($order)
         ↓
    DB::beginTransaction()
      ├─ Check: Invoice::where('order_id', $order->id)->lockForUpdate()->first()
      │    └─ Exists? → Return existing (idempotent)
      │
      ├─ SnapshotService::buildFullSnapshot($order)
      │    └─ Captures: order data, customer, addresses, items, pricing, payment
      │
      ├─ SnapshotValidator::validate($snapshot)
      │
      ├─ IntegrityService::computeHash($snapshot) → sha256(json)
      │
      ├─ NumberService::generateNext() → INV-2026-000001
      │
      ├─ Invoice::create({...all fields..., status:'generated'})
      │
      ├─ TimelineService::recordGenerated($invoice)
      └─ DB::commit()
         ↓
    DB::afterCommit()
      ├─ InvoiceCreated::dispatch($invoice)
      │    ├─ LogInvoiceCreated (sync) — logs to Laravel log
      │    └─ GenerateInvoicePdfJob (queue: meem-medium, 3 tries, backoff [30,120,300], 120s timeout)
      │         ↓
      │       DomPDF::loadView('pdf.invoice', $invoice)  [A4 portrait, Arial, remote disabled]
      │         ↓
      │       filename = str_replace('/', '-', invoice_number) . '.pdf'
      │       Save to storage/app/public/invoices/{filename}.pdf (public disk)
      │         ↓
      │       Update invoice: status='ready', pdf_path, pdf_checksum (md5), pdf_generated_at,
      │         generation_attempts+1, last_generation_error=null
      │         ↓
      │       On failure: status='failed', last_generation_error, increment attempts, rethrow → retries
      │
      └─ Return invoice
```

## Flow 7: Correct Invoice

```
Client → POST /api/v1/invoices/1/correct
         Body: { reason: "Wrong total", overrides: { total: 95.00 } }
         ↓
    [auth:sanctum] → [permission:correct-invoice]
         ↓
    CorrectInvoiceRequest → validation
         ↓
    InvoiceController@correct($request, 1)
         ↓
    InvoiceService::correctInvoice(1, ['total'=>95], 'Wrong total', 1)
         ↓
    DB::beginTransaction()
      ├─ Invoice::lockForUpdate()->findOrFail(1)
      ├─ Status check: must be generated/ready/verified/downloaded/printed
      │    └─ Fail → throw RuntimeException
      │
      ├─ NumberService::generateNext() → INV-2026-000002
      │
      ├─ Clone snapshot with overrides applied via data_set()
      ├─ IntegrityService::computeHash(cloned snapshot)
      │
      ├─ Create CORRECTION invoice:
      │    is_correction=true, correction_to_id=original->id
      │    status='generated'
      │
      ├─ Update ORIGINAL invoice:
      │    status='corrected', corrected_at, correction_reason
      │
      ├─ Timeline: recordCorrected(original) + recordGenerated(correction)
      └─ DB::commit()
         ↓
    DB::afterCommit()
      ├─ InvoiceCreated::dispatch(correction)
      └─ GenerateInvoicePdfJob::dispatch(correction)
         ↓
    Return: { status:200, message, success:true, data: AdminInvoiceResource(correction) }
```

## Flow 8: Cancel Invoice

```
Client → POST /api/v1/invoices/1/cancel
         Body: { reason: "Order refunded" }
         ↓
    [auth:sanctum] → [permission:cancel-invoice]
         ↓
    Inline validation: reason required|string|max:500
         ↓
    InvoiceController@cancel($request, 1)
         ↓
    InvoiceService::cancelInvoice(1, 'Order refunded', 1)
         ↓
    DB::beginTransaction()
      ├─ Invoice::lockForUpdate()->findOrFail(1)
      ├─ Status check: must be generated/ready/failed/corrected/verified/downloaded/printed
      │    └─ Fail → throw RuntimeException
      │
      ├─ $invoice->update([
      │      'status' => 'cancelled',
      │      'cancelled_at' => now(),
      │      'cancellation_reason' => 'Order refunded',
      │    ])
      │
      ├─ Timeline: recordCancelled
      └─ DB::commit()
         ↓
    Return: { status:200, message, success:true, data: AdminInvoiceResource($invoice->fresh()) }
```

## Flow 9: Issue Debit Note

```
Client → POST /api/v1/invoices/1/debit-note
         Body: { amount: 25.00, reason: "Additional shipping" }
         ↓
    [auth:sanctum] → [permission:issue-debit-note]
         ↓
    DebitNoteRequest → validation (amount: required|min:0.01, reason: required|max:500)
         ↓
    InvoiceController@issueDebitNote($request, 1)
         ↓
    Invoice::findOrFail(1)
         ↓
    Status check: generated/ready/verified/downloaded/printed → else 422
         ↓
    DebitNoteService::generate($invoice, 25.00, 'Additional shipping', 1)
         ↓
    DB::beginTransaction()
      ├─ NumberService::generateNext('DN') → DN-2026-000001
      ├─ DebitNote::create({...})
      └─ DB::commit()
         ↓
    Return: { status:201, message, success:true, data: debitNote }
```

## Invoice Status State Machine

```
                    ┌──────────┐
                    │ PENDING  │
                    └────┬─────┘
                    │         │
                 ┌──v──┐  ┌──v─────────┐
                 │CANCELLED│ │GENERATING  │
                 └────────┘ └─────┬──────┘
                              │         │
                         ┌────v───┐  ┌──v───┐
                         │GENERATED│  │FAILED│
                         └──┬──┬──┘  └──┬───┘
                     │     │  │     │
              ┌──────v──┐ ┌v──────v──┐ ┌v───────────v──┐
              │PDF_GENERATING│ │READY│  │CORRECTED│
              └──────┬──────┘ └──┬──┘  └──┬──────────────┘
                     │           │        │
                ┌────v────┐  ┌──v──────────v──┐    ┌───────────┐
                │ FAILED  │  │VERIFIED │DOWNLOADED│PRINTED│
                └────┬────┘  └──┬──────────────────┘──┬──────────┘
                     │           │        │           │
                     │     ┌────v────────v───v────────v───┐
                     │     │         CANCELLED            │
                     │     └──────────────┬────────────────┘
                     │                    │
                     │              ┌────v─────┐
                     └──────────────│ ARCHIVED │  (terminal)
                                    └──────────┘
```
