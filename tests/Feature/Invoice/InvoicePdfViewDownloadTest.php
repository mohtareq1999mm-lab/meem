<?php

namespace Tests\Feature\Invoice;

use App\Services\Invoice\InvoiceNumberService;
use App\Services\Invoice\InvoiceService;
use App\Services\Invoice\InvoiceSnapshotService;
use App\Services\Invoice\InvoiceSnapshotValidator;
use App\Services\Invoice\InvoiceTimelineService;
use App\Services\Invoice\SnapshotIntegrityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Tests\Concerns\CreatesTestTables;
use Tests\Concerns\WithInvoiceTables;
use Tests\TestCase;

/**
 * Binary PDF contract for the customer-facing VIEW + DOWNLOAD actions.
 *
 *   GET /api/v1/invoices/{uuid}/view      → 200 application/pdf, inline
 *   GET /api/v1/invoices/{uuid}/download  → 200 application/pdf, attachment
 *
 * Security chain (shared): Sanctum → find-by-uuid (404) → owner OR
 * view-invoice-download (404, no leak) → pdf_path present (404 "not yet generated")
 */
class InvoicePdfViewDownloadTest extends TestCase
{
    use CreatesTestTables, WithInvoiceTables, DatabaseTransactions;

    private const VIEW = '/api/v1/invoices/%s/view';
    private const DOWNLOAD = '/api/v1/invoices/%s/download';

    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        if (config('database.default') === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->createAllTestTables();
        $this->createInvoiceTables();
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->invoiceService = new InvoiceService(
            new InvoiceSnapshotService(),
            new InvoiceSnapshotValidator(),
            new SnapshotIntegrityService(),
            new InvoiceNumberService(),
            new InvoiceTimelineService(),
        );
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Pdf ' . $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
    }

    private function createReadyInvoiceWithPdf(User $user): \App\Models\Invoice
    {
        $order = \Marvel\Database\Models\Order::create([
            'user_id' => $user->id,
            'status' => 'processing',
            'price' => 100.0,
            'total_price' => 110.0,
            'shipping_price' => 10.0,
            'payment_method' => 'online',
            'payment_gateway' => 'myfatoorah',
            'fulfillment_type' => 'delivery',
            'shipping_method' => 'SCHEDULED',
        ]);

        $invoice = $this->invoiceService->generateFromOrder($order);

        $filename = str_replace('/', '-', $invoice->invoice_number) . '.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put('invoices/' . $filename, '%PDF-1.4 binary-test');
        $invoice->update(['pdf_path' => $filename, 'pdf_generated_at' => now(), 'status' => 'ready']);

        return $invoice->refresh();
    }

    private function actingAsOwner(): \App\Models\User
    {
        $user = $this->createUser(uniqid() . '@pdf.test');
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    // ─── VIEW ────────────────────────────────────────────────────────────────

    public function test_owner_can_view_own_pdf_inline(): void
    {
        $owner = $this->actingAsOwner();
        $invoice = $this->createReadyInvoiceWithPdf($owner);

        $response = $this->getJson(sprintf(self::VIEW, $invoice->uuid));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'inline; filename="' . $invoice->pdf_path . '"',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('%PDF-', $response->getContent());

        // VIEW must not count as a download.
        $this->assertNull($invoice->refresh()->downloaded_at);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('invoice_timeline')
            ->where('invoice_id', $invoice->id)->where('event', 'downloaded')->count());
    }

    public function test_view_requires_authentication(): void
    {
        $invoice = $this->createReadyInvoiceWithPdf($this->actingAsOwner());

        $this->getJson(sprintf(self::VIEW, $invoice->uuid))->assertStatus(401);
    }

    public function test_view_denied_for_other_customer_without_leak(): void
    {
        $this->actingAsOwner();
        $invoice = $this->createReadyInvoiceWithPdf($this->actingAsOwner());
        Sanctum::actingAs($this->createUser(uniqid() . '@other.test'), ['*']);

        $response = $this->getJson(sprintf(self::VIEW, $invoice->uuid));
        $response->assertStatus(404);
        $this->assertStringNotContainsString('invoice_number', $response->getContent());
    }

