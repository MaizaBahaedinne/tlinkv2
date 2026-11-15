<?php

namespace App\Support\Webmail;

use App\Models\OrgAssignment;
use App\Models\User;
use App\Models\WebmailMailbox;
use App\Support\AccessLevels;
use App\Support\AdminContext;
use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Qui voit quelle boîte : le périmètre vient de la matrice du poste (droit « événements » + module « Lecture et envoi »). */
final class WebmailAccess
{
    public const INBOX = 'webmail.inbox';

    public const ACTIONS = ['read' => 'Lire', 'create' => 'Écrire', 'update' => 'Classer', 'delete' => 'Supprimer'];

    /** Boîtes actives des entités que l'utilisateur gère. @return Builder<WebmailMailbox> */
    public static function mailboxes(User $user): Builder
    {
        return MarketingEntities::restrict(WebmailMailbox::query()->where('is_active', true), $user)->orderBy('address');
    }

    public static function can(User $user, WebmailMailbox $mailbox): bool
    {
        return $mailbox->is_active && MarketingEntities::allows($user, $mailbox->club_id, $mailbox->scope);
    }

    /** Le poste de l'utilisateur ouvre le webmail et au moins une boîte active lui est rattachée (entrée du menu). */
    public static function available(User $user): bool
    {
        $key = 'webmail.available.'.$user->id;

        if (request()->attributes->has($key)) {
            return (bool) request()->attributes->get($key);
        }

        $open = Schema::hasTable('webmail_mailboxes')
            && WebmailMailbox::query()->where('is_active', true)->exists()
            && (new AdminContext($user))->allowsModule(['event.manage'], self::INBOX, ['read'])
            && self::mailboxes($user)->exists();

        request()->attributes->set($key, $open);

        return $open;
    }

    /** Messages non lus (dernier relevé) dans les boîtes que l'utilisateur peut lire. */
    public static function unseen(User $user): int
    {
        return self::available($user) ? (int) self::mailboxes($user)->sum('unseen') : 0;
    }

    /**
     * Comptes autorisés à utiliser la boîte : titulaires d'un poste dont le niveau et la matrice ouvrent « Lecture et envoi »
     * sur l'entité de la boîte, avec leur poste et leurs droits. Les droits se règlent poste par poste dans la matrice.
     *
     * @return Collection<int, array{user: User, roles: array<int, string>, actions: array<int, string>, explicit: bool}>
     */
    public static function authorized(WebmailMailbox $mailbox): Collection
    {
        $assignments = OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->whereHas('node', fn ($query) => $query->where('kind', 'position')->where('access_level', '!=', AccessLevels::NONE))
            ->with(['node', 'person:id,user_id'])
            ->get()
            ->filter(function (OrgAssignment $assignment): bool {
                $node = $assignment->node;

                if ($node === null || $assignment->person?->user_id === null) {
                    return false;
                }

                $level = (string) $node->access_level;

                if (! in_array('event.manage', AccessLevels::forPosition($level, $node->permissions), true)) {
                    return false;
                }

                $matrix = (array) $node->module_permissions;

                return AccessLevels::isGlobal($level) || $matrix === [] || (array) ($matrix[self::INBOX] ?? []) !== [];
            });

        $users = User::query()->whereIn('id', $assignments->pluck('person.user_id')->unique())->where('is_active', true)->get()->keyBy('id');
        $rows = collect();

        foreach ($assignments->groupBy('person.user_id') as $userId => $held) {
            $user = $users->get((int) $userId);

            if ($user === null || ! self::can($user, $mailbox)) {
                continue;
            }

            $context = new AdminContext($user);
            $actions = array_values(array_filter(array_keys(self::ACTIONS), fn (string $action): bool => $context->allowsModule(['event.manage'], self::INBOX, [$action])));

            if (! in_array('read', $actions, true)) {
                continue;
            }

            $rows->push([
                'user' => $user,
                'roles' => $held->map(fn (OrgAssignment $assignment): string => trim($assignment->node->title.($assignment->node->subtitle ? ' — '.$assignment->node->subtitle : '')))->unique()->values()->all(),
                'actions' => $actions,
                'explicit' => $held->contains(fn (OrgAssignment $assignment): bool => (array) (((array) $assignment->node->module_permissions)[self::INBOX] ?? []) !== []),
            ]);
        }

        return $rows->sortBy(fn (array $row): string => mb_strtolower($row['user']->name))->values();
    }

    /**
     * Comptes à prévenir d'un nouveau message : ceux dont le poste a explicitement ouvert « Lecture et envoi » (pas les
     * administrateurs globaux, qui y ont accès sans y être affectés).
     *
     * @return array<int, int>
     */
    public static function readers(WebmailMailbox $mailbox): array
    {
        return self::authorized($mailbox)->where('explicit', true)->map(fn (array $row): int => (int) $row['user']->id)->all();
    }
}
