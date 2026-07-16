<?php

namespace Tests\Feature;

use App\DTOs\GatewayResult;
use App\Exceptions\UnsupportedGatewayException;
use App\Jobs\PaymentReconciliationJob;
use App\Models\PaymentReconciliationResult;
use App\Services\Payment\Contracts\PaymentGatewayContract;
use App\Services\Payment\PaymentGatewayFactory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Transaction;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission as PermissionEnum;
use Marvel\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private PaymentGatewayContract $mockGateway;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.default_currency' => 'KWD']);

        $this->admin = $this->createSuperAdminUser();

        $this->mockGateway = $this->createMock(PaymentGatewayContract::class);
        $this->mockGateway->method('name')->willReturn('myfatoorah');
    }

    private function createSuperAdminUser(): User
    {
        $permissions = [
            PermissionEnum::SUPER_ADMIN,
            PermissionEnum::CREATE_PRODUCT,
            PermissionEnum::VIEW_PRODUCTS,
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'api');
        }

        $role = Role::create([
            'name' => RoleEnum::SUPER_ADMIN,
            'guard_name' => 'api',
            'display_name' => json_encode(['en' => 'Super Admin', 'ar' => 'مدير النظام']),
        ]);

        foreach ($permissions as $perm) {
            $role->givePermissionTo($perm);
        }

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
            'type' => 'admin',
            'phone_number' => '+1-555-0100',
        ]);

        $user->assignRole($role);

        foreach ($permissions as $perm) {
            $user->givePermissionTo($perm);
        }

        return $user;
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $this->admin->id,
            'name' => 'Test Order',
            'user_phone' => '+965 1234 5678',
            'user_email' => 'customer@example.com',
            'address' => json_encode(['street' => 'Test Street']),
            'total_price' => 100.00,
            'price' => 100.00,
            'status' => 'completed',
            'payment_method' => 'online',
            'payment_gateway' => 'myfatoorah',
        ], $overrides));
    }

    private function createTransaction(string $status = 'paid', array $overrides = []): Transaction
    {
        $order = $overrides['order'] ?? $this->createOrder();

        return Transaction::create(array_merge([
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'payment_method' => 'myfatoorah',
            'invoice_id' => 12345,
            'status' => $status,
            'amount' => 100.00,
            'currency' => 'KWD',
            'gateway_transaction_id' => 'TST-' . strtoupper(uniqid()),
        ], $overrides));
    }

    private function createGatewayResult(array $overrides = []): GatewayResult
    {
        return new GatewayResult(...array_merge([
            'success' => true,
            'gatewayTransactionId' => 'TST-123',
            'amount' => 100.00,
            'currency' => 'KWD',
            'status' => 'Paid',
        ], $overrides));
    }

    private function mockFactoryReturns(GatewayResult $result): void
    {
        $this->mockGateway->method('verifyPayment')->willReturn($result);

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('make')->with('myfatoorah')->willReturn($this->mockGateway);

        $this->app->instance(PaymentGatewayFactory::class, $factory);
    }

    private function mockFactoryThrows(\Throwable $e): void
    {
        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('make')->willThrowException($e);

        $this->app->instance(PaymentGatewayFactory::class, $factory);
    }

    private function mockFactoryReturnsFor(string $gateway, ?GatewayResult $result = null, ?\Throwable $exception = null): void
    {
        $this->mockGateway = $this->createMock(PaymentGatewayContract::class);
        $this->mockGateway->method('name')->willReturn($gateway);

        if ($result) {
            $this->mockGateway->method('verifyPayment')->willReturn($result);
        }
        if ($exception) {
            $this->mockGateway->method('verifyPayment')->willThrowException($exception);
        }

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('make')->willReturnCallback(function (string $name) use ($gateway) {
            if ($name === $gateway) {
                return $this->mockGateway;
            }
            throw new UnsupportedGatewayException($name);
        });

        $this->app->instance(PaymentGatewayFactory::class, $factory);
    }

    private function runJob(): void
    {
        $job = new PaymentReconciliationJob();
        $job->handle($this->app->make(PaymentGatewayFactory::class));
    }

    // =========================================================================
    // Successful Reconciliation
    // =========================================================================

    /** @test */
    public function successful_reconciliation_creates_no_mismatches(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    /** @test */
    public function reconciliation_checks_multiple_transactions(): void
    {
        $tx1 = $this->createTransaction('paid');
        $tx2 = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $tx1->gateway_transaction_id,
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Amount Mismatch
    // =========================================================================

    /** @test */
    public function amount_mismatch_creates_reconciliation_record(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'amount' => 95.00,
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();

        $this->assertEquals(1, PaymentReconciliationResult::count());
        $this->assertNotNull($result);
        $this->assertEquals('amount', $result->mismatch_type);
        $this->assertEquals('100', $result->expected_value);
        $this->assertEquals('95', $result->actual_value);
        $this->assertEquals($transaction->id, $result->transaction_id);
        $this->assertEquals($transaction->order_id, $result->order_id);
        $this->assertEquals('myfatoorah', $result->gateway);
    }

    /** @test */
    public function amount_within_tolerance_does_not_create_mismatch(): void
    {
        $transaction = $this->createTransaction('paid', ['order' => $this->createOrder(['total_price' => 100.005])]);

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'amount' => 100.01,
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    /** @test */
    public function amount_mismatch_ignored_when_gateway_amount_is_null(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'amount' => null,
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Currency Mismatch
    // =========================================================================

    /** @test */
    public function currency_mismatch_creates_reconciliation_record(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'currency' => 'USD',
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();

        $this->assertEquals(1, PaymentReconciliationResult::count());
        $this->assertEquals('currency', $result->mismatch_type);
    }

    /** @test */
    public function currency_mismatch_ignored_when_gateway_currency_is_null(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'currency' => null,
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Payment Status Mismatch
    // =========================================================================

    /** @test */
    public function payment_status_mismatch_when_gateway_says_paid_but_local_is_pending(): void
    {
        $transaction = $this->createTransaction('pending');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Paid',
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();

        $this->assertEquals(1, PaymentReconciliationResult::count());
        $this->assertEquals('payment_status', $result->mismatch_type);
    }

    /** @test */
    public function payment_status_mismatch_when_gateway_says_failed_but_local_is_paid(): void
    {
        $order = $this->createOrder(['status' => 'pending']);
        $transaction = $this->createTransaction('paid', ['order' => $order]);

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Failed',
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();

        $this->assertEquals(1, PaymentReconciliationResult::count());
        $this->assertEquals('payment_status', $result->mismatch_type);
    }

    /** @test */
    public function payment_status_match_when_both_are_paid(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Paid',
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    /** @test */
    public function payment_status_ignored_when_gateway_status_is_unknown(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Unknown',
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Order Status Mismatch
    // =========================================================================

    /** @test */
    public function order_status_mismatch_when_gateway_says_paid_but_order_not_completed(): void
    {
        $order = $this->createOrder(['status' => 'pending']);
        $transaction = $this->createTransaction('paid', ['order' => $order]);

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Paid',
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();

        $this->assertEquals(1, PaymentReconciliationResult::count());
        $this->assertEquals('order_status', $result->mismatch_type);
        $this->assertEquals('pending', $result->expected_value);
        $this->assertStringContainsString('paid', strtolower($result->actual_value));
    }

    /** @test */
    public function order_status_mismatch_when_gateway_says_failed_but_order_completed(): void
    {
        $transaction = $this->createTransaction('pending');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Failed',
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();

        $this->assertEquals(1, PaymentReconciliationResult::count());
        $this->assertEquals('order_status', $result->mismatch_type);
    }

    /** @test */
    public function order_status_match_when_gateway_paid_and_order_completed(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'status' => 'Paid',
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Offline Payment Skipped
    // =========================================================================

    /** @test */
    public function offline_payments_without_gateway_transaction_id_are_skipped(): void
    {
        $order = $this->createOrder(['payment_method' => 'cod', 'payment_gateway' => 'CASH_ON_DELIVERY']);
        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'payment_method' => 'cod',
            'invoice_id' => 99999,
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'KWD',
            'gateway_transaction_id' => null,
        ]);

        $this->mockFactoryReturns($this->createGatewayResult());

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    /** @test */
    public function failed_transactions_are_skipped(): void
    {
        $transaction = $this->createTransaction('failed');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
        ]));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Unsupported Gateway Skipped
    // =========================================================================

    /** @test */
    public function unsupported_gateway_is_skipped(): void
    {
        $order = $this->createOrder(['payment_gateway' => 'unsupported_gateway']);
        $transaction = $this->createTransaction('paid', [
            'order' => $order,
            'payment_method' => 'unsupported_gateway',
        ]);

        $this->mockFactoryThrows(new UnsupportedGatewayException('unsupported_gateway'));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Gateway Exception / Timeout
    // =========================================================================

    /** @test */
    public function gateway_timeout_is_handled_gracefully(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturnsFor('myfatoorah', null, new \Illuminate\Http\Client\ConnectionException('Connection timed out'));

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    /** @test */
    public function gateway_exception_does_not_stop_job(): void
    {
        Log::spy();

        $tx1 = $this->createTransaction('paid');
        $tx2 = $this->createTransaction('paid');

        $this->mockGateway->method('verifyPayment')->willReturnCallback(function (string $id) use ($tx1) {
            if ($id === $tx1->gateway_transaction_id) {
                throw new \RuntimeException('Gateway unavailable');
            }
            return new GatewayResult(
                success: true,
                gatewayTransactionId: $id,
                amount: 100.00,
                currency: 'KWD',
                status: 'Paid',
            );
        });

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('make')->willReturn($this->mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $this->runJob();

        $this->assertEquals(0, PaymentReconciliationResult::count());

        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    /** @test */
    public function job_continues_after_exception_on_one_transaction(): void
    {
        $tx1 = $this->createTransaction('paid');
        $tx2 = $this->createTransaction('paid');

        $called = [];
        $this->mockGateway->method('verifyPayment')->willReturnCallback(function (string $id) use ($tx1, &$called) {
            $called[] = $id;
            if ($id === $tx1->gateway_transaction_id) {
                throw new \RuntimeException('Gateway error');
            }
            return new GatewayResult(
                success: true,
                gatewayTransactionId: $id,
                amount: 100.00,
                currency: 'KWD',
                status: 'Paid',
            );
        });

        $factory = $this->createMock(PaymentGatewayFactory::class);
        $factory->method('make')->willReturn($this->mockGateway);
        $this->app->instance(PaymentGatewayFactory::class, $factory);

        $this->runJob();

        $this->assertCount(2, $called);
        $this->assertEquals(0, PaymentReconciliationResult::count());
    }

    // =========================================================================
    // Multiple Mismatch Types on Same Transaction
    // =========================================================================

    /** @test */
    public function multiple_mismatches_on_same_transaction_all_recorded(): void
    {
        $order = $this->createOrder(['status' => 'pending', 'total_price' => 100.00]);
        $transaction = $this->createTransaction('pending', ['order' => $order]);

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'amount' => 90.00,
            'currency' => 'USD',
            'status' => 'Paid',
        ]));

        $this->runJob();

        $mismatches = PaymentReconciliationResult::all();
        $types = $mismatches->pluck('mismatch_type')->toArray();

        $this->assertCount(4, $mismatches);
        $this->assertContains('amount', $types);
        $this->assertContains('currency', $types);
        $this->assertContains('payment_status', $types);
        $this->assertContains('order_status', $types);
    }

    // =========================================================================
    // Dashboard Summary Endpoint
    // =========================================================================

    /** @test */
    public function dashboard_reconciliation_endpoint_returns_summary(): void
    {
        Sanctum::actingAs($this->admin);

        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'amount' => 50.00,
        ]));

        $this->runJob();

        $response = $this->getJson('/api/v1/dashboard/reconciliation');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_checked',
                'total_mismatches',
                'pending_mismatches',
                'resolved_mismatches',
                'last_run',
            ],
        ]);
        $response->assertJsonPath('data.total_mismatches', 1);
        $response->assertJsonPath('data.total_checked', 1);
        $response->assertJsonPath('data.pending_mismatches', 1);
        $response->assertJsonPath('data.resolved_mismatches', 0);
        $response->assertJsonPath('success', true);
    }

    /** @test */
    public function reconciliation_endpoint_returns_zero_when_no_data(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/dashboard/reconciliation');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_checked', 0);
        $response->assertJsonPath('data.total_mismatches', 0);
        $response->assertJsonPath('data.pending_mismatches', 0);
        $response->assertJsonPath('data.resolved_mismatches', 0);
        $response->assertJsonPath('data.last_run', null);
    }

    // =========================================================================
    // Scheduler
    // =========================================================================

    /** @test */
    public function reconciliation_command_dispatches_job(): void
    {
        Queue::fake();

        $this->artisan('payments:reconcile')
            ->assertExitCode(0);

        Queue::assertPushed(PaymentReconciliationJob::class);
    }

    /** @test */
    public function reconciliation_job_is_on_low_queue(): void
    {
        $job = new PaymentReconciliationJob();

        $this->assertEquals('low', $job->queue);
    }

    // =========================================================================
    // Model Scopes
    // =========================================================================

    /** @test */
    public function reconciliation_result_model_scopes_work(): void
    {
        $transaction = $this->createTransaction('paid');

        $this->mockFactoryReturns($this->createGatewayResult([
            'gatewayTransactionId' => $transaction->gateway_transaction_id,
            'amount' => 50.00,
        ]));

        $this->runJob();

        $result = PaymentReconciliationResult::first();
        $result->update(['resolved_at' => Carbon::now()]);

        $this->assertEquals(1, PaymentReconciliationResult::resolved()->count());
        $this->assertEquals(0, PaymentReconciliationResult::unresolved()->count());
        $this->assertEquals(1, PaymentReconciliationResult::byType('amount')->count());
    }
}
