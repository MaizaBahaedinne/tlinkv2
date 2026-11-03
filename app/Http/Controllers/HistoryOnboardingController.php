<?php

namespace App\Http\Controllers;

use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Support\AuditLogger;
use App\Support\MembershipRequests;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Accueil après la validation de l'info session : le membre dit s'il est ancien ou nouveau,
 * déclare son mandat et son poste d'arrivée (première adhésion), puis complète les mandats suivants.
 * Sert à reconstituer l'historique de la Fondation.
 */
class HistoryOnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at === null) {
            return redirect()->route('info-session.show');
        }

        $closed = Mandate::query()->where('status', Mandate::STATUS_CLOSED)->orderByDesc('start_year')->orderByDesc('id')->get();
        $joined = $user->joined_mandate_id ? Mandate::query()->find($user->joined_mandate_id) : null;

        $step = match (true) {
            $joined !== null => 3,
            $user->member_origin === 'alumni' => 2,
            default => 1,
        };

        $declared = $joined === null ? collect() : MandateParticipation::query()
            ->where('user_id', $user->id)
            ->with('node:id,title')
            ->orderBy('id')
            ->get()
            ->groupBy('mandate_id');

        return view('onboarding.history', [
            'step' => $step,
            'closedMandates' => $closed,
            'joined' => $joined,
            'followingMandates' => $joined === null ? collect() : $closed->filter(fn (Mandate $mandate) => ! $mandate->precedes($joined))->sortBy('start_year')->values(),
            'declared' => $declared,
            'scopes' => OrgScopes::all(),
            'clubs' => MembershipRequests::clubsByScope(),
        ]);
    }

    public function origin(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->onboarding_completed_at === null, 403);

        if ($user->joined_mandate_id !== null) {
            return redirect()->route('onboarding.history');
        }

        $data = $request->validate(['origin' => ['required', 'in:new,alumni']], ['origin.required' => 'Indiquez si vous êtes un ancien ou un nouveau membre.']);

        $user->forceFill([
            'member_origin' => $data['origin'],
            'history_onboarding_at' => $data['origin'] === 'new' ? now() : null,
        ])->save();

        AuditLogger::record($user->id, 'onboarding.origin', \App\Models\User::class, $user->id, ['origin' => $data['origin']], ip: $request->ip());

        return $data['origin'] === 'new'
            ? redirect()->route('memberships.index')->with('status', 'Bienvenue ! Faites votre première demande d’adhésion pour rejoindre la Fondation.')
            : redirect()->route('onboarding.history');
    }

    public function entry(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->onboarding_completed_at === null, 403);

        if ($user->member_origin !== 'alumni' || $user->joined_mandate_id !== null) {
            return redirect()->route('onboarding.history');
        }

        $data = $request->validate([
            'mandate_id' => ['required', 'integer', 'exists:mandates,id'],
            'past_target' => ['required', 'string', 'max:80'],
            'position_id' => ['nullable', 'integer'],
        ], [
            'mandate_id.required' => 'Choisissez le mandat où vous avez rejoint le réseau.',
            'past_target.required' => 'Choisissez la partie que vous avez rejointe.',
        ]);

        [$scope, $clubId] = $this->parseTarget($data['past_target']);
        $mandate = Mandate::query()->findOrFail($data['mandate_id']);

        // Première adhésion pré-remplie : elle suit le circuit habituel (membre accepté, responsabilité validée par un administrateur).
        MembershipRequests::declarePast($user, $mandate, $scope, $clubId, isset($data['position_id']) ? (int) $data['position_id'] : null, $request->ip());

        $user->forceFill(['joined_mandate_id' => $mandate->id, 'history_onboarding_at' => now()])->save();

        AuditLogger::record($user->id, 'onboarding.joined_mandate', \App\Models\User::class, $user->id, ['mandate' => $mandate->label, 'scope' => $scope, 'node_id' => $clubId], ip: $request->ip());

        return redirect()->route('onboarding.history')->with('status', 'Votre première adhésion est enregistrée.');
    }

    /**
     * @return array{0: string, 1: int|null}
     *
     * @throws ValidationException
     */
    private function parseTarget(string $target): array
    {
        if (preg_match('/^scope:([a-z0-9\-]+)$/', $target, $match) === 1 && OrgScopes::valid($match[1])) {
            return [$match[1], null];
        }

        if (preg_match('/^club:(\d+)$/', $target, $match) === 1) {
            $club = MembershipRequests::clubsByScope()->flatten()->firstWhere('id', (int) $match[1]);

            if ($club !== null) {
                return [(string) $club->scope, (int) $club->id];
            }
        }

        throw ValidationException::withMessages(['past_target' => 'Cette partie n’est pas disponible.']);
    }
}
