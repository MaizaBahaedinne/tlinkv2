<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\PositionApplication;
use App\Models\User;

/**
 * Parcours d'intégration d'un nouveau compte. Chaque étape bloque les suivantes, et tout le parcours
 * bloque l'application tant que le membre n'a pas été accepté (adhésion ou poste) pour le mandat en cours :
 * e-mail vérifié → info session → ancien/nouveau membre → profil complet → statut actuel → adhésion ou candidature approuvée.
 *
 * Les comptes existants et les administrateurs créés en ligne de commande sont dispensés (`onboarding_done_at`).
 */
final class Onboarding
{
    public const EMAIL = 'email';

    public const INFO_SESSION = 'info_session';

    public const HISTORY = 'history';

    public const PROFILE = 'profile';

    public const STATUS = 'status';

    public const PLACEMENT = 'placement';

    /** Étapes dans l'ordre, avec leur libellé. */
    public const STEPS = [
        self::EMAIL => 'Vérifier mon e-mail',
        self::INFO_SESSION => 'Valider la session d’information',
        self::HISTORY => 'Ancien ou nouveau membre',
        self::PROFILE => 'Compléter mon profil',
        self::STATUS => 'Mon statut actuel',
        self::PLACEMENT => 'Adhésion ou poste du mandat en cours',
    ];

    /** Le parcours s'applique à ce compte (ni dispensé, ni terminé, ni administrateur en usurpation). */
    public static function applies(User $user): bool
    {
        return $user->onboarding_done_at === null && session('impersonator_id') === null;
    }

    /** Le membre est en plein parcours d'intégration : il voit l'interface d'intégration, pas celle de l'application. */
    public static function inProgress(User $user): bool
    {
        return self::applies($user) && self::current($user) !== null;
    }

    /**
     * État du parcours d'un compte, sans rien enregistrer (contrairement à current()) : l'avancement vu par l'administration.
     *
     * @return array{steps: array<string, bool>, current: string|null, complete: bool}
     */
    public static function progress(User $user): array
    {
        $steps = [];

        foreach (array_keys(self::STEPS) as $step) {
            $steps[$step] = self::done($user, $step);
        }

        $current = array_search(false, $steps, true);

        return [
            'steps' => $steps,
            'current' => $current === false ? null : $current,
            'complete' => $user->onboarding_done_at !== null,
        ];
    }

    /**
     * Remet des comptes au début du parcours : e-mail (sauf $keepEmail), info session, ancien/nouveau et mandat d'arrivée.
     * Le profil et l'adhésion/poste du mandat en cours se calculent d'après l'état réel du compte : ils restent franchis
     * tant que la photo, le CV et une adhésion approuvée (ou un poste) existent.
     *
     * @param \Illuminate\Database\Eloquent\Builder<User> $query
     */
    public static function restart($query, bool $keepEmail = true): int
    {
        $reset = ['onboarding_done_at' => null, 'onboarding_completed_at' => null, 'history_onboarding_at' => null, 'member_origin' => null, 'joined_mandate_id' => null];

        if (! $keepEmail) {
            $reset['email_verified_at'] = null;
        }

        return $query->update($reset);
    }

    /**
     * Première étape non terminée, ou null si le parcours est complet (il est alors enregistré comme terminé).
     */
    public static function current(User $user): ?string
    {
        if ($user->onboarding_done_at !== null) {
            return null;
        }

        foreach (array_keys(self::STEPS) as $step) {
            if (! self::done($user, $step)) {
                return $step;
            }
        }

        $user->forceFill(['onboarding_done_at' => now()])->save();

        return null;
    }

    public static function done(User $user, string $step): bool
    {
        return match ($step) {
            self::EMAIL => $user->hasVerifiedEmail(),
            self::INFO_SESSION => $user->onboarding_completed_at !== null,
            self::HISTORY => $user->history_onboarding_at !== null,
            self::PROFILE => ProfileCompleteness::missing($user) === [],
            self::STATUS => CurrentStatus::complete($user->profile),
            self::PLACEMENT => self::placementDone($user),
            default => false,
        };
    }

    /** Étapes déjà franchies ou en cours : leurs pages restent accessibles (retour en arrière possible). */
    public static function unlockedSteps(User $user): array
    {
        $current = self::current($user);

        if ($current === null) {
            return array_keys(self::STEPS);
        }

        return array_slice(array_keys(self::STEPS), 0, array_search($current, array_keys(self::STEPS), true) + 1);
    }

    /** Pas de mandat en cours : rien à demander, l'étape est considérée comme faite. */
    public static function currentMandate(): ?Mandate
    {
        return Mandate::query()->where('status', Mandate::STATUS_CURRENT)->latest('start_year')->first();
    }

    public static function placementDone(User $user): bool
    {
        $mandate = self::currentMandate();

        if ($mandate === null) {
            return true;
        }

        $approved = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->duringMandate($mandate)
            ->exists();

        if ($approved) {
            return true;
        }

        return OrgAssignment::query()
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->exists();
    }

    /**
     * État de la demande du mandat en cours : none, pending ou rejected, avec la demande et son validateur.
     *
     * @return array{state: string, membership: ?Membership, reviewer: ?User, application: ?PositionApplication}
     */
    public static function placementRequest(User $user): array
    {
        $membership = Membership::query()
            ->with(['node:id,title', 'position:id,title'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'rejected'])
            ->latest('id')
            ->first();

        $application = PositionApplication::query()->where('user_id', $user->id)->where('status', 'pending')->latest('id')->first();

        $reviewer = null;

        if ($membership?->status === 'pending') {
            $reviewerId = ApprovalRequest::query()
                ->where('approvable_type', Membership::class)
                ->where('approvable_id', $membership->id)
                ->value('assigned_reviewer_id');

            $reviewer = $reviewerId ? User::query()->find($reviewerId) : null;
        }

        $state = ($membership?->status === 'pending' || $application !== null) ? 'pending' : ($membership?->status === 'rejected' ? 'rejected' : 'none');

        return ['state' => $state, 'membership' => $membership, 'reviewer' => $reviewer, 'application' => $application];
    }
}
