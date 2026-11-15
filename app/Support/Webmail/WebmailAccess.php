<?php

namespace App\Support\Webmail;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use App\Models\WebmailMailbox;
use App\Support\AdminContext;
use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Builder;

/** Qui voit quelle boîte : le périmètre vient de la matrice du poste (droit « événements » + module « Lecture et envoi »). */
final class WebmailAccess
{
    public const INBOX = 'webmail.inbox';

    /** Boîtes actives des entités que l'utilisateur gère. @return Builder<WebmailMailbox> */
    public static function mailboxes(User $user): Builder
    {
        return MarketingEntities::restrict(WebmailMailbox::query()->where('is_active', true), $user)->orderBy('address');
    }

    public static function can(User $user, WebmailMailbox $mailbox): bool
    {
        return $mailbox->is_active && MarketingEntities::allows($user, $mailbox->club_id, $mailbox->scope);
    }

    /**
     * Comptes à prévenir d'un nouveau message : titulaires d'un poste dont la matrice accorde « Lecture et envoi »
     * en consultation, sur l'entité de la boîte.
     *
     * @return array<int, int>
     */
    public static function readers(WebmailMailbox $mailbox): array
    {
        $nodeIds = OrgNode::query()->where('kind', 'position')->whereNotNull('module_permissions')->get(['id', 'module_permissions'])
            ->filter(fn (OrgNode $node): bool => array_intersect(['read', 'update', 'create'], (array) (((array) $node->module_permissions)[self::INBOX] ?? [])) !== [])
            ->pluck('id')->all();

        if ($nodeIds === []) {
            return [];
        }

        $userIds = OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->with('person:id,user_id')->get()
            ->map(fn (OrgAssignment $assignment): ?int => $assignment->person?->user_id)
            ->filter()->unique()->values();

        return User::query()->whereIn('id', $userIds)->where('is_active', true)->get()
            ->filter(fn (User $user): bool => self::can($user, $mailbox) && (new AdminContext($user))->allowsModule(['event.manage'], self::INBOX, ['read']))
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }
}
