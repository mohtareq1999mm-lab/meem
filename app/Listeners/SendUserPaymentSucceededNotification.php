<?php

namespace App\Listeners;

use App\Enums\UserType;
use App\Events\PaymentSucceeded;
use App\Notifications\UserPaymentSucceededNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendUserPaymentSucceededNotification implements ShouldQueue
{
    public $queue = 'meem-medium';

    public function handle(PaymentSucceeded $event): void
    {
        $user = $event->order->user;

        if (!$user || $user->type !== UserType::USER->value) {
            return;
        }

        $user->notify(new UserPaymentSucceededNotification($event->order));
    }
}
