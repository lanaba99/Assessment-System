<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a user is invited to a tenant. Carries the plaintext invitation
 * token — never the hash — because that's what the recipient has to paste
 * back into the invitation-acceptance endpoint. The hash stays in
 * `invitation_tokens`.
 *
 * In dev with MAIL_MAILER=log, the full message (token included) lands in
 * storage/logs/laravel.log, which is how you grab it for Postman testing.
 */
class UserInvited extends Notification
{
    public function __construct(
        private readonly string $plainToken,
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
            ->subject('You have been invited')
            ->greeting('Hello,')
            ->line('You have been invited to join the platform.')
            ->line('Your invitation token:')
            ->line($this->plainToken)
            ->line('Use this token to activate your account.');
    }
}