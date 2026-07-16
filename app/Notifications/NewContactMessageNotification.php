<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Contact $contact,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'New Contact Message',
            'message' => "New Contact Us message received from {$this->contact->name}.",
            'icon' => 'mail',
            'resource_type' => 'contact',
            'resource_id' => $this->contact->id,
            'action_url' => "/admin/contacts/{$this->contact->id}",
            'contact_id' => $this->contact->id,
            'customer_name' => $this->contact->name,
            'customer_email' => $this->contact->email,
            'subject' => $this->contact->subject,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    public function broadcastType(): string
    {
        return 'contact.message';
    }
}
