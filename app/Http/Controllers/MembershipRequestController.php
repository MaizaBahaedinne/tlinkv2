<?php

namespace App\Http\Controllers;

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
            'positionsByMandate' => (new MemberJourney())->byMandate($user)->filter(fn (array $group) => $group['positions']->isNotEmpty())->values(),
            'canEditPositions' => AdminContext::of($request)->isGlobal('entity.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'max:80'],
        ], ['target.required' => 'Choisissez où vous souhaitez adhérer.']);

        [$scope, $nodeId] = $this->parseTarget($data['target']);

        MembershipRequests::submit($request->user(), $scope, $nodeId, 'member', null, $request->ip());

        return redirect()->route('memberships.index')->with('status', 'Votre demande d’adhésion est envoyée : elle sera examinée par l’équipe.');
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
