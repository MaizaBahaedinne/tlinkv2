<?php

namespace App\Http\Controllers;

use App\Models\Mandate;
use App\Models\Membership;
use App\Support\AdminContext;
use App\Support\MemberJourney;
use App\Support\MembershipRequests;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * « Mes adhésions » : le membre suit ses adhésions et demande à rejoindre une entité ou un club.
 */
class MembershipRequestController extends Controller
{
    public const STATUSES = [
        'pending' => ['En attente de validation', 'bg-amber-50 text-amber-700'],
        'approved' => ['Adhérent(e)', 'bg-emerald-50 text-emerald-700'],
        'rejected' => ['Refusée', 'bg-rose-50 text-rose-700'],
        'suspended' => ['Suspendue', 'bg-slate-100 text-slate-600'],
        'archived' => ['Terminée', 'bg-slate-100 text-slate-600'],
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('memberships.index', [
            'memberships' => Membership::query()->where('user_id', $user->id)->with('node:id,title')->latest('id')->get(),
            'scopes' => OrgScopes::all(),
            'clubs' => MembershipRequests::clubsByScope(),
            'statuses' => self::STATUSES,
            'positionsByMandate' => $this->mandateRows($user),
            'canEditPositions' => AdminContext::of($request)->isGlobal('entity.manage'),
            'missingCv' => MembershipRequests::missingCv($user->loadMissing('profile')),
            'motivationMin' => MembershipRequests::MOTIVATION_MIN,
            'motivationMax' => MembershipRequests::MOTIVATION_MAX,
        ]);
    }

    /**
     * Tous les mandats (le plus récent d'abord), même ceux où le membre n'a pas de poste,
     * suivis des postes hors mandat s'il y en a.
     *
     * @return \Illuminate\Support\Collection<int, array{mandate: ?Mandate, positions: \Illuminate\Support\Collection}>
     */
    private function mandateRows(\App\Models\User $user)
    {
        $groups = (new MemberJourney())->byMandate($user);
        $byMandate = $groups->filter(fn (array $group) => $group['mandate'] !== null)->keyBy(fn (array $group) => $group['mandate']->id);

        $rows = Mandate::query()->orderByDesc('start_year')->orderByDesc('id')->get()
            ->map(fn (Mandate $mandate) => ['mandate' => $mandate, 'positions' => $byMandate->get($mandate->id)['positions'] ?? collect()]);

        $loose = $groups->first(fn (array $group) => $group['mandate'] === null);

        if ($loose !== null && $loose['positions']->isNotEmpty()) {
            $rows->push(['mandate' => null, 'positions' => $loose['positions']]);
        }

        return $rows->values();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'max:80'],
            'motivation_letter' => ['required', 'string', 'max:'.MembershipRequests::MOTIVATION_MAX],
        ], [
            'target.required' => 'Choisissez où vous souhaitez adhérer.',
            'motivation_letter.required' => 'La lettre de motivation est obligatoire.',
            'motivation_letter.max' => 'La lettre de motivation est trop longue.',
        ]);

        [$scope, $nodeId] = $this->parseTarget($data['target']);

        MembershipRequests::submit($request->user()->loadMissing('profile'), $scope, $nodeId, 'member', null, $request->ip(), $data['motivation_letter']);

        return redirect()->route('memberships.index')->with('status', 'Votre demande d’adhésion est envoyée : elle sera examinée par l’équipe.');
    }

    public function destroy(Request $request, Membership $membership): RedirectResponse
    {
        MembershipRequests::withdraw($request->user(), $membership, $request->ip());

        return redirect()->route('memberships.index')->with('status', 'Votre demande a été retirée.');
    }

    /**
     * « scope:university » vise un périmètre, « club:12 » un club (son périmètre est celui du club).
     *
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

        throw ValidationException::withMessages(['target' => 'Cette adhésion n’est pas disponible.']);
    }
}
