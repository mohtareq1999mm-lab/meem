<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AdminLoggedInNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public $admin,
        public string $ip,
        public string $userAgent,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Admin Login',
            'message' => "{$this->admin->name} logged in.",
            'icon' => 'log-in',
            'resource_type' => 'admin',
            'resource_id' => $this->admin->id,
            'action_url' => '/admin/admins',
            'admin_id' => $this->admin->id,
            'admin_name' => $this->admin->name,
            'admin_email' => $this->admin->email,
            'login_time' => now()->toIso8601String(),
            'login_ip' => $this->ip,
            'user_agent' => $this->userAgent,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function broadcastType(): string
    {
        return 'admin.login';
    }
}
