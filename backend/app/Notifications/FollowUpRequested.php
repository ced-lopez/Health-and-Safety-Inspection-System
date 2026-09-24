<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FollowUpRequested extends Notification
{
    use Queueable;

    public function __construct(public string $requestNumber, public string $applicantName) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'follow_up_requested',
            'title' => 'Follow-up Inspection Requested',
            'message' => "{$this->applicantName} requested a follow-up inspection for #{$this->requestNumber}.",
            'request_number' => $this->requestNumber,
        ];
    }
}
