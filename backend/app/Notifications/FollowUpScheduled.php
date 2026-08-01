<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpScheduled extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $inspectorName,
        public string $applicantName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Follow-up Inspection Scheduled for #{$this->requestNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line('A follow-up inspection has been scheduled for your request.')
            ->line("Request Number: {$this->requestNumber}")
            ->line("Inspector: {$this->inspectorName}")
            ->action('View Details', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'follow_up_scheduled',
            'title' => 'Follow-up Inspection Scheduled',
            'message' => "Follow-up inspection for request #{$this->requestNumber} has been scheduled.",
            'request_number' => $this->requestNumber,
        ];
    }
}
