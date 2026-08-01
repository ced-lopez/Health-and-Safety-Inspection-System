<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewInspectionAssignment extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $businessName,
        public string $category,
        public string $applicantName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Inspection Assignment #{$this->requestNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line('You have been assigned a new inspection request.')
            ->line("Request Number: {$this->requestNumber}")
            ->line("Business: {$this->businessName}")
            ->line("Category: {$this->category}")
            ->action('View Assignment', url('/inspections'))
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_inspection_assignment',
            'title' => 'New Inspection Assignment',
            'message' => "You have been assigned to request #{$this->requestNumber} for {$this->businessName}.",
            'request_number' => $this->requestNumber,
        ];
    }
}
