<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
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
            ->subject("Inspection Request #{$this->requestNumber} Submitted")
            ->greeting("Hello {$this->applicantName}!")
            ->line('Your inspection request has been submitted successfully.')
            ->line("Request Number: {$this->requestNumber}")
            ->line("Category: {$this->category}")
            ->line('A barangay staff will review your application and requirements.')
            ->action('Track Application', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_submitted',
            'title' => 'Inspection Request Submitted',
            'message' => "Request #{$this->requestNumber} ({$this->category}) has been submitted for review.",
            'request_number' => $this->requestNumber,
        ];
    }
}
