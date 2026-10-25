<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// By default the email carries no message text, only a link to sign in. Personal data in email is a decision for GSK
// (config gsk.contact.include_message_body).
class ContactMessageReceived extends Notification
{
    public function __construct(private readonly int $messageId, private readonly ?array $body = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject('New contact message on the GSK website')
            ->line('A new message arrived through the website contact form.');

        if ($this->body !== null) {
            $mail->line('From: ' . $this->body['name'] . ' <' . $this->body['email'] . '>')
                ->line('Topic: ' . ($this->body['topic'] ?? '(none)'))
                ->line($this->body['message']);
        }

        return $mail->action('Open in the staff area', url('/staff/contact-messages/' . $this->messageId));
    }
}
