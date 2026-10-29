<?php

namespace App\Http\Controllers;

use App\Models\MentoringTicket;
use App\Models\User;
use App\Support\Mentoring\Mentoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mentorat : tickets des membres, suivi par les mentors, tri par le manager mentor et annuaire des mentors.
 */
class MentoringController extends Controller
{
    // ───────────── Espace du membre ─────────────

    public function index(Request $request): View
    {
        $this->open();
        $user = $request->user();

        return view('mentoring.index', [
            'tickets' => MentoringTicket::query()->with(['mentor', 'node'])->where('requester_id', $user->id)->latest('id')->paginate(15),
            'tabs' => $this->tabs($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->open();

        return view('mentoring.create', ['clubs' => Mentoring::clubsOf($request->user()), 'tabs' => $this->tabs($request->user())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->open();

        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(MentoringTicket::AUDIENCES))],
            'type' => ['required', Rule::in(array_keys(MentoringTicket::TYPES))],
            'collective_type' => ['nullable', Rule::in(array_keys(MentoringTicket::COLLECTIVES))],
            'collective_name' => ['nullable', 'string', 'max:160'],
            'node_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:'.Mentoring::MAX_TEXT],
        ], [
            'description.min' => 'Décrivez la situation en quelques phrases (20 caractères minimum).',
        ]);

        if ($data['audience'] === 'collective' && empty($data['node_id']) && trim((string) ($data['collective_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['collective_name' => 'Indiquez le collectif concerné (club, projet, chapter…).']);
        }

        $ticket = Mentoring::submit($request->user(), $data);

        return redirect()->route('mentoring.show', $ticket)->with('status', 'Votre demande '.$ticket->reference.' a été transmise aux managers mentors.');
    }

    // ───────────── Espaces mentor et manager ─────────────

    public function assigned(Request $request): View
    {
        $this->open();
        $user = $request->user();
        abort_unless(Mentoring::isMentor($user), 403);

        return view('mentoring.assigned', [
            'tickets' => MentoringTicket::query()->with(['requester', 'node'])->where('mentor_id', $user->id)
                ->orderByRaw("case when status in ('assigned','in_progress') then 0 else 1 end")->latest('id')->paginate(15),
            'tabs' => $this->tabs($user),
        ]);
    }

    // ───────────── Fiche d'un ticket ─────────────

    public function show(Request $request, MentoringTicket $ticket): View|RedirectResponse
    {
        $this->open();
        $user = $request->user();

        // Le tri (qualifier, affecter, clore) se fait dans l'administration : le manager mentor et les postes habilités y sont envoyés.
        if (! $this->isRequester($ticket, $user) && (int) $ticket->mentor_id !== (int) $user->id && Mentoring::canSeeAll($user)) {
            return redirect()->route('admin.mentoring.tickets.show', $ticket);
        }

        abort_unless(Mentoring::canView($ticket, $user), 403);

        $staff = Mentoring::isStaff($ticket, $user);
        $ticket->load(['requester', 'mentor', 'node', 'rex.author']);

        $events = $ticket->events()->with('author')
            ->when(! $staff, fn ($query) => $query->whereIn('kind', \App\Models\MentoringEvent::PUBLIC_KINDS))
            ->get();

        return view('mentoring.show', [
            'ticket' => $ticket,
            'events' => $events,
            'staff' => $staff,
            'adminUrl' => Mentoring::canSeeAll($user) ? route('admin.mentoring.tickets.show', $ticket) : null,
            'tabs' => $this->tabs($user),
        ]);
    }

    public function comment(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        $user = $this->actor($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.Mentoring::MAX_TEXT], 'internal' => ['nullable', 'boolean']]);

        Mentoring::comment($ticket, $user, $data['body'], (bool) ($data['internal'] ?? false));

        return $this->backTo($request, $ticket)->withFragment('fil');
    }

    public function qualify(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        $user = $this->managerActor($request, $ticket);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(MentoringTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(MentoringTicket::PRIORITIES))],
            'qualification_note' => ['nullable', 'string', 'max:'.Mentoring::MAX_TEXT],
        ]);

        Mentoring::qualify($ticket, $user, $data['category'], $data['priority'], $data['qualification_note'] ?? null);

        return $this->backTo($request, $ticket)->with('status', 'Ticket qualifié.');
    }

    public function reject(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        $user = $this->managerActor($request, $ticket);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Indiquez le motif communiqué au demandeur.']);

        Mentoring::reject($ticket, $user, $data['reason']);

        return $this->backTo($request, $ticket)->with('status', 'Ticket écarté.');
    }

    public function assign(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        $user = $this->managerActor($request, $ticket);
        $data = $request->validate(['mentor_id' => ['required', 'integer']], ['mentor_id.required' => 'Choisissez un mentor.']);

        Mentoring::assign($ticket, $user, (int) $data['mentor_id']);

        return $this->backTo($request, $ticket)->with('status', 'Ticket affecté.');
    }

    public function start(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        Mentoring::start($ticket, $this->staffActor($request, $ticket));

        return $this->backTo($request, $ticket)->with('status', 'Accompagnement démarré.');
    }

    public function rex(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        $user = $this->staffActor($request, $ticket);
        $rules = ['required', 'string', 'max:'.Mentoring::MAX_TEXT];
        $data = $request->validate([
            'context' => $rules,
            'actions' => $rules,
            'outcome' => $rules,
            'lessons' => $rules,
            'recommendations' => ['nullable', 'string', 'max:'.Mentoring::MAX_TEXT],
        ]);

        Mentoring::saveRex($ticket, $user, $data);

        return $this->backTo($request, $ticket)->with('status', 'REX enregistré.')->withFragment('rex');
    }

    public function resolve(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        Mentoring::resolve($ticket, $this->staffActor($request, $ticket));

        return $this->backTo($request, $ticket)->with('status', 'Ticket résolu.');
    }

    public function close(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        Mentoring::close($ticket, $this->managerActor($request, $ticket));

        return $this->backTo($request, $ticket)->with('status', 'Ticket clôturé.');
    }

    public function reopen(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        Mentoring::reopen($ticket, $this->managerActor($request, $ticket));

        return $this->backTo($request, $ticket)->with('status', 'Ticket rouvert.');
    }

    // ───────────── Aides ─────────────

    /** Retour sur la fiche du ticket : dans l'administration quand l'action en vient (manager mentor), sinon côté membre. */
    private function backTo(Request $request, MentoringTicket $ticket): RedirectResponse
    {
        $user = $request->user();
        $admin = $request->input('ctx') === 'admin' && ! $this->isRequester($ticket, $user) && Mentoring::canSeeAll($user);

        return redirect()->route($admin ? 'admin.mentoring.tickets.show' : 'mentoring.show', $ticket);
    }

    private function isRequester(MentoringTicket $ticket, User $user): bool
    {
        return (int) $ticket->requester_id === (int) $user->id;
    }

    private function open(): void
    {
        abort_unless(Mentoring::enabled(), 404);
    }

    /** Acteur d'un ticket qu'il peut consulter (demandeur ou équipe de mentors). */
    private function actor(Request $request, MentoringTicket $ticket): User
    {
        $this->open();
        abort_unless(Mentoring::canView($ticket, $request->user()), 403);

        return $request->user();
    }

    private function staffActor(Request $request, MentoringTicket $ticket): User
    {
        $this->open();
        abort_unless(Mentoring::isStaff($ticket, $request->user()), 403);

        return $request->user();
    }

    private function managerActor(Request $request, MentoringTicket $ticket): User
    {
        $this->open();
        abort_unless(Mentoring::isManagerOf($ticket, $request->user()), 403);

        return $request->user();
    }

    /** Onglets de navigation du mentorat selon le rôle du membre. @return array<int, array{label: string, route: string, match: string}> */
    private function tabs(User $user): array
    {
        $tabs = [['label' => 'Mes demandes', 'route' => 'mentoring.index', 'match' => ['mentoring.index', 'mentoring.create']]];

        if (Mentoring::isMentor($user)) {
            $tabs[] = ['label' => 'Mes accompagnements', 'route' => 'mentoring.assigned', 'match' => ['mentoring.assigned']];
        }

        return $tabs;
    }
}
