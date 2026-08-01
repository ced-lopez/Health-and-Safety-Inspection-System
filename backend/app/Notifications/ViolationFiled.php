<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViolationFiled extends Notification
{
    use Queueable;

    public function __construct(
        public string $violationTitle,
        public string $establishmentName,
        public string $severity,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Violation Filed: {$this->violationTitle}")
            ->greeting('Hello!')
            ->line("A new violation has been filed for {$this->establishmentName}.")
            ->line("Violation: {$this->violationTitle}")
            ->line("Severity: {$this->severity}")
            ->action('View Violation', url('/violations'))
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'violation_filed',
            'title' => 'Violation Filed',
            'message' => "Violation \"{$this->violationTitle}\" filed for {$this->establishmentName}.",
            'severity' => $this->severity,
        ];
    }
}
