<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PreferredScheduleSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $businessName,
        public string $applicantName,
        public string $preferredScheduleAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Schedule Proposal for Request #{$this->requestNumber}")
            ->greeting('Hello!')
            ->line("{$this->applicantName} proposed a preferred inspection schedule for request #{$this->requestNumber}.")
            ->line('Business / Facility: '.$this->businessName)
            ->line('Preferred Schedule: '.$this->preferredScheduleAt)
            ->line('Review and confirm the schedule to notify the resident.')
            ->action('Review Request', url('/inspection-requests'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'preferred_schedule_submitted',
            'title' => 'Schedule Proposal Received',
            'message' => "{$this->applicantName} proposed an inspection schedule for request #{$this->requestNumber} at {$this->preferredScheduleAt}. Confirm to notify the resident.",
            'request_number' => $this->requestNumber,
            'preferred_schedule_at' => $this->preferredScheduleAt,
        ];
    }
}