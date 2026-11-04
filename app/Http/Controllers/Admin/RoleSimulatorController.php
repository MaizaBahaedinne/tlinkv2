<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrgNode;
use App\Support\AccessLevels;
use App\Support\AdminContext;
use App\Support\AdminMenu;
use App\Support\AuditLogger;
use App\Support\OrgAccess;
use App\Support\RoleSimulator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Simulateur de rôles : un administrateur global agit « comme » le titulaire d'un poste pour tester l'interface
 * et les autorisations de ce poste. Les postes d'administrateur global et de gestionnaire des postes et des droits
 * ne sont pas simulables. Chaque début et fin de simulation est tracé dans le journal d'audit.
 */
class RoleSimulatorController extends AdminController
{
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $this->authorizeUse($request);

        $term = trim((string) $request->query('q', ''));
        $scopeMap = OrgAccess::scopeMap();

        $nodes = OrgNode::query()
            ->where('kind', 'position')
            ->where('access_level', '!=', AccessLevels::NONE)
            ->when($term !== '', fn ($query) => $query->where(function ($inner) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $inner->where('title', 'like', $like)->orWhere('subtitle', 'like', $like);
            }))
            ->with(['parent:id,title', 'assignments' => fn ($query) => $query->with('person:id,user_id,full_name')])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (OrgNode $node): bool => RoleSimulator::eligible($node)
                // Un poste sans périmètre n'accorde aucun droit : il n'y a rien à simuler.
                && (AccessLevels::reachesEverywhere((string) $node->access_level, $node->permissions)
                    || ($scopeMap[$node->id] ?? null) !== null
                    || filled($node->extra_scopes)))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $positions = new LengthAwarePaginator(
            $nodes->forPage($page, self::PER_PAGE)->values(),
            $nodes->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.simulator.index', [
            'positions' => $positions,
            'term' => $term,
            'scopeMap' => $scopeMap,
            'levels' => AccessLevels::BADGES,
        ]);
    }

    public function start(Request $request, OrgNode $node): RedirectResponse
    {
        $this->authorizeUse($request);

        abort_unless(RoleSimulator::isSimulable($node), 403, 'Ce poste ne peut pas être simulé (administrateur global, gestion des postes et des droits ou poste sans droit).');

        $user = $request->user();

        $request->session()->put(RoleSimulator::SESSION_KEY, ['user_id' => $user->id, 'node_id' => $node->id]);
        RoleSimulator::activate($user, $node);

        $this->audit($request, 'admin.simulation.started', $node, ['title' => $node->title]);

        $target = AdminMenu::hasAccess(new AdminContext($user)) ? 'admin.dashboard' : 'home';

        return redirect()->route($target)->with('status', 'Simulation en cours : vous agissez comme « '.$node->title.' ».');
    }

    /** Hors du groupe admin : pendant la simulation, l'utilisateur n'a plus les droits d'administration. */
    public function stop(Request $request): RedirectResponse
    {
        $state = (array) $request->session()->get(RoleSimulator::SESSION_KEY);

        abort_unless($state !== [], 403, 'Aucune simulation de rôle en cours.');

        $request->session()->forget(RoleSimulator::SESSION_KEY);
        RoleSimulator::deactivate();

        AuditLogger::record($request->user()->id, 'admin.simulation.stopped', OrgNode::class, (int) ($state['node_id'] ?? 0), [], $request->ip());

        return redirect()->route('admin.simulator.index')->with('status', 'Simulation terminée : vous retrouvez vos droits.');
    }

    /** Réservé aux administrateurs globaux (droits réels), hors simulation en cours. */
    private function authorizeUse(Request $request): void
    {
        abort_if(RoleSimulator::active(), 403, 'Terminez la simulation en cours avant d’en démarrer une autre.');
        abort_unless(RoleSimulator::canUse($request->user()), 403, 'Seuls les administrateurs globaux peuvent utiliser le simulateur de rôles.');
    }
}
