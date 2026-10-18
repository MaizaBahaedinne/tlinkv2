<?php

namespace App\Http\Controllers\Admin;

use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends AdminController
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'published' => 'Publié',
        'cancelled' => 'Annulé',
        'completed' => 'Terminé',
    ];

    public const REGISTRATION_STATUSES = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'waitlisted' => 'Liste d’attente',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée',
        'attended' => 'Présent',
        'absent' => 'Absent',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'entity' => ['nullable', 'integer'],
        ]);

        $events = $this->scoped(FoundationEvent::query(), $request, 'event.manage')
            ->withCount('registrations')
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where(fn ($inner) => $inner->where('title', 'like', "%{$needle}%")->orWhere('location', 'like', "%{$needle}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['entity'] ?? null, fn ($query, $entity) => $query->where('entity_id', $entity))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $entityNames = \App\Models\Entity::query()->whereIn('id', $events->getCollection()->pluck('entity_id')->filter())->pluck('name', 'id');

        return view('admin.events.index', [
            'events' => $events,
            'entityNames' => $entityNames,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'entities' => $this->entityOptions($request, 'event.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.events.form', $this->formData($request, new FoundationEvent(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $event = FoundationEvent::query()->create($this->validated($request) + ['organizer_id' => $request->user()->id]);

        $this->audit($request, 'admin.event.created', $event, ['title' => $event->title, 'entity_id' => $event->entity_id]);

        return redirect()->route('admin.events.show', $event)->with('status', "Événement « {$event->title} » créé.");
    }

    public function show(Request $request, FoundationEvent $event): View
    {
        $this->authorizeEntity($request, 'event.manage', $event->entity_id);

        $registrations = $event->registrations()->with('user:id,name,email')->latest('id')->get();

        return view('admin.events.show', [
            'event' => $event,
            'entityName' => $event->entity_id ? \App\Models\Entity::query()->whereKey($event->entity_id)->value('name') : null,
            'organizerName' => $event->organizer_id ? \App\Models\User::query()->whereKey($event->organizer_id)->value('name') : null,
            'registrations' => $registrations,
            'statuses' => self::STATUSES,
            'registrationStatuses' => self::REGISTRATION_STATUSES,
            'registrationCounts' => $registrations->countBy('status'),
        ]);
    }

    public function edit(Request $request, FoundationEvent $event): View
    {
        $this->authorizeEntity($request, 'event.manage', $event->entity_id);

        return view('admin.events.form', $this->formData($request, $event));
    }

    public function update(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->authorizeEntity($request, 'event.manage', $event->entity_id);

        $event->update($this->validated($request));

        $this->audit($request, 'admin.event.updated', $event, ['title' => $event->title, 'status' => $event->status]);

        return redirect()->route('admin.events.show', $event)->with('status', 'Événement mis à jour.');
    }

    public function destroy(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->authorizeEntity($request, 'event.manage', $event->entity_id);

        if ($event->registrations()->exists()) {
            return back()->with('error', 'Suppression impossible : des inscriptions existent. Annulez plutôt l’événement.');
        }

        $title = $event->title;
        $this->audit($request, 'admin.event.deleted', $event, ['title' => $title]);
        $event->delete();

        return redirect()->route('admin.events.index')->with('status', "Événement « {$title} » supprimé.");
    }

    public function updateRegistration(Request $request, FoundationEvent $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorizeEntity($request, 'event.manage', $event->entity_id);
        abort_unless($registration->event_id === $event->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::REGISTRATION_STATUSES))],
        ]);

        $from = $registration->status;
        $registration->update(['status' => $data['status']]);

        $this->audit($request, 'admin.event.registration_updated', $registration, [
            'event_id' => $event->id,
            'user_id' => $registration->user_id,
            'from' => $from,
            'to' => $data['status'],
        ]);

        return back()->with('status', 'Inscription mise à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, FoundationEvent $event): array
    {
        return [
            'event' => $event,
            'statuses' => self::STATUSES,
            'entities' => $this->entityOptions($request, 'event.manage'),
            'canBeGlobal' => $this->context($request)->isGlobal('event.manage'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $scope = $this->context($request)->scope('event.manage');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'entity_id' => [$scope === [] ? 'nullable' : 'required', 'integer', Rule::in(array_keys($this->entityOptions($request, 'event.manage')))],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'approval_required' => ['required', 'boolean'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ]);

        $data['approval_required'] = (bool) $data['approval_required'];

        return $data;
    }
}
