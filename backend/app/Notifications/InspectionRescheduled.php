<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectionRescheduled extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $name,
        public string $scheduledAt,
        public ?string $inspectorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Inspection Rescheduled for Request #{$this->requestNumber}")
            ->greeting("Hello {$this->name}!")
            ->line('The date for your inspection has been changed.')
            ->line("Request Number: {$this->requestNumber}")
            ->line("New Scheduled Date & Time: {$this->scheduledAt}")
            ->when($this->inspectorName !== null, fn ($message) => $message->line("Inspector: {$this->inspectorName}"))
            ->action('View Details', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inspection_rescheduled',
            'title' => 'Inspection Rescheduled',
            'message' => "Inspection for request #{$this->requestNumber} has been rescheduled to {$this->scheduledAt}.",
            'request_number' => $this->requestNumber,
            'scheduled_at' => $this->scheduledAt,
        ];
    }
}
