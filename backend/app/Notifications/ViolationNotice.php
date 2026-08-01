<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViolationNotice extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public string $violationTitle,
        public string $deadline,
        public string $applicantName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Violation Notice for Request #{$this->requestNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line('A violation has been recorded for your inspection request.')
            ->line("Request Number: {$this->requestNumber}")
            ->line("Violation: {$this->violationTitle}")
            ->line("Compliance Deadline: {$this->deadline}")
            ->line('Please address the violation within 7 days and request a follow-up inspection.')
            ->action('View Details', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'violation_notice',
            'title' => 'Violation Notice',
            'message' => "Violation issued for request #{$this->requestNumber}: {$this->violationTitle}. Compliance deadline: {$this->deadline}.",
            'request_number' => $this->requestNumber,
        ];
    }
}
