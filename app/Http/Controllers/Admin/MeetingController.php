<?php

namespace App\Http\Controllers\Admin;

use App\Models\AppNotification;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingAttendee;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\ClubReach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Réunions d'un club ou de la Fondation : date, lieu, sujet, durée, ordre du jour, invitations (avec notification),
 * réponses, présences et décision prise sur chaque point de l'ordre du jour.
 */
class MeetingController extends AdminController
{
    private const MODULE = 'meetings.meetings';

    public function index(Request $request): View
    {
        $user = $request->user();
        $when = in_array($request->query('when'), ['upcoming', 'past', 'all'], true) ? (string) $request->query('when') : 'upcoming';
        $club = $request->integer('club') ?: null;

        $meetings = ClubReach::scope(Meeting::query(), $user)
            ->with(['club:id,title'])
            ->withCount(['items', 'attendees'])
            ->when($when === 'upcoming', fn ($query) => $query->where('status', 'planned')->where('starts_at', '>=', now()->subDay())->orderBy('starts_at'))
            ->when($when === 'past', fn ($query) => $query->where(fn ($inner) => $inner->where('status', '!=', 'planned')->orWhere('starts_at', '<', now()->subDay()))->orderByDesc('starts_at'))
            ->when($when === 'all', fn ($query) => $query->orderByDesc('starts_at'))
            ->when($club, fn ($query) => $query->where('club_id', $club))
            ->paginate(15)->withQueryString();

        return view('admin.meetings.index', [
            'meetings' => $meetings,
            'filters' => ['when' => $when, 'club' => $club],
            'clubOptions' => ClubReach::options($user),
            'canCreate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['create']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        return view('admin.meetings.form', $this->formData($request, new Meeting([
            'club_id' => $request->integer('club') ?: null,
            'duration_minutes' => 60,
            'starts_at' => now()->addDays(3)->setTime(18, 0),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $data = $this->validated($request);
        ClubReach::validate($request->user(), $data['club_id']);

        $meeting = DB::transaction(function () use ($data, $request): Meeting {
            $meeting = Meeting::query()->create($data + ['organizer_id' => $request->user()->id, 'created_by' => $request->user()->id, 'status' => 'planned']);

            // L'organisateur est de fait présent à sa réunion.
            $meeting->attendees()->create(['user_id' => $request->user()->id, 'response' => 'accepted', 'responded_at' => now()]);

            return $meeting;
        });

        $this->audit($request, 'admin.meeting.created', $meeting, ['title' => $meeting->title, 'club_id' => $meeting->club_id, 'starts_at' => $meeting->starts_at->toDateTimeString()]);

        return redirect()->route('admin.meetings.show', $meeting)->with('status', 'Réunion créée : ajoutez maintenant l’ordre du jour et les invités.');
    }

    public function show(Request $request, Meeting $meeting): View
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $meeting->load(['club:id,title', 'organizer:id,name', 'items.presenter:id,name', 'attendees.user:id,name']);

        $term = trim(mb_substr((string) $request->query('u', ''), 0, 80));
        $found = collect();

        if (mb_strlen($term) >= 2) {
            $query = User::query()->where('is_active', true)->whereNotIn('id', $meeting->attendees->pluck('user_id'));

            foreach (array_slice(preg_split('/\s+/u', $term), 0, 4) as $word) {
                $like = '%'.addcslashes($word, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
            }

            $found = $query->orderBy('name')->limit(10)->get(['id', 'name', 'email']);
        }

        $planned = (int) $meeting->items->sum('duration_minutes');

        return view('admin.meetings.show', [
            'meeting' => $meeting,
            'term' => $term,
            'found' => $found,
            'toInvite' => $meeting->attendees->filter(fn (MeetingAttendee $a): bool => $a->invited_at === null && $a->user_id !== $meeting->organizer_id)->count(),
            'plannedMinutes' => $planned,
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['update']),
            'canDelete' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['delete']),
            'attendeeOptions' => $meeting->attendees->pluck('user.name', 'user_id')->all(),
        ]);
    }

    public function edit(Request $request, Meeting $meeting): View
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        return view('admin.meetings.form', $this->formData($request, $meeting));
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $data = $this->validated($request);
        ClubReach::validate($request->user(), $data['club_id']);

        $meeting->fill($data);
        $moved = $meeting->isDirty(['starts_at', 'duration_minutes', 'location', 'online_url']);
        $meeting->save();

        if ($moved && $meeting->invitations_sent_at !== null && $meeting->isOpen()) {
            $this->notify($meeting, 'meeting.updated', 'Réunion modifiée', 'La date ou le lieu de « '.$meeting->title.' » a changé : '.$this->when($meeting).'.');
        }

        $this->audit($request, 'admin.meeting.updated', $meeting, ['title' => $meeting->title, 'moved' => $moved]);

