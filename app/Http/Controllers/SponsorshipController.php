<?php

namespace App\Http\Controllers;

use App\Models\OrgNode;
use App\Support\AuditLogger;
use App\Support\MyOffice;
use App\Support\OrgChart\OrgChartRules;
use App\Support\PositionInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Parrainage : un membre qui occupe un poste appelle des personnes à rejoindre les postes situés sous le sien, par lien
 * direct (à partager comme il veut), QR code ou invitation par e-mail. La candidature reste examinée par l'équipe.
 */
class SponsorshipController extends Controller
{
    public function __construct(private readonly OrgChartRules $rules)
    {
    }

    public function index(Request $request): View
    {
        $selectedId = (int) $request->query('poste');
        $selected = null;

        $offices = collect(MyOffice::overview($request->user()))
            ->map(function (array $office) use ($selectedId, &$selected): array {
                $team = collect($office['team'])->filter(fn (array $row): bool => $row['node']->kind === 'position');

                if ($selectedId > 0 && $selected === null && ($row = $team->first(fn (array $row): bool => (int) $row['node']->id === $selectedId))) {
                    $node = $row['node'];
                    $selected = [
                        'node' => $node,
                        'holders' => $row['holders'],
                        'depth' => $row['depth'],
                        'closed' => $this->isClosed($node, $row['holders']),
                        'url' => $node->joinUrl(),
                        'scope' => $node->scopeLabel(),
                        'office' => $office['node']->title,
                    ];
                }

                // Organigramme horizontal : mon poste à gauche, puis un niveau de postes par colonne vers la droite.
                $tree = MyOffice::tree(['node' => $office['node'], 'holders' => $office['holders'], 'superiors' => [], 'team' => $team->values()->all()]);

                return [
                    'node' => $office['node'],
                    'tree' => $tree,
                    'depth' => (int) $team->max('depth'),
                    'count' => $team->count(),
                    'open' => $team->filter(fn (array $row): bool => ! $this->isClosed($row['node'], $row['holders']))->count(),
                    'truncated' => $office['team_truncated'],
                ];
            })
            ->values();

        return view('sponsorship.index', ['offices' => $offices, 'selected' => $selected]);
    }

    /** Poste à titulaire unique déjà occupé : les candidatures sont fermées. */
    private function isClosed(OrgNode $node, array $holders): bool
    {
        return $node->capacity !== 'multiple' && $holders !== [];
    }

    /** Invitation personnelle par e-mail pour un poste situé sous celui du membre. */
    public function invite(Request $request, OrgNode $node): RedirectResponse
    {
        $user = $request->user();

        if (! $this->sponsorable($user, $node)) {
            abort(403);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'first_name.required' => 'Indiquez le prénom de la personne.',
            'last_name.required' => 'Indiquez le nom de la personne.',
            'email.required' => 'Indiquez son adresse e-mail.',
            'email.email' => 'Cette adresse e-mail n’est pas valide.',
        ]);

        if ($node->capacity !== 'multiple' && $this->rules->currentPeopleCount($node) >= 1) {
            throw ValidationException::withMessages(['email' => 'Ce poste est déjà pourvu : il n’est plus possible d’inviter un candidat.']);
        }

        $result = PositionInvitations::send($node, [$data], $user);

        if ($result['sent'] !== []) {
            AuditLogger::record($user->id, 'sponsorship.invitation.sent', OrgNode::class, $node->id, ['position' => $node->title, 'email' => $result['sent'][0]['email']], $request->ip());

            return redirect(route('sponsorship.index', ['poste' => $node->id]).'#partage')->with('status', 'Invitation envoyée à '.$result['sent'][0]['email'].' pour le poste « '.$node->title.' ».');
        }

        $reason = $result['skipped'][0]['reason'] ?? null;

        throw ValidationException::withMessages(['email' => $reason !== null
            ? 'Cette personne '.$reason.'.'
            : 'L’e-mail n’a pas pu être envoyé. Partagez plutôt le lien ou le QR code, ou réessayez plus tard.']);
    }

    /** Le poste fait partie de ceux situés sous un poste que le membre occupe. */
    private function sponsorable($user, OrgNode $node): bool
    {
        if ($node->kind !== 'position') {
            return false;
        }

        return collect(MyOffice::overview($user))
            ->contains(fn (array $office): bool => collect($office['team'])->contains(fn (array $row): bool => (int) $row['node']->id === (int) $node->id));
    }
}
