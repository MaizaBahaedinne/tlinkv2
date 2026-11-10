<?php

namespace App\Notifications;

use App\Support\EmailVerificationCodes;
use App\Support\MailTemplates;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail contenant le code à 6 chiffres de confirmation de l'adresse. */
class EmailVerificationCode extends Notification
{
    public function __construct(public readonly string $code)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return MailTemplates::message('verification_code', [
            'prenom' => (string) ($notifiable->first_name ?? ''),
            'code' => $this->code,
            'minutes' => (string) EmailVerificationCodes::VALID_MINUTES,
        ]);
    }
}
