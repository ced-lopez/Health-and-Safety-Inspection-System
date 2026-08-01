<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectionSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $inspectorName,
        public string $businessName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Inspection Report Submitted for Request #{$this->requestNumber}")
            ->greeting('Hello!')
            ->line("Inspector {$this->inspectorName} has submitted the inspection report.")
            ->line("Request Number: {$this->requestNumber}")
            ->line("Business: {$this->businessName}")
            ->line('Please review the inspection results and issue the appropriate document.')
            ->action('Review Report', url('/inspections'))
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inspection_submitted',
            'title' => 'Inspection Report Submitted',
            'message' => "Inspector {$this->inspectorName} submitted the report for request #{$this->requestNumber}.",
            'request_number' => $this->requestNumber,
        ];
    }
}
