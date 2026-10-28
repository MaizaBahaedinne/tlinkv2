<?php

namespace App\Http\Controllers\Admin;

use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
        ]);

        $events = $this->scoped(FoundationEvent::query(), $request, 'event.manage')
            ->withCount('registrations')
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where(fn ($inner) => $inner->where('title', 'like', "%{$needle}%")->orWhere('location', 'like', "%{$needle}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.events.index', [
            'events' => $events,
                        'filters' => $filters,
            'statuses' => self::STATUSES,
            'scopes' => $this->scopeOptions($request, 'event.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.events.form', $this->formData($request, new FoundationEvent(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $event = FoundationEvent::query()->create($this->validated($request) + ['organizer_id' => $request->user()->id]);
        $this->syncCover($request, $event);

        $this->audit($request, 'admin.event.created', $event, ['title' => $event->title, 'scope' => $event->scope]);

        return redirect()->route('admin.events.show', $event)->with('status', "Événement « {$event->title} » créé.");
    }

    public function show(Request $request, FoundationEvent $event): View
    {
        $this->authorizeScope($request, 'event.manage', $event->scope);

        $registrations = $event->registrations()->with('user:id,name,email')->latest('id')->get();

        return view('admin.events.show', [
            'event' => $event,
            'scopeName' => OrgScopes::label($event->scope),
            'organizerName' => $event->organizer_id ? \App\Models\User::query()->whereKey($event->organizer_id)->value('name') : null,
            'registrations' => $registrations,
            'statuses' => self::STATUSES,
            'registrationStatuses' => self::REGISTRATION_STATUSES,
            'registrationCounts' => $registrations->countBy('status'),
        ]);
    }

    public function edit(Request $request, FoundationEvent $event): View
    {
        $this->authorizeScope($request, 'event.manage', $event->scope);

        return view('admin.events.form', $this->formData($request, $event));
    }

    public function update(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->authorizeScope($request, 'event.manage', $event->scope);

        $event->update($this->validated($request));
        $this->syncCover($request, $event);

        $this->audit($request, 'admin.event.updated', $event, ['title' => $event->title, 'status' => $event->status]);

        return redirect()->route('admin.events.show', $event)->with('status', 'Événement mis à jour.');
    }

    public function destroy(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->authorizeScope($request, 'event.manage', $event->scope);

        if ($event->registrations()->exists()) {
            return back()->with('error', 'Suppression impossible : des inscriptions existent. Annulez plutôt l’événement.');
        }

        $title = $event->title;
        $cover = $event->cover_image_path;
        $this->audit($request, 'admin.event.deleted', $event, ['title' => $title]);
        $event->delete();
        $this->deleteCover($cover);

        return redirect()->route('admin.events.index')->with('status', "Événement « {$title} » supprimé.");
    }

    public function updateRegistration(Request $request, FoundationEvent $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorizeScope($request, 'event.manage', $event->scope);
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
            'scopes' => $this->scopeOptions($request, 'event.manage'),
            'canBeGlobal' => $this->context($request)->isGlobal('event.manage'),
            'governorates' => Governorate::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /** Enregistre, remplace ou retire la cover (disque public, dossier events/covers). */
    private function syncCover(Request $request, FoundationEvent $event): void
    {
        $old = $event->cover_image_path;

        if ($request->hasFile('cover')) {
            $event->update(['cover_image_path' => $request->file('cover')->store('events/covers', 'public')]);
            $this->deleteCover($old);
        } elseif ($request->boolean('cover_remove') && $old) {
            $event->update(['cover_image_path' => null]);
            $this->deleteCover($old);
        }
    }

    private function deleteCover(?string $path): void
    {
        // Seules les covers téléversées ici sont supprimées.
        if ($path && str_starts_with($path, 'events/covers/')) {
            Storage::disk('public')->delete($path);
        }
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
            'scope' => [$scope === [] ? 'nullable' : 'required', Rule::in(array_keys($this->scopeOptions($request, 'event.manage')))],
            'location' => ['nullable', 'string', 'max:255'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'approval_required' => ['required', 'boolean'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ]);

        $data['approval_required'] = (bool) $data['approval_required'];
        unset($data['cover']);

        return $data;
    }
}
