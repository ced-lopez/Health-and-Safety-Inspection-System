<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationConfirmed extends Notification
{
    use Queueable;

    public function __construct(public string $name) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Barangay 178 Health & Safety System')
            ->greeting("Hello {$this->name}!")
            ->line('Your account has been successfully registered.')
            ->line('You can now submit inspection requests, track applications, and manage your clearances online.')
            ->action('Go to Dashboard', url('/dashboard'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'registration',
            'title' => 'Registration Successful',
            'message' => "Welcome {$this->name}! Your account has been created successfully.",
        ];
    }
}