    public function test_view_returns_404_when_pdf_missing(): void
    {
        $owner = $this->actingAsOwner();
        $order = \Marvel\Database\Models\Order::create([
            'user_id' => $owner->id, 'status' => 'processing',
            'price' => 100.0, 'total_price' => 110.0, 'shipping_price' => 10.0,
            'payment_method' => 'online', 'payment_gateway' => 'myfatoorah',
            'fulfillment_type' => 'delivery', 'shipping_method' => 'SCHEDULED',
        ]);
        $invoice = $this->invoiceService->generateFromOrder($order);

        $response = $this->getJson(sprintf(self::VIEW, $invoice->uuid));
        $response->assertStatus(404)
            ->assertJsonPath('message', 'PDF not yet generated')
            ->assertJsonPath('data.status', 'generated');
    }

    // ─── DOWNLOAD ────────────────────────────────────────────────────────────

    public function test_owner_can_download_own_pdf_as_attachment(): void
    {
        $owner = $this->actingAsOwner();
        $invoice = $this->createReadyInvoiceWithPdf($owner);

        $response = $this->getJson(sprintf(self::DOWNLOAD, $invoice->uuid));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename="' . $invoice->pdf_path . '"',
            (string) $response->headers->get('Content-Disposition')
        );

        // Download bookkeeping preserved.
        $this->assertNotNull($invoice->refresh()->downloaded_at);
        $this->assertDatabaseHas('invoice_timeline', [
            'invoice_id' => $invoice->id,
            'event' => 'downloaded',
        ]);
    }

    public function test_download_requires_authentication(): void
    {
        $invoice = $this->createReadyInvoiceWithPdf($this->actingAsOwner());

        $this->getJson(sprintf(self::DOWNLOAD, $invoice->uuid))->assertStatus(401);
    }

    public function test_download_denied_for_other_customer_without_leak(): void
    {
        $this->actingAsOwner();
        $invoice = $this->createReadyInvoiceWithPdf($this->actingAsOwner());
        Sanctum::actingAs($this->createUser(uniqid() . '@o2.test'), ['*']);

        $response = $this->getJson(sprintf(self::DOWNLOAD, $invoice->uuid));
        $response->assertStatus(404);
        $this->assertStringNotContainsString('invoice_number', $response->getContent());
    }

    public function test_download_returns_404_when_pdf_missing(): void
    {
        $owner = $this->actingAsOwner();
        $order = \Marvel\Database\Models\Order::create([
            'user_id' => $owner->id, 'status' => 'processing',
            'price' => 100.0, 'total_price' => 110.0, 'shipping_price' => 10.0,
            'payment_method' => 'online', 'payment_gateway' => 'myfatoorah',
            'fulfillment_type' => 'delivery', 'shipping_method' => 'SCHEDULED',
        ]);
        $invoice = $this->invoiceService->generateFromOrder($order);

        $this->getJson(sprintf(self::DOWNLOAD, $invoice->uuid))
            ->assertStatus(404)
            ->assertJsonPath('message', 'PDF not yet generated');
    }

    public function test_repeated_downloads_preserve_first_downloaded_at(): void
    {
        $owner = $this->actingAsOwner();
        $invoice = $this->createReadyInvoiceWithPdf($owner);

        $first = $this->getJson(sprintf(self::DOWNLOAD, $invoice->uuid))->assertOk();
        $stamp = $invoice->refresh()->downloaded_at;

        $this->getJson(sprintf(self::DOWNLOAD, $invoice->uuid))->assertOk();

        $this->assertSame(
            $stamp?->format('Y-m-d H:i:s'),
            $invoice->refresh()->downloaded_at?->format('Y-m-d H:i:s')
        );
    }
}
