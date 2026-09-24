<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordLink extends Notification
{
    use Queueable;

    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?'
            .http_build_query([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

        return (new MailMessage)
            ->subject('Reset Your Barangay 178 Password')
            ->greeting("Hello {$notifiable->name}!")
            ->line('We received a request to reset the password for your Barangay 178 Health and Safety Inspection System account.')
            ->action('Reset Password', $resetUrl)
            ->line('This link expires in 60 minutes and can only be used once.')
            ->line('If you did not request a password reset, no action is needed.');
    }
}
