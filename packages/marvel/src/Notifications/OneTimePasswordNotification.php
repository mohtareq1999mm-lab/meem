<?php

namespace Marvel\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Spatie\OneTimePasswords\Notifications\OneTimePasswordNotification as SpatieNotification;

class OneTimePasswordNotification extends SpatieNotification
{
    public function toMail(object $notifiable)
    {
        return (new MailMessage)
            ->from(env('MAIL_FROM_ADDRESS', 'default@default.com'), config('app.name', 'ChawkBazar'))
            ->subject($this->subject() ?? __('Your OTP Code'))
            ->markdown('emails.one-time-passwords', [
                'oneTimePassword' => $this->oneTimePassword,
                'appName' => config('app.name', 'ChawkBazar'),
                'locale' => app()->getLocale()
            ]);
    }

    public function subject(): string
    {
        return __('message.your otp code');
    }
}
