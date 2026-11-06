<?php

namespace App\Notifications;

use App\Support\EmailVerificationCodes;
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
        return (new MailMessage())
            ->subject('Votre code de confirmation : '.$this->code.' | T.Link')
            ->greeting('Bienvenue '.$notifiable->first_name.' !')
            ->line('Pour poursuivre votre intégration à la Fondation Tunivisions, saisissez ce code de confirmation dans T.Link :')
            ->line('**'.$this->code.'**')
            ->line('Ce code est valable '.EmailVerificationCodes::VALID_MINUTES.' minutes. Si vous n’avez pas créé de compte, ignorez ce message.')
            ->salutation('L’équipe Tunivisions');
    }
}
