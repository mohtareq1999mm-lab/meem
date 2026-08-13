<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserFlashSalePriceDropNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public $flashSale,
    ) {
        $this->onQueue('meem-medium');
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => [
                'en' => __('notifications.flash_sale.price.drop.title', [], 'en'),
                'ar' => __('notifications.flash_sale.price.drop.title', [], 'ar'),
            ],
            'message' => [
                'en' => __('notifications.flash_sale.price.drop.body', ['flash_sale_title' => $this->flashSale->title ?? ''], 'en'),
                'ar' => __('notifications.flash_sale.price.drop.body', ['flash_sale_title' => $this->flashSale->title ?? ''], 'ar'),
            ],
            'icon' => 'bolt',
            'resource_type' => 'flash_sale',
            'resource_id' => $this->flashSale->id,
            'action_url' => '/flash-sales/' . $this->flashSale->id,
            'flash_sale_id' => $this->flashSale->id,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function broadcastType(): string
    {
        return 'flash_sale.price.drop';
    }

    public function databaseType($notifiable): string
    {
        return $this->broadcastType();
    }
}
