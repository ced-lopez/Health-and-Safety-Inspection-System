<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectorAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $inspectorName,
        public string $applicantName,
        public ?string $scheduledAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Inspector Assigned for Request #{$this->requestNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line('An inspector has been assigned to your inspection request.')
            ->line("Request Number: {$this->requestNumber}")
            ->line("Inspector: {$this->inspectorName}")
            ->when($this->scheduledAt !== null, fn ($message) => $message->line("Scheduled for: {$this->scheduledAt}"))
            ->line('The inspector will contact you to schedule the inspection.')
            ->action('View Details', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inspector_assigned',
            'title' => 'Inspector Assigned',
            'message' => "Inspector {$this->inspectorName} has been assigned to request #{$this->requestNumber}.",
            'request_number' => $this->requestNumber,
            'scheduled_at' => $this->scheduledAt,
        ];
    }
}
