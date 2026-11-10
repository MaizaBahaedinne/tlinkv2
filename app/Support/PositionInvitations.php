<?php

namespace App\Support;

use App\Models\OrgNode;
use App\Models\PositionInvitation;
use App\Models\User;
use App\Notifications\PositionInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Envoi des invitations à candidater : une invitation par personne et par poste (renvoyée si elle est encore ouverte).
 */
final class PositionInvitations
{
    /**
     * @param array<int, array{first_name: string, last_name: string, email: string}> $invitees
     * @return array{sent: array<int, array{name: string, email: string}>, skipped: array<int, array{email: string, reason: string}>, failed: array<int, array{email: string, reason: string}>}
     */
    public static function send(OrgNode $node, array $invitees, User $by): array
    {
        $result = ['sent' => [], 'skipped' => [], 'failed' => []];

        foreach ($invitees as $invitee) {
            $email = mb_strtolower(trim($invitee['email']));
            $holder = User::query()->where('email', $email)->first();

            if ($holder && $node->assignments()->whereHas('person', fn ($query) => $query->where('user_id', $holder->id))->exists()) {
                $result['skipped'][] = ['email' => $email, 'reason' => 'occupe déjà ce poste'];

                continue;
            }

            $invitation = PositionInvitation::query()
                ->where('node_id', $node->id)->where('email', $email)
                ->whereNull('accepted_at')
                ->latest('id')->first() ?? new PositionInvitation(['node_id' => $node->id, 'email' => $email, 'send_count' => 0]);

            $invitation->fill([
                'first_name' => trim($invitee['first_name']),
                'last_name' => trim($invitee['last_name']),
                'invited_by' => $by->id,
            ]);

            if (self::deliver($invitation)) {
                $result['sent'][] = ['name' => $invitation->fullName(), 'email' => $email];
            } else {
                $result['failed'][] = ['email' => $email, 'reason' => 'envoi impossible, vérifiez la messagerie'];
            }
        }

        return $result;
    }

    /** Génère un nouveau lien (l'ancien cesse de fonctionner), l'enregistre puis envoie l'e-mail. */
    public static function deliver(PositionInvitation $invitation): bool
    {
        $token = Str::random(40);

        $invitation->forceFill([
            'token_hash' => PositionInvitation::hashToken($token),
            'expires_at' => now()->addDays(PositionInvitation::VALID_DAYS),
            'revoked_at' => null,
        ])->save();

        try {
            Notification::route('mail', [$invitation->email => $invitation->fullName()])
                ->notify(new PositionInvitationNotification($invitation, $token));
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }

        $invitation->forceFill(['last_sent_at' => now(), 'send_count' => $invitation->send_count + 1])->save();

        return true;
    }
}
