<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Jobs\LogActivityJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentSucceededNotification implements ShouldQueue
{
    public $queue = 'medium';

    public function handle(PaymentSucceeded $event): void
    {
        $order = $event->order;

        $description = __('activity.payment_succeeded') ?: 'Payment succeeded';

        LogActivityJob::dispatch(
            get_class($order),
            $order->id,
            $order->user_id,
            'payment_succeeded',
            'orders',
            $description,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'payment_gateway' => $order->payment_gateway,
            ],
        );
    }
}
