<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendVerificationCode extends Notification
{
    use Queueable;

    public function __construct(public string $code, public string $channel = 'email') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Barangay 178 Verification Code')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Use the verification code below to complete your account registration.')
            ->line('Your verification code is: '.$this->code)
            ->line('This code is valid for 10 minutes.')
            ->line('If you did not create this account, you can safely ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verification',
            'channel' => $this->channel,
            'title' => 'Account Verification',
            'message' => 'Your verification code is '.$this->code.'. It expires in 10 minutes.',
        ];
    }
}