        return redirect()->route('admin.meetings.show', $meeting)->with('status', 'Réunion mise à jour.'.($moved && $meeting->invitations_sent_at ? ' Les invités ont été prévenus.' : ''));
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $this->audit($request, 'admin.meeting.deleted', $meeting, ['title' => $meeting->title]);
        $title = $meeting->title;
        $meeting->delete();

        return redirect()->route('admin.meetings.index')->with('status', 'Réunion « '.$title.' » supprimée.');
    }

    /** Planifiée, tenue ou annulée. */
    public function status(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Meeting::STATUSES))]]);
        $before = $meeting->status;

        $meeting->update(['status' => $data['status']]);

        if ($data['status'] === 'cancelled' && $before !== 'cancelled' && $meeting->invitations_sent_at !== null) {
            $this->notify($meeting, 'meeting.cancelled', 'Réunion annulée', 'La réunion « '.$meeting->title.' » prévue '.$this->when($meeting).' est annulée.');
        }

        $this->audit($request, 'admin.meeting.status', $meeting, ['title' => $meeting->title, 'from' => $before, 'to' => $meeting->status]);

        return back()->with('status', 'Réunion : '.mb_strtolower(Meeting::STATUSES[$meeting->status]).'.');
    }

    public function minutes(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $data = $request->validate(['minutes' => ['nullable', 'string', 'max:20000']]);
        $meeting->update(['minutes' => $data['minutes'] ?? null]);
        $this->audit($request, 'admin.meeting.minutes', $meeting, ['title' => $meeting->title]);

        return back()->with('status', 'Compte rendu enregistré.');
    }

    /* ------------------------------------------------------------------ Ordre du jour */

    public function storeItem(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $data = $this->validatedItem($request, $meeting);
        $meeting->items()->create($data + ['position' => ((int) $meeting->items()->max('position')) + 1]);
        $this->audit($request, 'admin.meeting.item.created', $meeting, ['title' => $data['title']]);

        return back()->with('status', 'Point ajouté à l’ordre du jour.');
    }

    public function updateItem(Request $request, Meeting $meeting, MeetingAgendaItem $item): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);
        abort_unless($item->meeting_id === $meeting->id, 404);

        $before = $item->outcome;
        $item->update($this->validatedItem($request, $meeting));

        $this->audit($request, 'admin.meeting.item.updated', $meeting, ['title' => $item->title, 'outcome' => $item->outcome, 'changed_outcome' => $before !== $item->outcome]);

        return back()->with('status', 'Point mis à jour.');
    }

    public function destroyItem(Request $request, Meeting $meeting, MeetingAgendaItem $item): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);
        abort_unless($item->meeting_id === $meeting->id, 404);

        $this->audit($request, 'admin.meeting.item.deleted', $meeting, ['title' => $item->title]);
        $item->delete();

        return back()->with('status', 'Point retiré de l’ordre du jour.');
    }

    public function moveItem(Request $request, Meeting $meeting, MeetingAgendaItem $item): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);
        abort_unless($item->meeting_id === $meeting->id, 404);

        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $items = $meeting->items()->get()->values();
        $index = $items->search(fn (MeetingAgendaItem $row): bool => $row->id === $item->id);
        $target = $data['direction'] === 'up' ? $index - 1 : $index + 1;

        if ($target >= 0 && $target < $items->count()) {
            $order = $items->all();
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

            foreach ($order as $position => $row) {
                $row->update(['position' => $position + 1]);
            }
        }

        return back();
    }

    /* ------------------------------------------------------------------ Invités et présences */

    /** Ajoute des invités : membres choisis, équipe du club ou membres du club. */
    public function addAttendees(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $data = $request->validate([
            'user_ids' => ['nullable', 'array', 'max:50'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'group' => ['nullable', Rule::in(['team', 'members'])],
        ]);

        $ids = collect($data['user_ids'] ?? []);

        if (($data['group'] ?? null) !== null) {
            abort_if($meeting->club_id === null, 422, 'Cette réunion n’est liée à aucun club.');
            $ids = $ids->merge($data['group'] === 'team' ? $this->teamUserIds($meeting->club_id) : $this->memberUserIds($meeting->club_id));
        }

        $existing = $meeting->attendees()->pluck('user_id')->all();
        $new = $ids->map(fn ($id): int => (int) $id)->unique()->diff($existing)->take(300);

        foreach (User::query()->whereIn('id', $new)->where('is_active', true)->pluck('id') as $userId) {
            $meeting->attendees()->create(['user_id' => $userId]);
        }

        $this->audit($request, 'admin.meeting.attendees.added', $meeting, ['title' => $meeting->title, 'count' => $new->count()]);

        return redirect()->route('admin.meetings.show', $meeting)->with('status', $new->count().' invité(s) ajouté(s). Envoyez les invitations pour les prévenir.');
    }

    public function removeAttendee(Request $request, Meeting $meeting, MeetingAttendee $attendee): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        $attendee->delete();

        return back()->with('status', 'Invité retiré.');
    }

    /** Présences constatées (une fois la réunion tenue ou en cours). */
    public function attendance(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);

        $data = $request->validate([
            'attendance' => ['nullable', 'array'],
            'attendance.*' => ['nullable', Rule::in(array_keys(MeetingAttendee::ATTENDANCE))],
        ]);

        foreach ($meeting->attendees as $attendee) {
            if (array_key_exists($attendee->user_id, $data['attendance'] ?? [])) {
                $attendee->update(['attendance' => $data['attendance'][$attendee->user_id] ?: null]);
            }
        }

        $this->audit($request, 'admin.meeting.attendance', $meeting, ['title' => $meeting->title]);

        return back()->with('status', 'Présences enregistrées.');
    }

    /** Notifie les invités qui ne l'ont pas encore été. */
    public function sendInvitations(Request $request, Meeting $meeting): RedirectResponse
    {
        ClubReach::authorize($request->user(), $meeting->club_id);
        abort_unless($meeting->isOpen(), 422, 'Seule une réunion planifiée peut être envoyée.');

        $pending = $meeting->attendees()->whereNull('invited_at')->where('user_id', '!=', $meeting->organizer_id)->get();

        foreach ($pending as $attendee) {
            $this->tell($attendee->user_id, 'meeting.invited', 'Invitation à une réunion', '« '.$meeting->title.' » '.$this->when($meeting).'. Répondez à l’invitation.');
            $attendee->update(['invited_at' => now()]);
        }

        if ($pending->isNotEmpty()) {
            $meeting->update(['invitations_sent_at' => now()]);
        }

        $this->audit($request, 'admin.meeting.invited', $meeting, ['title' => $meeting->title, 'count' => $pending->count()]);

        return back()->with('status', $pending->isEmpty() ? 'Tous les invités ont déjà reçu leur invitation.' : $pending->count().' invitation(s) envoyée(s).');
    }

    /* ------------------------------------------------------------------ Utilitaires */

    /** @return array<string, mixed> */
    private function formData(Request $request, Meeting $meeting): array
    {
        return [
            'meeting' => $meeting,
            'clubOptions' => ClubReach::options($request->user()),
            'canBeFoundation' => ClubReach::isGlobal($request->user()),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'club_id' => ['nullable', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')],
            'title' => ['required', 'string', 'max:190'],
            'subject' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'location' => ['nullable', 'string', 'max:190'],
            'online_url' => ['nullable', 'url:http,https', 'max:255'],
            'guests' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'Donnez un intitulé à la réunion.',
            'starts_at.required' => 'Indiquez la date et l’heure.',
        ]);

        $data['club_id'] = ($data['club_id'] ?? null) ?: null;

        return $data;
    }

    /** @return array<string, mixed> */
    private function validatedItem(Request $request, Meeting $meeting): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'presenter_id' => ['nullable', 'integer'],
            'outcome' => ['nullable', Rule::in(array_keys(MeetingAgendaItem::OUTCOMES))],
            'decision' => ['nullable', 'string', 'max:5000'],
        ], ['title.required' => 'Donnez un intitulé au point.']);

        $presenter = ($data['presenter_id'] ?? null) ?: null;

        if ($presenter !== null && ! $meeting->attendees()->where('user_id', $presenter)->exists()) {
            $presenter = null;
        }

        $data['presenter_id'] = $presenter;
        $data['outcome'] = $data['outcome'] ?? 'pending';

        return $data;
    }

    private function when(Meeting $meeting): string
    {
        return 'le '.$meeting->starts_at->format('d/m/Y à H:i').($meeting->location ? ' ('.$meeting->location.')' : '');
    }

    /** Notifie les invités déjà prévenus. */
    private function notify(Meeting $meeting, string $type, string $title, string $body): void
    {
        foreach ($meeting->attendees()->whereNotNull('invited_at')->get() as $attendee) {
            $this->tell($attendee->user_id, $type, $title, $body);
        }
    }

    private function tell(int $userId, string $type, string $title, string $body): void
    {
        AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => ['url' => route('meetings.mine', [], false)],
        ]);
    }

    /** @return array<int, int> */
    private function teamUserIds(int $clubId): array
    {
        $nodeIds = [$clubId];
        $frontier = [$clubId];

        for ($depth = 0; $frontier !== [] && $depth < 12; $depth++) {
            $frontier = OrgNode::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $nodeIds = array_merge($nodeIds, $frontier);
        }

        $today = now()->toDateString();

        return OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('person:id,user_id')
            ->get()->pluck('person.user_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /** @return array<int, int> */
    private function memberUserIds(int $clubId): array
    {
        return Membership::query()->where('node_id', $clubId)->where('status', 'approved')->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }
}
