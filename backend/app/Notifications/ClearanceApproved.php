<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClearanceApproved extends Notification
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
            ->subject("Health & Safety Clearance #{$this->clearanceNumber} Approved")
            ->greeting("Hello {$this->applicantName}!")
            ->line('Your Health and Safety Clearance has been approved.')
            ->line("Clearance Number: {$this->clearanceNumber}")
            ->line("Valid Until: {$this->expirationDate}")
            ->line('You can download your QR-coded clearance from the portal.')
            ->action('View Clearance', url('/resident/clearance'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'clearance_approved',
            'title' => 'Clearance Approved',
            'message' => "Clearance #{$this->clearanceNumber} has been approved and is valid until {$this->expirationDate}.",
            'clearance_number' => $this->clearanceNumber,
        ];
    }
}
