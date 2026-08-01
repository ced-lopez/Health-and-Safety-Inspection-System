<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissingRequirements extends Notification
{
    use Queueable;

    public function __construct(
        public string $requestNumber,
        public array $missingDocuments,
        public string $applicantName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Action Required: Missing Requirements for #{$this->requestNumber}")
            ->greeting("Hello {$this->applicantName}!")
            ->line('Your inspection request is missing the following requirements:');

        foreach ($this->missingDocuments as $document) {
            $message->line("- {$document}");
        }

        $message->line('Please upload the missing documents to proceed with your application.')
            ->action('Upload Documents', url('/resident/my-applications'))
            ->line('Thank you for using Barangay 178 services.');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'missing_requirements',
            'title' => 'Missing Requirements',
            'message' => 'Your application #'.$this->requestNumber.' has missing requirements.',
            'request_number' => $this->requestNumber,
            'missing_documents' => $this->missingDocuments,
        ];
    }
}
