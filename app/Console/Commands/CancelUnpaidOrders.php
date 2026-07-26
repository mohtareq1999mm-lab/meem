<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Transaction;
use App\Events\OrderCancelled;
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

                $lockedOrder->update(['status' => 'cancelled']);

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
