<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RenewalReminder extends Notification
{
    use Queueable;

    public function __construct(
        public string $clearanceNumber,
        public string $applicantName,
        public string $expirationDate,
        public int $daysUntilExpiry,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Renewal Reminder: Clearance #{$this->clearanceNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line("Your Health and Safety Clearance will expire in {$this->daysUntilExpiry} days.")
            ->line("Clearance Number: {$this->clearanceNumber}")
            ->line("Expiration Date: {$this->expirationDate}")
            ->line('Please submit a renewal application to avoid interruption.')
            ->action('Renew Now', url('/resident/clearance'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'renewal_reminder',
            'title' => 'Renewal Reminder',
            'message' => "Clearance #{$this->clearanceNumber} expires in {$this->daysUntilExpiry} days ({$this->expirationDate}). Please renew.",
            'clearance_number' => $this->clearanceNumber,
        ];
    }
}
