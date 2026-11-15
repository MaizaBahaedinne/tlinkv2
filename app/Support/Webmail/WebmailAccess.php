<?php

namespace App\Support\Webmail;

use App\Models\OrgAssignment;
use App\Models\User;
use App\Models\WebmailMailbox;
use App\Models\WebmailMailboxUser;
use App\Support\AccessLevels;
use App\Support\AdminContext;
use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Qui utilise quelle boîte : soit des comptes choisis à la main sur la fiche de la boîte (avec leurs droits), soit les titulaires
 * d'un poste dont la matrice ouvre « Lecture et envoi » sur l'entité de la boîte.
 */
final class WebmailAccess
{
    public const INBOX = 'webmail.inbox';

    public const ACTIONS = ['read' => 'Lire', 'create' => 'Écrire', 'update' => 'Classer', 'delete' => 'Supprimer'];

    /** Boîtes actives des entités que l'utilisateur gère par son poste. @return Builder<WebmailMailbox> */
    public static function mailboxes(User $user): Builder
    {
        return MarketingEntities::restrict(WebmailMailbox::query()->where('is_active', true), $user)->orderBy('address');
    }

    public static function can(User $user, WebmailMailbox $mailbox): bool
    {
        return $mailbox->is_active && MarketingEntities::allows($user, $mailbox->club_id, $mailbox->scope);
    }

    /**
     * Droits donnés à la main sur chaque boîte : [identifiant de la boîte => actions]. Lire est toujours inclus.
     *
     * @return array<int, array<int, string>>
     */
    public static function grants(User $user): array
    {
        if (! Schema::hasTable('webmail_mailbox_users')) {
            return [];
        }

        return WebmailMailboxUser::query()->where('user_id', $user->id)->get(['mailbox_id', 'actions'])
            ->mapWithKeys(fn (WebmailMailboxUser $grant): array => [(int) $grant->mailbox_id => self::normalizeActions((array) $grant->actions)])->all();
    }

    /**
     * L'utilisateur peut-il faire cette action sur la boîte ? Soit par un droit donné à la main sur la boîte, soit par son
     * poste (entité de la boîte + module « Lecture et envoi »).
     */
    public static function allows(User $user, WebmailMailbox $mailbox, string $action): bool
    {
        if (! $mailbox->is_active) {
            return false;
        }

        if (in_array($action, self::grants($user)[$mailbox->id] ?? [], true)) {
            return true;
        }

        return self::can($user, $mailbox) && self::context($user)->allowsModule(['event.manage'], self::INBOX, [$action]);
    }

    /**
     * Boîtes que l'utilisateur peut lire (droit manuel ou poste), par ordre d'adresse.
     *
     * @return Collection<int, WebmailMailbox>
     */
    public static function readable(User $user): Collection
    {
        $key = 'webmail.readable.'.$user->id;

        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $readable = collect();

        if (Schema::hasTable('webmail_mailboxes')) {
            $granted = array_keys(self::grants($user));
            $byPost = self::mailboxes($user)->get();
            $manual = $granted === [] ? collect() : WebmailMailbox::query()->where('is_active', true)->whereIn('id', $granted)->get();

            $readable = $byPost->merge($manual)->unique('id')->filter(fn (WebmailMailbox $mailbox): bool => self::allows($user, $mailbox, 'read'))->sortBy('address')->values();
        }

        request()->attributes->set($key, $readable);

        return $readable;
    }

    /** Au moins une boîte est ouverte à l'utilisateur (entrée du menu). */
    public static function available(User $user): bool
    {
        return self::readable($user)->isNotEmpty();
    }

    /** Messages non lus (dernier relevé) dans les boîtes que l'utilisateur peut lire. */
    public static function unseen(User $user): int
    {
        return (int) self::readable($user)->sum('unseen');
    }

    /** Boîte affichée : celle de la route ou de ?boite=, à défaut la première de l'utilisateur. */
    public static function current(User $user, Request $request): ?WebmailMailbox
    {
        $routed = $request->route('mailbox');
        $wanted = $routed instanceof WebmailMailbox ? $routed->id : (int) $request->query('boite', 0);
        $readable = self::readable($user);

        return $readable->firstWhere('id', $wanted) ?? $readable->first();
    }

    /**
     * Lire est toujours inclus ; les autres actions sont celles demandées.
     *
     * @param array<int, string> $actions
     * @return array<int, string>
     */
    public static function normalizeActions(array $actions): array
    {
        return array_values(array_filter(array_keys(self::ACTIONS), fn (string $action): bool => $action === 'read' || in_array($action, $actions, true)));
    }

    /**
     * Comptes autorisés à utiliser la boîte : ceux choisis à la main (droits propres) et les titulaires d'un poste dont le
     * niveau et la matrice ouvrent « Lecture et envoi » sur l'entité de la boîte.
     *
     * @return Collection<int, array{user: User, roles: array<int, string>, actions: array<int, string>, explicit: bool, manual: bool, manual_actions: array<int, string>}>
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

            $rows->put((int) $user->id, [
                'user' => $user,
                'roles' => $held->map(fn (OrgAssignment $assignment): string => trim($assignment->node->title.($assignment->node->subtitle ? ' — '.$assignment->node->subtitle : '')))->unique()->values()->all(),
                'actions' => $actions,
                'explicit' => $held->contains(fn (OrgAssignment $assignment): bool => (array) (((array) $assignment->node->module_permissions)[self::INBOX] ?? []) !== []),
                'manual' => false,
                'manual_actions' => [],
            ]);
        }

        if (Schema::hasTable('webmail_mailbox_users')) {
            foreach (WebmailMailboxUser::query()->where('mailbox_id', $mailbox->id)->with('user')->get() as $grant) {
                if ($grant->user === null || ! $grant->user->is_active) {
                    continue;
                }

                $manual = self::normalizeActions((array) $grant->actions);
                $row = $rows->get((int) $grant->user_id) ?? ['user' => $grant->user, 'roles' => [], 'actions' => []];
                $merged = array_values(array_unique(array_merge($row['actions'], $manual)));

                $rows->put((int) $grant->user_id, array_merge($row, [
                    'actions' => array_values(array_filter(array_keys(self::ACTIONS), fn (string $action): bool => in_array($action, $merged, true))),
                    'explicit' => true,
                    'manual' => true,
                    'manual_actions' => $manual,
                ]));
            }
        }

        return $rows->sortBy(fn (array $row): string => mb_strtolower($row['user']->name))->values();
    }

    /**
     * Comptes à prévenir d'un nouveau message : ceux choisis à la main et ceux dont le poste a explicitement ouvert « Lecture et
     * envoi » (pas les administrateurs globaux, qui y ont accès sans y être affectés).
     *
     * @return array<int, int>
     */
    public static function readers(WebmailMailbox $mailbox): array
    {
        return self::authorized($mailbox)->where('explicit', true)->map(fn (array $row): int => (int) $row['user']->id)->all();
    }

    private static function context(User $user): AdminContext
    {
        $request = request();

        return $request->user()?->is($user) ? AdminContext::of($request) : new AdminContext($user);
    }
}
