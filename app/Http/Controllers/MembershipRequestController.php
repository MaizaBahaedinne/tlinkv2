<?php

namespace App\Http\Controllers;

use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Support\AdminContext;
use App\Support\MemberJourney;
use App\Support\MembershipRequests;
use App\Support\Onboarding;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
        $memberships = Membership::query()->where('user_id', $user->id)->with(['node:id,title', 'position:id,title'])->latest('id')->get();
        $rows = $this->mandateRows($user, $memberships);
        $today = now()->toDateString();

        return view('memberships.index', [
            'memberships' => $memberships,
            'stats' => [
                'active' => $memberships->filter(fn (Membership $m) => $m->status === 'approved' && ($m->ends_at === null || $m->ends_at->toDateString() >= $today))->count(),
                'pending' => $memberships->where('status', 'pending')->count(),
                'positions' => $rows->sum(fn (array $row) => $row['positions']->filter(fn ($a) => $a->ends_at === null || $a->ends_at->toDateString() >= $today)->count()),
            ],
            'scopes' => OrgScopes::all(),
            'clubs' => MembershipRequests::clubsByScope(),
            'pastClubs' => MembershipRequests::clubsByScope(true),
            'statuses' => self::STATUSES,
            'positionsByMandate' => $rows,
            'joinedMandate' => $user->joined_mandate_id ? Mandate::query()->find($user->joined_mandate_id) : null,
            'canEditPositions' => AdminContext::of($request)->isGlobal('entity.manage'),
            'missingCv' => MembershipRequests::missingCv($user->loadMissing('profile')),
            'motivationMin' => MembershipRequests::MOTIVATION_MIN,
            'motivationMax' => MembershipRequests::MOTIVATION_MAX,
        ]);
    }

    /**
     * Tous les mandats (le plus récent d'abord), même ceux où le membre n'a rien, avec ses adhésions, ses postes et ses
     * participations ; puis une ligne « hors mandat » pour ce qui ne se rattache à aucun mandat connu.
     * Une adhésion appartient au mandat où elle commence (ou, si elle a une fin, à tous ceux que sa période recoupe) ; elle n'est pas répétée dans les mandats suivants.
     *
     * @param \Illuminate\Support\Collection<int, Membership> $memberships
     * @return \Illuminate\Support\Collection<int, array{mandate: ?Mandate, positions: \Illuminate\Support\Collection, participations: \Illuminate\Support\Collection, memberships: \Illuminate\Support\Collection}>
     */
    private function mandateRows(\App\Models\User $user, $memberships)
    {
        $groups = (new MemberJourney())->byMandate($user);
        $byMandate = $groups->filter(fn (array $group) => $group['mandate'] !== null)->keyBy(fn (array $group) => $group['mandate']->id);
        $today = now()->toDateString();
        $placed = [];

        $rows = Mandate::query()->orderByDesc('start_year')->orderByDesc('id')->get()
            ->map(function (Mandate $mandate) use ($byMandate, $memberships, &$placed): array {
                $own = $memberships->filter(fn (Membership $m) => $this->entersMandate($m, $mandate))->values();

                foreach ($own as $m) {
                    $placed[$m->id] = true;
                }

                return [
                    'mandate' => $mandate,
                    'positions' => $byMandate->get($mandate->id)['positions'] ?? collect(),
                    'participations' => $byMandate->get($mandate->id)['participations'] ?? collect(),
                    'memberships' => $own,
                ];
            });

        $loose = $groups->first(fn (array $group) => $group['mandate'] === null);
        $loosePositions = $loose['positions'] ?? collect();
        $looseMemberships = $memberships->reject(fn (Membership $m) => isset($placed[$m->id]))->values();

        if ($loosePositions->isNotEmpty() || $looseMemberships->isNotEmpty()) {
            $rows->push(['mandate' => null, 'positions' => $loosePositions, 'participations' => collect(), 'memberships' => $looseMemberships]);
        }

        return $rows->values();
    }

    /** La période de l'adhésion commence dans le mandat, ou (avec une date de fin) le recoupe. */
    private function entersMandate(Membership $membership, Mandate $mandate): bool
    {
        $begin = $membership->starts_at ?? $membership->created_at;

        if ($begin === null || $begin->gt($mandate->windowEnd())) {
            return false;
        }

        return $membership->ends_at !== null
            ? $membership->ends_at->gte($mandate->windowStart())
            : $begin->gte($mandate->windowStart());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'max:80'],
            'position_id' => ['nullable', 'integer'],
            'motivation_letter' => ['required', 'string', 'max:'.MembershipRequests::MOTIVATION_MAX],
        ], [
            'target.required' => 'Choisissez où vous souhaitez adhérer.',
            'motivation_letter.required' => 'La lettre de motivation est obligatoire.',
            'motivation_letter.max' => 'La lettre de motivation est trop longue.',
        ]);

        [$scope, $nodeId] = $this->parseTarget($data['target']);

        // Parcours d'intégration : un nouveau membre rejoint un club (sans poste) ; seuls les anciens membres
        // peuvent viser une entité ou un poste (validés par leurs supérieurs).
        if (Onboarding::applies($request->user()) && $request->user()->member_origin !== 'alumni') {
            if ($nodeId === null) {
                throw ValidationException::withMessages(['target' => 'En tant que nouveau membre, vous rejoignez un club : choisissez-en un.']);
            }

            if (isset($data['position_id'])) {
                throw ValidationException::withMessages(['position_id' => 'Les postes sont réservés aux anciens membres : demandez simplement à rejoindre ce club.']);
            }
        }

        MembershipRequests::submit($request->user()->loadMissing('profile'), $scope, $nodeId, 'member', null, $request->ip(), $data['motivation_letter'], isset($data['position_id']) ? (int) $data['position_id'] : null);

        $status = isset($data['position_id'])
            ? 'Votre candidature est envoyée : elle sera examinée par un de vos supérieurs.'
            : 'Votre demande d’adhésion est envoyée : elle sera examinée par l’équipe.';

        return redirect()->route(Onboarding::applies($request->user()) ? 'integration.show' : 'memberships.index')->with('status', $status);
    }

    public function destroy(Request $request, Membership $membership): RedirectResponse
    {
        MembershipRequests::withdraw($request->user(), $membership, $request->ip());

        return redirect()->route(Onboarding::applies($request->user()) ? 'integration.show' : 'memberships.index')->with('status', 'Votre demande a été retirée.');
    }

    /**
     * Postes de membre disponibles dans la partie choisie (rechargés quand le membre change de partie).
     */
    public function positions(Request $request): JsonResponse
    {
        $data = $request->validate(['target' => ['required', 'string', 'max:80']]);

        // Lecture seule : sert aussi aux mandats passés, dont les clubs peuvent être inactifs aujourd'hui.
        [$scope, $clubId] = $this->parseTarget($data['target'], 'target', true);

        return response()->json(['positions' => MembershipRequests::memberPositions($scope, $clubId)
            ->map(fn ($node) => ['id' => $node->id, 'title' => $node->title, 'subtitle' => $node->subtitle, 'leader' => MembershipRequests::isLeaderPosition($node), 'rights' => MembershipRequests::grantsRights($node)])
            ->values()]);
    }

    /**
     * Participation à un ancien mandat : simple membre accepté directement, poste de responsabilité soumis à validation.
     */
    public function storePast(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('past', [
            'mandate_id' => ['required', 'integer', 'exists:mandates,id'],
            'past_target' => ['required', 'string', 'max:80'],
            'position_id' => ['nullable', 'integer'],
        ], [
            'past_target.required' => 'Choisissez la partie que vous avez rejointe.',
        ]);

        try {
            [$scope, $clubId] = $this->parseTarget($data['past_target'], 'past_target', true);

            $participation = MembershipRequests::declarePast(
                $request->user(),
                Mandate::query()->findOrFail($data['mandate_id']),
                $scope,
                $clubId,
                isset($data['position_id']) ? (int) $data['position_id'] : null,
                $request->ip(),
            );
        } catch (ValidationException $exception) {
            throw $exception->errorBag('past');
        }

        return redirect()->route('memberships.index')->with('status', $participation->status === 'pending'
            ? 'Votre demande est envoyée : ce poste de responsabilité doit être validé par un administrateur.'
            : 'Votre participation est enregistrée.');
    }

    public function destroyPast(Request $request, MandateParticipation $participation): RedirectResponse
    {
        MembershipRequests::withdrawPast($request->user(), $participation, $request->ip());

        return redirect()->route('memberships.index')->with('status', 'Participation retirée.');
    }

    /**
     * « scope:university » vise un périmètre, « club:12 » un club (son périmètre est celui du club).
     *
     * @return array{0: string, 1: int|null}
     *
     * @throws ValidationException
     */
    private function parseTarget(string $target, string $errorKey = 'target', bool $withInactive = false): array
    {
        if (preg_match('/^scope:([a-z0-9\-]+)$/', $target, $match) === 1 && OrgScopes::valid($match[1])) {
            return [$match[1], null];
        }

        if (preg_match('/^club:(\d+)$/', $target, $match) === 1) {
            $club = MembershipRequests::clubsByScope($withInactive)->flatten()->firstWhere('id', (int) $match[1]);

            if ($club !== null) {
                return [(string) $club->scope, (int) $club->id];
            }
        }

        throw ValidationException::withMessages([$errorKey => 'Cette adhésion n’est pas disponible.']);
    }
}
