<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a user requests a password reset. Carries the plaintext reset
 * token — never the hash — because that's what the recipient has to paste
 * back into the reset endpoint. The hash stays in `password_reset_tokens`.
 *
 * In dev with MAIL_MAILER=log, the full message (token included) lands in
 * storage/logs/laravel.log, which is how you grab it for Postman testing.
 */
class PasswordResetRequested extends Notification
{
    public function __construct(
        private readonly string $plainToken,
        private readonly int $ttlMinutes = 60,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Password reset requested')
            ->greeting('Hello,')
            ->line('We received a request to reset your password.')
            ->line('Your reset token (valid for '.$this->ttlMinutes.' minutes):')
            ->line($this->plainToken)
            ->line('If you did not request a password reset, no further action is required.');
    }
}
