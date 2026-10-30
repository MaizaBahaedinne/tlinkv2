<?php

namespace App\Support;

use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Parcours d'un membre dans la Fondation : postes et adhésions classés par mandat,
 * poste du mandat actuel et personnes liées (supérieurs et subordonnés).
 */
class MemberJourney
{
    /** Un mandat « 2025-2026 » court du 1er septembre 2025 au 31 août 2026 (année universitaire). */
    private const MANDATE_START_MONTH = 9;

    /** @var Collection<int, Mandate> */
    private Collection $mandates;

    /** @var Collection<int, OrgNode> */
    private Collection $nodes;

    /** @var array<int, int> id du bloc => id du mandat dont l'organigramme le contient */
    private array $nodeMandate = [];

    public function __construct()
    {
        $this->mandates = Mandate::query()->orderByDesc('start_year')->get();
        $this->nodes = OrgNode::query()->get(['id', 'parent_id', 'relation_type', 'title', 'subtitle', 'access_level'])->keyBy('id');

        foreach ($this->mandates as $mandate) {
            if ($mandate->root_node_id) {
                foreach ($this->subtree((int) $mandate->root_node_id) as $id) {
                    $this->nodeMandate[$id] ??= $mandate->id;
                }
            }
        }
    }

    public function currentMandate(): ?Mandate
    {
        return $this->mandates->firstWhere('is_current', true) ?? $this->mandates->first();
    }

    /**
     * Postes et adhésions de l'utilisateur regroupés par mandat (le plus récent d'abord).
     * Le dernier groupe, sans mandat, rassemble ce qui ne se rattache à aucun mandat connu.
     *
     * @return Collection<int, array{mandate: ?Mandate, positions: Collection<int, OrgAssignment>, memberships: Collection<int, Membership>}>
     */
    public function byMandate(User $user): Collection
    {
        $assignments = OrgAssignment::query()
            ->whereHas('person', fn ($person) => $person->where('user_id', $user->id))
            ->with(['node:id,title,subtitle,access_level', 'person:id,user_id,job_title'])
            ->orderBy('starts_at')->orderBy('id')
            ->get();

        $memberships = $user->memberships()->with('node:id,title')->orderBy('starts_at')->orderBy('id')->get();
        $participations = MandateParticipation::query()->where('user_id', $user->id)->with('node:id,title')->orderBy('id')->get();

        $groups = $this->mandates->map(fn (Mandate $mandate) => [
            'mandate' => $mandate,
            'participations' => $participations->where('mandate_id', $mandate->id)->values(),
            'positions' => $assignments->filter(fn (OrgAssignment $a) => $this->assignmentMandateId($a) === $mandate->id)->values(),
            'memberships' => $memberships->filter(fn (Membership $m) => $this->overlaps($mandate, $m->starts_at ?? $m->created_at, $m->ends_at, $m->status === 'approved' || $m->status === 'active'))->values(),
        ]);

        $loose = [
            'mandate' => null,
            'participations' => collect(),
            'positions' => $assignments->filter(fn (OrgAssignment $a) => $this->assignmentMandateId($a) === null)->values(),
            'memberships' => $memberships->filter(fn (Membership $m) => ! $this->mandates->contains(fn (Mandate $mandate) => $this->overlaps($mandate, $m->starts_at ?? $m->created_at, $m->ends_at, $m->status === 'approved' || $m->status === 'active')))->values(),
        ];

        return $groups
            ->filter(fn (array $group) => $group['positions']->isNotEmpty() || $group['memberships']->isNotEmpty() || $group['participations']->isNotEmpty())
            ->when($loose['positions']->isNotEmpty() || $loose['memberships']->isNotEmpty(), fn (Collection $all) => $all->push($loose))
            ->values();
    }

    /**
     * Postes encore occupés dans le mandat actuel, chacun avec ses supérieurs et ses subordonnés.
     *
     * @return Collection<int, array{assignment: OrgAssignment, superiors: Collection<int, array<string, mixed>>, subordinates: Collection<int, array<string, mixed>>}>
     */
    public function currentPositions(User $user): Collection
    {
        $current = $this->currentMandate();
        $today = now()->toDateString();

        $assignments = OrgAssignment::query()
            ->whereHas('person', fn ($person) => $person->where('user_id', $user->id))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with(['node:id,title,subtitle,access_level', 'person:id,user_id,job_title'])
            ->orderBy('id')
            ->get()
            ->filter(fn (OrgAssignment $a) => $current === null || ($this->nodeMandate[$a->node_id] ?? $current->id) === $current->id)
            ->values();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $upward = [];
        $downward = [];
        foreach ($assignments as $assignment) {
            $upward[$assignment->id] = $this->ancestorChain((int) $assignment->node_id);
            $downward[$assignment->id] = $this->hierarchyDescendants((int) $assignment->node_id);
        }

        $nodeIds = collect($upward)->flatten()->merge(collect($downward)->flatten())->unique()->values();

        $holders = OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with(['person.user.profile:id,user_id,photo_path'])
            ->get()
            ->groupBy('node_id');

        return $assignments->map(function (OrgAssignment $assignment) use ($upward, $downward, $holders, $user) {
            $superiors = $this->nearestHolders($upward[$assignment->id], $holders, $user);
            $subordinates = $this->nearestHolders($downward[$assignment->id], $holders, $user);

            return [
                'assignment' => $assignment,
                'superiors' => $superiors,
                'subordinates' => $subordinates,
            ];
        });
    }

