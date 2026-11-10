<?php

namespace App\Notifications;

use App\Support\MailTemplates;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Envoi de test d'un modèle d'e-mail, avec des valeurs d'exemple (Admin > Messagerie > Modèles). */
class MailTemplateTest extends Notification
{
    public function __construct(public readonly string $template)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = MailTemplates::sample($this->template);
        $message->subject('[TEST] '.$message->subject);

        return $message;
    }
}
