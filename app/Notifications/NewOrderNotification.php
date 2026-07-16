<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public $order,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'New Order',
            'message' => "New Order #{$this->order->order_number} has been placed.",
            'icon' => 'shopping-cart',
            'resource_type' => 'order',
            'resource_id' => $this->order->id,
            'action_url' => "/admin/orders/{$this->order->id}",
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->name ?? $this->order->user?->name,
            'total_amount' => $this->order->total_price ?? $this->order->price,
            'payment_status' => $this->order->payment_status ?? 'pending',
            'order_status' => $this->order->status ?? 'pending',
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function broadcastType(): string
    {
        return 'order.created';
    }
}
