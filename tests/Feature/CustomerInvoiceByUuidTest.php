<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\User;
use Tests\TestCase;

class CustomerInvoiceByUuidTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');

        if (!Schema::hasTable('users')) {
            $this->createTables();
        }
        if (!Schema::hasColumn('invoices', 'pdf_path')) {
            Schema::table('invoices', fn (Blueprint $t) => $t->string('pdf_path')->nullable());
        }

        $this->owner = User::create([
            'name' => 'Invoice Owner', 'email' => 'inv-owner@test.com',
            'password' => bcrypt('pass'), 'type' => 'user', 'is_active' => true,
        ]);
        $this->other = User::create([
            'name' => 'Other User', 'email' => 'inv-other@test.com',
            'password' => bcrypt('pass'), 'type' => 'user', 'is_active' => true,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable(); $t->string('password');
            $t->string('type')->default('user'); $t->boolean('is_active')->default(true);
            $t->rememberToken(); $t->timestamps(); $t->softDeletes();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('status')->default('pending'); $t->string('payment_status')->nullable();
            $t->string('fulfillment_status')->nullable();
            $t->decimal('price', 10, 2)->default(0); $t->decimal('total_price', 10, 2)->default(0);
            $t->decimal('shipping_price', 10, 2)->nullable(); $t->timestamps(); $t->softDeletes();
        });

        Schema::create('order_products', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->foreignId('product_id')->nullable(); $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->string('product_name')->nullable(); $t->string('product_sku')->nullable();
            $t->integer('product_quantity')->default(1); $t->decimal('product_price', 10, 2)->default(0);
            $t->decimal('product_total_price', 10, 2)->default(0); $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id(); $t->uuid('uuid')->nullable()->unique();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->unsignedBigInteger('transaction_id')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('invoice_number')->unique(); $t->string('invoice_series')->nullable();
            $t->unsignedBigInteger('sequence_number')->nullable(); $t->unsignedInteger('sequence_year')->nullable();
            $t->decimal('subtotal', 10, 2)->default(0); $t->decimal('shipping_price', 10, 2)->default(0);
            $t->decimal('coupon_discount', 10, 2)->default(0); $t->decimal('promotion_discount', 10, 2)->default(0);
            $t->decimal('total_discount', 10, 2)->default(0); $t->decimal('total', 10, 2)->default(0);
            $t->decimal('amount_paid', 10, 2)->default(0); $t->string('currency', 3)->default('EGP');
            $t->string('payment_method')->nullable(); $t->string('payment_gateway')->nullable();
            $t->string('status')->default('generated'); $t->json('data')->nullable();
            $t->string('snapshot_hash')->nullable(); $t->string('verification_hash')->nullable();
            $t->timestamp('generated_at')->nullable(); $t->string('generated_by')->nullable();
            $t->timestamps();
        });
    }

    private function createInvoiceFor(User $user): Invoice
    {
        $order = Order::create([
            'user_id' => $user->id,
            'price' => 100.00,
            'total_price' => 130.00,
            'shipping_price' => 30.00,
            'status' => 'completed',
            'payment_status' => 'payment-success',
        ]);

        return Invoice::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'user_id' => $user->id,
            'invoice_number' => 'INV-TEST-' . Str::random(6),
            'subtotal' => 100.00,
            'shipping_price' => 30.00,
            'total_discount' => 0.00,
            'total' => 130.00,
            'amount_paid' => 130.00,
            'currency' => 'EGP',
            'payment_method' => 'cod',
            'status' => 'generated',
            'generated_at' => now(),
            'generated_by' => 'system',
        ]);
    }

    /** @test */
    public function owner_can_show_her_invoice_by_uuid_with_customer_resource_shape()
    {
        Sanctum::actingAs($this->owner);
        $invoice = $this->createInvoiceFor($this->owner);

        $response = $this->getJson("/api/v1/general/invoices/show/uuid/{$invoice->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $invoice->uuid)
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.total', 130);

        // Canonical CustomerInvoiceResource fields present; admin-only snapshot hash absent
        $response->assertJsonStructure([
            'data' => ['uuid', 'invoice_number', 'status', 'subtotal', 'shipping_price', 'total', 'currency'],
        ]);
    }

    /** @test */
    public function another_user_gets_403_not_the_invoice()
    {
        Sanctum::actingAs($this->other);
        $invoice = $this->createInvoiceFor($this->owner);

        $this->getJson("/api/v1/general/invoices/show/uuid/{$invoice->uuid}")
            ->assertStatus(403);
    }

    /** @test */
    public function guest_is_unauthenticated()
    {
        $invoice = $this->createInvoiceFor($this->owner);

        $this->getJson("/api/v1/general/invoices/show/uuid/{$invoice->uuid}")
            ->assertStatus(401);
    }

    /** @test */
    public function unknown_uuid_returns_404()
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/general/invoices/show/uuid/' . Str::uuid())
            ->assertStatus(404);
    }

    /** @test */
    public function malformed_uuid_never_reaches_the_controller()
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/general/invoices/show/uuid/not-a-uuid')
            ->assertStatus(404);
    }
}
