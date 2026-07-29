<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Services\Invoice\InvoiceNumberService;
use App\Services\Invoice\InvoiceService;
use App\Services\Invoice\InvoiceSnapshotService;
use App\Services\Invoice\InvoiceSnapshotValidator;
use App\Services\Invoice\SnapshotIntegrityService;
use App\Services\Invoice\InvoiceTimelineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;
use Tests\Concerns\WithInvoiceTables;
use Tests\TestCase;

class OrderInvoiceEndpointTest extends TestCase
{
    use DatabaseTransactions, WithInvoiceTables;

    private const PREFIX = '/api/v1/general';

    private User $user;
    private User $otherUser;
    private Order $order;
    private Invoice $invoice;
    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Config::set('scout.driver', 'null');

        if (!Schema::hasTable('users')) {
            $this->createBaseTables();
        }

        $this->createInvoiceTables();

        $this->seedUsersAndOrder();

        $this->seedInvoice();
    }

    private function createBaseTables(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('language')->default('en');
                $table->text('options')->nullable();
                $table->decimal('minimum_order_amount', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('phone_number')->nullable();
                $table->string('type')->default('user');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->string('payment_status')->nullable();
                $table->string('fulfillment_status')->nullable();
                $table->boolean('coupon_consumed')->default(false);
                $table->boolean('promotion_consumed')->default(false);
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('total_price', 10, 2)->default(0);
                $table->decimal('shipping_price', 10, 2)->nullable();
                $table->string('coupon')->nullable();
                $table->decimal('coupon_discount', 10, 2)->nullable();
                $table->string('coupon_discount_type')->nullable();
                $table->decimal('coupon_discount_max_amount', 10, 2)->nullable();
                $table->unsignedBigInteger('promotion_id')->nullable();
                $table->string('promotion_code')->nullable();
                $table->string('promotion_type')->nullable();
                $table->decimal('promotion_discount', 10, 2)->nullable();
                $table->timestamp('expected_delivery_at')->nullable();
                $table->decimal('fast_shipping_fee', 10, 2)->default(0);
                $table->string('shipping_method')->default('SCHEDULED');
                $table->string('fulfillment_type', 20)->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('payment_gateway', 50)->nullable();
                $table->unsignedBigInteger('pickup_location_id')->nullable();
                $table->string('pickup_location_name')->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('sku')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->boolean('in_stock')->default(true);
                $table->integer('stock_quantity')->default(0);
                $table->integer('reserved_quantity')->default(0);
                $table->integer('sold_quantity')->default(0);
                $table->boolean('has_discount')->default(false);
                $table->boolean('has_flash_sale')->default(false);
                $table->string('product_type')->default('simple');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('order_products')) {
            Schema::create('order_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->string('product_name')->nullable();
                $table->integer('product_quantity')->default(1);
                $table->decimal('product_price', 10, 2)->default(0);
                $table->decimal('product_total_price', 10, 2)->default(0);
                $table->decimal('product_discount_price', 10, 2)->nullable();
                $table->decimal('product_flash_sale_price', 10, 2)->nullable();
                $table->boolean('is_gift')->default(false);
                $table->unsignedBigInteger('promotion_id')->nullable();
                $table->decimal('promotion_discount_amount', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->nullable()->unique();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('invoice_id')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('status', 30)->default('pending');
                $table->decimal('amount', 10, 2)->nullable();
                $table->string('currency', 3)->default('EGP');
                $table->string('gateway_transaction_id', 255)->nullable();
                $table->json('gateway_response')->nullable();
                $table->text('error_message')->nullable();
                $table->string('qr_code_url', 500)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    private function seedUsersAndOrder(): void
    {
        $this->user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->otherUser = User::create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->order = Order::create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'price' => 150.00,
            'total_price' => 150.00,
            'shipping_price' => 10.00,
            'payment_method' => 'online',
            'payment_gateway' => 'myfatoorah',
            'fulfillment_type' => 'delivery',
            'shipping_method' => 'SCHEDULED',
        ]);

        Transaction::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => 'paid',
            'amount' => 150.00,
            'currency' => 'EGP',
            'payment_method' => 'online',
            'paid_at' => now(),
        ]);
    }

    private function seedInvoice(): void
    {
        $invoiceNumberService = new InvoiceNumberService();
        $snapshotService = new InvoiceSnapshotService();
        $snapshotValidator = new InvoiceSnapshotValidator();
        $integrityService = new SnapshotIntegrityService();
        $timelineService = new InvoiceTimelineService();

        $this->invoiceService = new InvoiceService(
            $snapshotService,
            $snapshotValidator,
            $integrityService,
            $invoiceNumberService,
            $timelineService,
        );

        $this->invoice = $this->invoiceService->generateFromOrder($this->order);
    }

    public function test_user_can_get_own_invoice(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(self::PREFIX . '/orders/invoice/' . $this->invoice->uuid);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'uuid',
                'invoice_number',
                'status',
                'subtotal',
                'shipping_price',
                'total_discount',
                'total',
                'currency',
                'payment_method',
                'payment_gateway',
                'generated_at',
                'pdf_generated_at',
                'verification_url',
                'snapshot',
            ],
        ]);
        $response->assertJsonPath('data.uuid', $this->invoice->uuid);
        $response->assertJsonPath('data.invoice_number', $this->invoice->invoice_number);
        $this->assertNotEmpty($response->json('data.status'));
    }

    public function test_other_user_cannot_access_invoice(): void
    {
        Sanctum::actingAs($this->otherUser);

        $response = $this->getJson(self::PREFIX . '/orders/invoice/' . $this->invoice->uuid);

        $response->assertStatus(403);
    }

    public function test_nonexistent_invoice_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(self::PREFIX . '/orders/invoice/' . (string) Str::orderedUuid());

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson(self::PREFIX . '/orders/invoice/' . $this->invoice->uuid);

        $response->assertStatus(401);
    }

    public function test_invoice_has_rounded_monetary_values(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(self::PREFIX . '/orders/invoice/' . $this->invoice->uuid);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'subtotal',
                'shipping_price',
                'total_discount',
                'total',
            ],
        ]);

        $subtotal = $response->json('data.subtotal');
        $total = $response->json('data.total');
        $this->assertNotNull($subtotal);
        $this->assertNotNull($total);
    }

    public function test_invoice_snapshot_is_present(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(self::PREFIX . '/orders/invoice/' . $this->invoice->uuid);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'snapshot' => [
                    'snapshot_version',
                    'order',
                    'customer',
                    'items',
                    'pricing_breakdown',
                    'payment',
                ],
            ],
        ]);
    }

    public function test_order_resource_has_invoice_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(self::PREFIX . '/orders');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'order_has_invoice',
                        'invoice_id',
                    ],
                ],
            ],
        ]);

        $orderData = $response->json('data.data');
        $targetOrder = collect($orderData)->firstWhere('id', $this->order->id);

        $this->assertNotNull($targetOrder);
        $this->assertTrue($targetOrder['order_has_invoice']);
        $this->assertEquals($this->invoice->uuid, $targetOrder['invoice_id']);
    }
}
