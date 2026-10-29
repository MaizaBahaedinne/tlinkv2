<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentoringMentor;
use App\Models\MentoringTicket;
use App\Support\Mentoring\Mentoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mentorat côté administration : réception et tri des tickets par le manager mentor, annuaire des mentors.
 * Les actions sur un ticket (qualifier, affecter, clore…) passent par les routes `mentoring.*`, qui vérifient le rôle.
 */
class MentoringController extends Controller
{
    public function index(Request $request): View
    {
        $this->open();
        abort_unless(Mentoring::canSeeAll($request->user()), 403);

        $filters = $request->validate([
            'statut' => ['nullable', 'string', 'max:12'],
            'priorite' => ['nullable', Rule::in(array_keys(MentoringTicket::PRIORITIES))],
            'public' => ['nullable', Rule::in(array_keys(MentoringTicket::AUDIENCES))],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $status = $filters['statut'] ?? 'open';
        $query = MentoringTicket::query()->with(['requester', 'mentor', 'node']);

        if ($status === 'open') {
            $query->whereIn('status', MentoringTicket::OPEN);
        } elseif (isset(MentoringTicket::STATUSES[$status])) {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }

        if (! empty($filters['priorite'])) {
            $query->where('priority', $filters['priorite']);
        }

        if (! empty($filters['public'])) {
            $query->where('audience', $filters['public']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.addcslashes(trim($filters['q']), '\\%_').'%';
            $query->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('reference', 'like', $term)
                ->orWhereHas('requester', fn ($requester) => $requester->where('name', 'like', $term)));
        }

        return view('admin.mentoring.index', [
            'tickets' => $query->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->latest('id')->paginate(20)->withQueryString(),
            'counts' => MentoringTicket::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'status' => $status,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, MentoringTicket $ticket): View|RedirectResponse
    {
        $this->open();
        $user = $request->user();
        abort_unless(Mentoring::canSeeAll($user), 403);

        // Sa propre demande se suit comme celle de n'importe quel membre.
        if ((int) $ticket->requester_id === (int) $user->id) {
            return redirect()->route('mentoring.show', $ticket);
        }

        $ticket->load(['requester', 'mentor', 'node', 'rex.author']);
        $manager = Mentoring::isManagerOf($ticket, $user);

        return view('admin.mentoring.show', [
            'ticket' => $ticket,
            'events' => $ticket->events()->with('author')->get(),
            'staff' => Mentoring::isStaff($ticket, $user),
            'manager' => $manager,
            'mentors' => $manager ? Mentoring::mentors()->where('user_id', '!=', $ticket->requester_id)->values() : collect(),
            'workload' => $manager ? Mentoring::workload() : [],
        ]);
    }

    // ───────────── Annuaire des mentors ─────────────

    public function mentors(Request $request): View
    {
        $this->open();
        $user = $request->user();
        $canWrite = Mentoring::canAdminister($user);
        abort_unless($canWrite || Mentoring::canSeeAll($user), 403);

        $term = trim((string) $request->query('q', ''));

        return view('admin.mentoring.mentors', [
            'rows' => MentoringMentor::query()->with('user')->get()->sortBy(fn (MentoringMentor $row): string => mb_strtolower((string) $row->user?->name))->values(),
            'workload' => Mentoring::workload(),
            'term' => $term,
            'found' => $canWrite ? Mentoring::searchMembers($term) : collect(),
            'canWrite' => $canWrite,
            'managers' => count(Mentoring::managerIds()),
        ]);
    }

    public function storeMentor(Request $request): RedirectResponse
    {
        $this->writer($request);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'manager' => ['nullable', 'boolean'], 'specialties' => ['nullable', 'string', 'max:255']]);

        Mentoring::saveMentor($request->user(), (int) $data['user_id'], (bool) ($data['manager'] ?? false), $data['specialties'] ?? null);

        return redirect()->route('admin.mentoring.mentors.index')->with('status', 'Mentor ajouté à l’annuaire.');
    }

    public function updateMentor(Request $request, MentoringMentor $mentor): RedirectResponse
    {
        $this->writer($request);
        $data = $request->validate(['specialties' => ['nullable', 'string', 'max:255'], 'manager' => ['nullable', 'boolean'], 'active' => ['nullable', 'boolean']]);

        Mentoring::updateMentor($mentor, $request->user(), (bool) ($data['manager'] ?? false), (bool) ($data['active'] ?? false), $data['specialties'] ?? null);

        return redirect()->route('admin.mentoring.mentors.index')->with('status', 'Mentor mis à jour.');
    }

    public function destroyMentor(Request $request, MentoringMentor $mentor): RedirectResponse
    {
        $this->writer($request);

        Mentoring::removeMentor($mentor, $request->user());

        return redirect()->route('admin.mentoring.mentors.index')->with('status', 'Mentor retiré de l’annuaire.');
    }

    private function open(): void
    {
        abort_unless(Mentoring::enabled(), 404);
    }

    private function writer(Request $request): void
    {
        $this->open();
        abort_unless(Mentoring::canAdminister($request->user()), 403);
    }
}
