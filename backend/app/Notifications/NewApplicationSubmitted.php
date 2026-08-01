<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewApplicationSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $category,
        public string $applicantName,
        public string $businessName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Inspection Request #{$this->requestNumber}")
            ->greeting('Hello!')
            ->line("{$this->applicantName} has submitted a new inspection request.")
            ->line("Request Number: {$this->requestNumber}")
            ->line("Category: {$this->category}")
            ->line('Please review the application and its requirements.')
            ->action('View Request', url('/inspection-requests'))
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_application_submitted',
            'title' => 'New Inspection Request',
            'message' => "{$this->applicantName} submitted request #{$this->requestNumber} ({$this->category}).",
            'request_number' => $this->requestNumber,
        ];
    }
}
