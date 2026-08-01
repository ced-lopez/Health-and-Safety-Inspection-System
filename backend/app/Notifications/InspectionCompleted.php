<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectionCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $applicantName,
        public string $result,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Inspection Completed for Request #{$this->requestNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line("The inspection for request #{$this->requestNumber} has been completed.")
            ->line("Result: {$this->result}")
            ->action('View Report', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inspection_completed',
            'title' => 'Inspection Completed',
            'message' => "Inspection for request #{$this->requestNumber} is complete. Result: {$this->result}.",
            'request_number' => $this->requestNumber,
        ];
    }
}
