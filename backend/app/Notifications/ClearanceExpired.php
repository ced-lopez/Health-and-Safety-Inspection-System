<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClearanceExpired extends Notification
{
    use Queueable;

    public function __construct(
        public string $clearanceNumber,
        public string $applicantName,
        public string $expirationDate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Clearance #{$this->clearanceNumber} Has Expired")
            ->greeting("Hello {$this->applicantName}!")
            ->line('Your Health and Safety Clearance has expired.')
            ->line("Clearance Number: {$this->clearanceNumber}")
            ->line("Expired On: {$this->expirationDate}")
            ->line('Please submit a renewal application to obtain a new clearance.')
            ->action('Apply for Renewal', url('/resident/clearance'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clearance_expired',
            'title' => 'Clearance Expired',
            'message' => "Clearance #{$this->clearanceNumber} expired on {$this->expirationDate}. Please renew.",
            'clearance_number' => $this->clearanceNumber,
        ];
    }
}
