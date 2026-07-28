# Invoice Module

## Overview

The Invoice module manages the full invoice lifecycle for the e-commerce platform — generation from paid orders, PDF generation, verification, correction, cancellation, and debit/credit notes. Invoices include data snapshots with integrity hashing for tamper-proof verification.

**API Prefix:** `/api/v1/invoices` (mixed public/authenticated)

**Key Capabilities:**
- Automatic invoice generation on payment success (via queued listener)
- Sequential invoice numbering with yearly series
- PDF generation (queued via DomPDF)
- Tamper-proof verification via snapshot hashing + HMAC verification hash
- Invoice correction (creates a new corrected invoice, marks original as corrected)
- Invoice cancellation with reason tracking
- Debit note issuance
- Public invoice verification endpoint
- Full event timeline tracking

## Key Files

| Layer | File |
|-------|------|
| Controller | `app/Http/Controllers/Api/InvoiceController.php` |
| Service | `app/Services/Invoice/InvoiceService.php` |
| Snapshot Service | `app/Services/Invoice/InvoiceSnapshotService.php` |
| Snapshot Validator | `app/Services/Invoice/InvoiceSnapshotValidator.php` |
| Integrity Service | `app/Services/Invoice/SnapshotIntegrityService.php` |
| Number Service | `app/Services/Invoice/InvoiceNumberService.php` |
| Timeline Service | `app/Services/Invoice/InvoiceTimelineService.php` |
| Debit Note Service | `app/Services/Invoice/DebitNoteService.php` |
| Credit Note Service | `app/Services/Invoice/CreditNoteService.php` |
| Model (Invoice) | `app/Models/Invoice.php` |
| Model (InvoiceTimeline) | `app/Models/InvoiceTimeline.php` |
| Model (InvoiceSequence) | `app/Models/InvoiceSequence.php` |
| Model (DebitNote) | `app/Models/DebitNote.php` |
| Model (CreditNote) | `app/Models/CreditNote.php` |
| Enum | `app/Enums/InvoiceStatus.php` |
| Event | `app/Events/InvoiceCreated.php` |
| Listener (PDF) | `app/Listeners/GenerateInvoiceListener.php` (queued) |
| Listener (Log) | `app/Listeners/LogInvoiceCreated.php` |
| Job | `app/Jobs/GenerateInvoicePdfJob.php` |
| Resource | `app/Http/Resources/Invoice/InvoiceResource.php` |
| Resource (Collection) | `app/Http/Resources/Invoice/InvoiceCollection.php` |
| Resource (Snapshot) | `app/Http/Resources/Invoice/InvoiceSnapshotResource.php` |
| Request (Correct) | `app/Http/Requests/Invoice/CorrectInvoiceRequest.php` |
| Request (Debit Note) | `app/Http/Requests/Invoice/DebitNoteRequest.php` |
| Migration (invoices) | `database/migrations/2026_07_16_000002_create_invoices_table.php` |
| Migration (sequences) | `database/migrations/2026_07_16_000001_create_invoice_sequences_table.php` |
| Migration (timeline) | `database/migrations/2026_07_28_000001_create_invoice_timeline_table.php` |
| Migration (lifecycle) | `database/migrations/2026_07_28_000005_add_invoice_lifecycle_columns.php` |
| Migration (uuid+verify) | `database/migrations/2026_07_27_082000_add_uuid_and_verification_to_invoices_table.php` |
| Migration (unique) | `database/migrations/2026_07_28_000006_remove_unique_order_id_from_invoices.php` |
| Routes | `routes/api.php` (lines 122-132) |
| Permissions | `packages/marvel/src/Enums/Permission.php` (lines 256-260) |
| Test | `tests/Unit/Invoice/InvoiceLifecycleTest.php` |
| PDF View | `resources/views/pdf/invoice.blade.php` |

## Dependencies

- **DomPDF** (`barryvdh/laravel-dompdf`) — PDF generation
- **Laravel Sanctum** — authentication
- **Spatie Permissions** — 6 permissions for access control
- **Order model** — invoice generated from paid orders
- **UUID** — auto-generated ordered UUIDs

## Permissions

| Permission | Required For |
|------------|-------------|
| `view-invoices` | GET /invoices |
| `view-invoice` | GET /invoices/{id}, GET /invoices/uuid/{uuid} |
| `regenerate-invoice` | POST /invoices/{id}/regenerate |
| `correct-invoice` | POST /invoices/{id}/correct |
| `cancel-invoice` | POST /invoices/{id}/cancel |
| `issue-debit-note` | POST /invoices/{id}/debit-note |

## Routes

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| GET | `/api/v1/invoices` | Sanctum (view-invoices) | List invoices (paginated, filterable, sortable) |
| GET | `/api/v1/invoices/{id}` | Sanctum (view-invoice) | Show invoice by ID |
| GET | `/api/v1/invoices/uuid/{uuid}` | Sanctum (view-invoice) | Show invoice by UUID |
| GET | `/api/v1/invoices/my-invoices` | Sanctum | List current user's invoices |
| GET | `/api/v1/invoices/verify/{uuid}` | Public (throttle:60,1) | Verify invoice authenticity |
| GET | `/api/v1/invoices/{uuid}/download` | Sanctum + owner/admin | Download invoice PDF |
| POST | `/api/v1/invoices/{id}/regenerate` | Sanctum (regenerate-invoice) | Regenerate PDF |
| POST | `/api/v1/invoices/{id}/correct` | Sanctum (correct-invoice) | Create corrected invoice |
| POST | `/api/v1/invoices/{id}/cancel` | Sanctum (cancel-invoice) | Cancel invoice |
| POST | `/api/v1/invoices/{id}/debit-note` | Sanctum (issue-debit-note) | Issue debit note |
