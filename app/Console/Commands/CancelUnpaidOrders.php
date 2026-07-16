<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Cart;
use Marvel\Database\Models\Transaction;
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
                $order->update(['status' => 'cancelled']);

                $order->transactions()
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);

                try {
                    event(new PaymentFailed($order));
                } catch (\Throwable $e) {
                    report($e);
                }

                $cancelledCount++;
            });

            // Release reserved inventory from the user's active cart
            try {
                $cart = Cart::query()
                    ->where('user_id', $order->user_id)
                    ->where('status', 'active')
                    ->first();

                if ($cart) {
                    $this->cartInventoryService->releaseCart($cart, false);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->info("Cancelled {$cancelledCount} unpaid order(s).");

        return self::SUCCESS;
    }
}
