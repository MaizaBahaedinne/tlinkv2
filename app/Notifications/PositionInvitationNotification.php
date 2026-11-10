<?php

namespace App\Notifications;

use App\Models\PositionInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail d'invitation à candidater à un poste : le lien personnel mène à la création du compte. */
class PositionInvitationNotification extends Notification
{
    public function __construct(public readonly PositionInvitation $invitation, public readonly string $token)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = (string) $this->invitation->node?->title;
        $scope = $this->invitation->node?->scopeLabel();
        $position = $title.($scope ? ' ('.$scope.')' : '');

        return (new MailMessage())
            ->subject('Invitation à candidater : '.$position.' | T.Link')
            ->greeting('Bonjour '.$this->invitation->first_name.' !')
            ->line('La Fondation Tunivisions vous invite à candidater au poste « '.$position.' ».')
            ->line('Créez votre compte T.Link en quelques minutes, puis déposez votre candidature : elle sera examinée par l’équipe.')
            ->action('Créer mon compte et candidater', route('position.invitation', $this->token))
            ->line('Ce lien est personnel et valable '.PositionInvitation::VALID_DAYS.' jours. Si cette invitation ne vous concerne pas, ignorez ce message.')
            ->salutation('L’équipe Tunivisions');
    }
}