    /** Le premier niveau occupé trouvé en remontant : le N+1 hiérarchique réel, même si des blocs intermédiaires sont vides. */
    private function nearestHolders(array $levels, Collection $holders, User $user): Collection
    {
        foreach ($levels as $level) {
            $people = collect($level)
                ->flatMap(fn (int $nodeId) => ($holders[$nodeId] ?? collect())->map(fn (OrgAssignment $a) => [$nodeId, $a]))
                ->filter(fn (array $pair) => $pair[1]->person !== null && (int) $pair[1]->person->user_id !== (int) $user->id)
                ->map(fn (array $pair) => [
                    'name' => $pair[1]->person->user?->name ?? $pair[1]->person->full_name,
                    'job_title' => $pair[1]->person->job_title,
                    'node_title' => $this->nodes[$pair[0]]->title ?? '—',
                    'user_id' => $pair[1]->person->user_id,
                    'photo' => $pair[1]->person->photoUrl(),
                ])
                ->unique(fn (array $row) => $row['user_id'] ?? $row['name'].$row['node_title'])
                ->values();

            if ($people->isNotEmpty()) {
                return $people;
            }
        }

        return collect();
    }

    /**
     * Blocs situés au-dessus, niveau par niveau, tant que le lien est hiérarchique.
     *
     * @return array<int, array<int, int>>
     */
    private function ancestorChain(int $nodeId): array
    {
        $levels = [];
        $node = $this->nodes[$nodeId] ?? null;

        while ($node !== null && $node->relation_type === 'hierarchy' && $node->parent_id !== null && isset($this->nodes[$node->parent_id])) {
            $levels[] = [(int) $node->parent_id];
            $node = $this->nodes[$node->parent_id];
        }

        return $levels;
    }

    /**
     * Blocs situés en dessous, niveau par niveau, tant que le lien est hiérarchique.
     *
     * @return array<int, array<int, int>>
     */
    private function hierarchyDescendants(int $nodeId): array
    {
        $levels = [];
        $frontier = [$nodeId];
        $seen = [$nodeId => true];

        while ($frontier !== []) {
            $next = $this->nodes
                ->filter(fn (OrgNode $node) => in_array($node->parent_id, $frontier, true) && $node->relation_type === 'hierarchy' && ! isset($seen[$node->id]))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($next === []) {
                break;
            }

            foreach ($next as $id) {
                $seen[$id] = true;
            }

            $levels[] = $next;
            $frontier = $next;
        }

        return $levels;
    }

    /** @return array<int, int> */
    private function subtree(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $frontier = $this->nodes->filter(fn (OrgNode $node) => in_array($node->parent_id, $frontier, true) && ! in_array($node->id, $ids, true))->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    private function assignmentMandateId(OrgAssignment $assignment): ?int
    {
        if (isset($this->nodeMandate[$assignment->node_id])) {
            return $this->nodeMandate[$assignment->node_id];
        }

        if ($assignment->starts_at !== null) {
            return $this->mandates->first(fn (Mandate $mandate) => $this->overlaps($mandate, $assignment->starts_at, $assignment->ends_at, $assignment->ends_at === null))?->id;
        }

        return null;
    }

    /** La période [début, fin] recoupe-t-elle la fenêtre du mandat ? Sans date de fin, une période en cours court jusqu'à aujourd'hui. */
    private function overlaps(Mandate $mandate, ?CarbonInterface $start, ?CarbonInterface $end, bool $ongoing): bool
    {
        if ($start === null) {
            return false;
        }

        $to = $end ?? ($ongoing ? now()->max($start) : $start);
        $windowStart = Carbon::create($mandate->start_year, self::MANDATE_START_MONTH, 1)->startOfDay();
        $windowEnd = Carbon::create($mandate->end_year, self::MANDATE_START_MONTH, 1)->subDay()->endOfDay();

        return $start->lessThanOrEqualTo($windowEnd) && $to->greaterThanOrEqualTo($windowStart);
    }
}
