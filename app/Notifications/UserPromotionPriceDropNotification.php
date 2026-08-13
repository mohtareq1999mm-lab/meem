<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserPromotionPriceDropNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public $promotion,
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
                'en' => __('notifications.promotion.price.drop.title', [], 'en'),
                'ar' => __('notifications.promotion.price.drop.title', [], 'ar'),
            ],
            'message' => [
                'en' => __('notifications.promotion.price.drop.body', ['promotion_name' => $this->promotion->name ?? ''], 'en'),
                'ar' => __('notifications.promotion.price.drop.body', ['promotion_name' => $this->promotion->name ?? ''], 'ar'),
            ],
            'icon' => 'tag',
            'resource_type' => 'promotion',
            'resource_id' => $this->promotion->id,
            'action_url' => '/promotions/' . $this->promotion->id,
            'promotion_id' => $this->promotion->id,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function broadcastType(): string
    {
        return 'promotion.price.drop';
    }

    public function databaseType($notifiable): string
    {
        return $this->broadcastType();
    }
}
