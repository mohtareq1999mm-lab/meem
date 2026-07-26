<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\GatewayResult;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\CartItem;
use Marvel\Database\Models\Product;
use Marvel\Enums\ShippingMethod;
use App\Events\PaymentSucceeded;
use App\Events\PaymentFailed;
use App\Services\General\OrderService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PaymentCallbackStressTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIX = '/api/v1/general';

    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        \Illuminate\Support\Facades\Config::set('payment.default_currency', 'EGP');
        \Illuminate\Support\Facades\Config::set('payment.order_timeout_hours', 72);

        if (!Schema::hasTable('products')) {
            $this->createAllTables();
        }

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'type' => 'user',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 50.00,
            'status' => true,
            'in_stock' => true,
            'stock_quantity' => 100,
            'product_type' => 'simple',
        ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function createAllTables(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('language')->default('en');
            $table->text('options')->nullable();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->boolean('is_fast_shipping_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('estimated_days')->nullable();
            $table->decimal('free_shipping_over', 10, 2)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_quantity')->default(10);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('sold_quantity')->default(0);
            $table->boolean('is_fast_shipping_available')->default(false);
            $table->boolean('has_discount')->default(false);
            $table->string('discount_type')->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->string('product_type')->default('simple');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('type')->default('user');
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->string('phone_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->string('coupon')->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->boolean('is_gift')->default(false);
            $table->string('shipping_method')->nullable();
            $table->string('coupon_code')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name');
            $table->string('user_phone');
            $table->string('user_email');
            $table->text('address')->nullable();
            $table->string('shipping_method')->default('SCHEDULED');
            $table->string('status')->default('pending');
            $table->string('payment_method');
            $table->string('coupon')->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name');
            $table->integer('product_quantity');
            $table->decimal('product_price', 10, 2);
            $table->decimal('product_total_price', 10, 2);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('payment_method');
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency')->default('EGP');
            $table->string('invoice_id')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->text('gateway_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->string('event')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });
    }

    private function createOrderWithPendingTransaction(
        string $paymentMethod = 'cod',
        ?string $invoiceId = null,
        ?string $gateway = null,
    ): Order {
        $order = Order::create([
            'user_id' => $this->user->id,
            'name' => 'Test Order',
            'user_phone' => '01000000001',
            'user_email' => $this->user->email,
            'address' => json_encode(['address' => '123 Street']),
            'shipping_method' => 'SCHEDULED',
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->user->id,
            'payment_method' => $gateway ?? $paymentMethod,
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'EGP',
            'invoice_id' => $invoiceId,
        ]);

        return $order->fresh();
    }

    private function mockSuccessfulGateway(string $paymentId, ?string $invoiceId = null, float $amount = 100.00, int $times = 1): void
    {
        $mockGateway = \Mockery::mock(PaymentGatewayContract::class);
        $mockGateway->shouldReceive('verifyPayment')
            ->times($times)
            ->with($paymentId)
            ->andReturn(new GatewayResult(
                success: true,
                gatewayTransactionId: $invoiceId ?? $paymentId,
                amount: $amount,
                currency: 'EGP',
                status: 'paid',
                rawResponse: ['status' => 'paid'],
            ));

        $factoryMock = \Mockery::mock(PaymentGatewayFactory::class);
        $factoryMock->shouldReceive('make')
            ->times($times)
            ->with('myfatoorah')
            ->andReturn($mockGateway);

        $this->app->instance(PaymentGatewayFactory::class, $factoryMock);
    }

    /** @test */
    public function callback_without_payment_id_returns_400(): void
    {
        $response = $this->get(self::PREFIX . '/checkout/callback');
        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    /** @test */
    public function callback_with_unsupported_gateway_returns_500(): void
    {
        $invoiceId = 'INV-UNSUPPORTED';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'unknown_gateway');

        $factoryMock = \Mockery::mock(PaymentGatewayFactory::class);
        $factoryMock->shouldReceive('make')
            ->once()
            ->with('unknown_gateway')
            ->andThrow(new \App\Exceptions\UnsupportedGatewayException());

        $this->app->instance(PaymentGatewayFactory::class, $factoryMock);

        $response = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-unsupported');
        $response->assertStatus(500);
    }

    /** @test */
    public function callback_with_failed_verification_does_not_complete_order(): void
    {
        Event::fake([PaymentFailed::class]);
        $invoiceId = 'INV-FAILED';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'myfatoorah');

        $mockGateway = \Mockery::mock(PaymentGatewayContract::class);
        $mockGateway->shouldReceive('verifyPayment')
            ->once()
            ->with('payment-failed')
            ->andReturn(new GatewayResult(
                success: false,
                gatewayTransactionId: $invoiceId,
                status: 'failed',
                errorMessage: 'Payment declined',
                rawResponse: ['status' => 'failed'],
            ));

        $factoryMock = \Mockery::mock(PaymentGatewayFactory::class);
        $factoryMock->shouldReceive('make')
            ->once()
            ->with('myfatoorah')
            ->andReturn($mockGateway);

        $this->app->instance(PaymentGatewayFactory::class, $factoryMock);

        $response = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-failed');
        $response->assertStatus(302);

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('failed', $order->fresh()->transactions()->first()->status);

        Event::assertDispatched(PaymentFailed::class);
    }

    /** @test */
    public function callback_with_amount_mismatch_blocks_order(): void
    {
        Event::fake([PaymentFailed::class]);
        $invoiceId = 'INV-MISMATCH';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'myfatoorah');

        $mockGateway = \Mockery::mock(PaymentGatewayContract::class);
        $mockGateway->shouldReceive('verifyPayment')
            ->once()
            ->with('payment-mismatch')
            ->andReturn(new GatewayResult(
                success: true,
                gatewayTransactionId: $invoiceId,
                amount: 200.00,
                currency: 'EGP',
                status: 'paid',
                rawResponse: ['status' => 'paid'],
            ));

        $factoryMock = \Mockery::mock(PaymentGatewayFactory::class);
        $factoryMock->shouldReceive('make')
            ->once()
            ->with('myfatoorah')
            ->andReturn($mockGateway);

        $this->app->instance(PaymentGatewayFactory::class, $factoryMock);

        $response = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-mismatch');
        $response->assertStatus(302);

        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->transactions()->first()->status);

        Event::assertDispatched(PaymentFailed::class);
    }

    /** @test */
    public function callback_with_non_existent_transaction_redirects_success(): void
    {
        $this->mockSuccessfulGateway('payment-nonexistent', times: 1);

        $response = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-nonexistent');

        $response->assertStatus(302);
        $this->assertStringContainsString('payment/success', $response->headers->get('Location'));
    }

    /** @test */
    public function rapid_duplicate_callbacks_are_idempotent(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $invoiceId = 'INV-RAPID';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'myfatoorah');

        $this->mockSuccessfulGateway('payment-rapid', $invoiceId, 100.00, 3);

        $response1 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-rapid');
        $response2 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-rapid');
        $response3 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-rapid');

        $response1->assertStatus(302);
        $response2->assertStatus(302);
        $response3->assertStatus(302);

        $order = $order->fresh();
        $this->assertEquals('completed', $order->status);

        $transaction = $order->transactions()->first();
        $this->assertEquals('paid', $transaction->status);
        $this->assertNotNull($transaction->paid_at);

        Event::assertDispatched(PaymentSucceeded::class, 1);
    }

    /** @test */
    public function callback_after_order_already_completed_is_idempotent(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $invoiceId = 'INV-ALREADY';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'myfatoorah');

        $this->mockSuccessfulGateway('payment-already', $invoiceId, 100.00, 2);

        $response1 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-already');
        $response1->assertStatus(302);
        $this->assertEquals('completed', $order->fresh()->status);

        $response2 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-already');
        $response2->assertStatus(302);
        $this->assertEquals('completed', $order->fresh()->status);

        Event::assertDispatched(PaymentSucceeded::class, 1);
    }

    /** @test */
    public function callback_for_cancelled_order_does_not_resurrect(): void
    {
        Event::fake([PaymentSucceeded::class, PaymentFailed::class]);
        $invoiceId = 'INV-CANCELLED';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'myfatoorah');

        $order->update(['status' => 'cancelled']);

        $this->mockSuccessfulGateway('payment-cancelled', $invoiceId, 100.00, 1);

        $response = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-cancelled');
        $response->assertStatus(302);

        $order = $order->fresh();
        $this->assertNotEquals('completed', $order->status);
        $this->assertEquals('cancelled', $order->status);

        $transaction = $order->transactions()->first();
        $this->assertEquals('pending', $transaction->status);
    }

    /** @test */
    public function callback_with_lock_for_update_prevents_double_processing(): void
    {
        $invoiceId = 'INV-LOCK';
        $order = $this->createOrderWithPendingTransaction('online', $invoiceId, gateway: 'myfatoorah');

        $this->mockSuccessfulGateway('payment-lock', $invoiceId, 100.00, 2);

        $exception = null;
        DB::beginTransaction();
        try {
            $response1 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-lock');
            $response1->assertStatus(302);

            $orderAfterFirst = $order->fresh();
            $this->assertEquals('pending', $orderAfterFirst->status);

            DB::rollBack();

            $response2 = $this->get(self::PREFIX . '/checkout/callback?paymentId=payment-lock');
            $response2->assertStatus(302);

            $orderAfterSecond = $order->fresh();
            $this->assertEquals('completed', $orderAfterSecond->status);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
