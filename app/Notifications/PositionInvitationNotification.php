<?php

namespace App\Notifications;

use App\Models\PositionInvitation;
use App\Support\MailTemplates;
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
        $scope = (string) $this->invitation->node?->scopeLabel();

        return MailTemplates::message('position_invitation', [
            'prenom' => $this->invitation->first_name,
            'nom' => $this->invitation->last_name,
            'poste' => $title.($scope !== '' ? ' ('.$scope.')' : ''),
            'perimetre' => $scope,
            'jours' => (string) PositionInvitation::VALID_DAYS,
        ], route('position.invitation', $this->token));
    }
}
