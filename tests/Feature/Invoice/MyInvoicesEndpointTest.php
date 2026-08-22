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
use Tests\Concerns\CreatesTestTables;
use Tests\Concerns\WithInvoiceTables;
use Tests\TestCase;

/**
 * GET /api/v1/general/invoices/my-invoices — lightweight list contract.
 *
 * Proves the list returns ONLY invoice-level summary fields (no snapshot),
 * while pagination, ownership scoping and the DETAIL endpoint stay intact.
 */
class MyInvoicesEndpointTest extends TestCase
{
    use CreatesTestTables, WithInvoiceTables, DatabaseTransactions;

    private const URI = '/api/v1/general/invoices/my-invoices';

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
            'name' => 'List ' . $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
    }

    private function createOrderFor(User $user): \Marvel\Database\Models\Order
    {
        return \Marvel\Database\Models\Order::create([
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
    }

    // 1 + 2 + 3: 200, pagination works, summary fields present
    public function test_returns_paginated_lightweight_summaries(): void
    {
        $user = $this->createUser('a@mi.test');
        for ($i = 0; $i < 3; $i++) {
            $this->invoiceService->generateFromOrder($this->createOrderFor($user));
        }
        Sanctum::actingAs($user);

        $response = $this->getJson(self::URI . '?limit=2&page=1');

        $response->assertOk()->assertJsonPath('success', true);

        $items = $response->json('data.data');
        $this->assertCount(2, $items);                       // pagination applied
        $this->assertSame(3, $response->json('data.links.total'));

        foreach ($items as $item) {
            foreach ([ // required summary fields
                'uuid', 'invoice_number', 'status', 'subtotal', 'shipping_price',
                'total_discount', 'total', 'currency', 'payment_method',
                'payment_gateway', 'generated_at', 'pdf_generated_at',
                'verification_url', 'download_url',
            ] as $field) {
                $this->assertArrayHasKey($field, $item);
            }
        }
    }

    // 4 + 5 + 6: snapshot and all subfields absent — even across many invoices
    public function test_snapshot_and_all_subfields_are_never_emitted(): void
    {
        $user = $this->createUser('b@mi.test');
        for ($i = 0; $i < 4; $i++) {                        // multiple invoices
            $this->invoiceService->generateFromOrder($this->createOrderFor($user));
        }
        Sanctum::actingAs($user);

        $response = $this->getJson(self::URI)->assertOk();
        $raw = $response->getContent();

        $this->assertStringNotContainsString('"snapshot"', $raw);
        foreach (['customer', 'billing_address', 'shipping_address', 'fulfillment',
                  'pickup_location', 'items', 'pricing_breakdown', 'payment',
                  'metadata', 'audit', 'snapshot_version', 'snapshot_schema'] as $sub) {
            $this->assertStringNotContainsString('"' . $sub . '"', $raw);
        }
    }

    // 7: ownership filtering unchanged
    public function test_lists_only_the_authenticated_users_invoices(): void
    {
        $mine = $this->createUser('me@mi.test');
        $other = $this->createUser('other@mi.test');

        $myInvoice = $this->invoiceService->generateFromOrder($this->createOrderFor($mine));
        $this->invoiceService->generateFromOrder($this->createOrderFor($other));

        Sanctum::actingAs($mine);
        $response = $this->getJson(self::URI)->assertOk();

        $uuids = collect($response->json('data.data'))->pluck('uuid');
        $this->assertSame([$myInvoice->uuid], $uuids->all());
        $this->assertSame(1, $response->json('data.links.total'));
    }

    // 8: authentication behavior unchanged
    public function test_guest_gets_401(): void
    {
        $this->getJson(self::URI)->assertStatus(401);
    }

    // 9: customer DETAIL endpoint keeps the full snapshot
    public function test_detail_endpoint_still_includes_snapshot(): void
    {
        $user = $this->createUser('c@mi.test');
        $order = $this->createOrderFor($user);
        $invoice = $this->invoiceService->generateFromOrder($order);
        Sanctum::actingAs($user);

        $detail = $this->getJson('/api/v1/general/orders/' . $order->id . '/invoice')->assertOk();

        $this->assertSame($invoice->uuid, $detail->json('data.uuid'));
        $this->assertNotNull($detail->json('data.snapshot'));
        $this->assertArrayHasKey('pricing_breakdown', $detail->json('data.snapshot'));
    }

    // 10: admin endpoints unchanged (admin show still exposes hashes/snapshot)
    public function test_admin_show_endpoint_remains_unchanged(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('view-invoice', 'api');
        $admin = $this->createUser('admin@mi.test');
        $admin->givePermissionTo('view-invoice');
        $order = $this->createOrderFor($this->createUser('cust@mi.test'));
        $invoice = $this->invoiceService->generateFromOrder($order);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/v1/invoices/' . $invoice->id)->assertOk();

        $response->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.snapshot_hash', $invoice->snapshot_hash)
            ->assertJsonPath('data.verification_hash', $invoice->verification_hash);
        $this->assertNotNull($response->json('data.snapshot'));
    }
}
