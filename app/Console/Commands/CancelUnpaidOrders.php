<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Transaction;
use App\Events\OrderCancelled;
use App\Events\OrderStatusChanged;
use App\Events\PaymentFailed;
use App\Services\General\CartInventoryService;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';
    protected $description = 'Cancel unpaid pending orders past their timeout period';

    private CartInventoryService $cartInventoryService;

    public function __construct(CartInventoryService $cartInventoryService)
    {
        parent::__construct();
        $this->cartInventoryService = $cartInventoryService;
    }

    public function handle(): int
    {
        $cutoff = now()->subHours(config('payment.order_timeout_hours', 72));

        $orders = Order::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->cursor();

        $cancelledCount = 0;

        foreach ($orders as $order) {
            DB::transaction(function () use ($order, &$cancelledCount) {
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->first();

                if (!$lockedOrder || $lockedOrder->status !== 'pending') {
                    return;
                }

                $cancelUpdateData = ['status' => 'cancelled'];
                if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'payment_status')) {
                    $cancelUpdateData['payment_status'] = \Marvel\Database\Models\Order::PAYMENT_STATUS_FAILED;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'fulfillment_status')) {
                    $cancelUpdateData['fulfillment_status'] = \Marvel\Database\Models\Order::FULFILLMENT_STATUS_CANCELLED;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'cancelled_at')) {
                    $cancelUpdateData['cancelled_at'] = now();
                }
                $lockedOrder->update($cancelUpdateData);

                // System-initiated pre-payment cancellation: the order was never
                // paid, so promotion usage must NOT be decremented (it was never
                // consumed). We therefore bypass changeOrderStatus() here but keep
                // the audit event so every status write is observable.
                event(new OrderStatusChanged($lockedOrder));

                $lockedOrder->transactions()
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);

                try {
                    event(new OrderCancelled($lockedOrder));
                } catch (\Throwable $e) {
                    report($e);
                }

                try {
                    event(new PaymentFailed($lockedOrder));
                } catch (\Throwable $e) {
                    report($e);
                }

                // Release reserved inventory and expire the cart
                try {
                    $cart = Cart::query()
                        ->where('user_id', $lockedOrder->user_id)
                        ->where('status', 'active')
                        ->first();

                    if ($cart) {
                        $this->cartInventoryService->expireSingleCart($cart);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }

                $cancelledCount++;
            });
        }

        $this->info("Cancelled {$cancelledCount} unpaid order(s).");

        return self::SUCCESS;
    }
}
