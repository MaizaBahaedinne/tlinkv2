<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\EventRelease;
use App\Models\FoundationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Billetterie : releases (tranches tarifaires) d'un événement. */
class EventTicketController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'tickets');

        return $this->render($request, $event, 'tickets', 'admin.events.manage.tickets', [
            'releases' => $event->releases()->withCount(['registrations as seats' => fn ($query) => $query->whereIn('status', \App\Models\EventRegistration::SEAT_HOLDING)])->get(),
        ]);
    }

    public function store(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'tickets');

        $release = $event->releases()->create($this->validated($request) + ['sort_order' => ((int) $event->releases()->max('sort_order')) + 1]);
        $this->manageAudit($request, $event, 'release.created:'.$release->id);

        return back()->with('status', "Release « {$release->name} » ajoutée.");
    }

    public function update(Request $request, FoundationEvent $event, EventRelease $release): RedirectResponse
    {
        $this->allow($request, $event, 'tickets');
        abort_unless($release->event_id === $event->id, 404);

        $release->update($this->validated($request));
        $this->manageAudit($request, $event, 'release.updated:'.$release->id);

        return back()->with('status', 'Release mise à jour.');
    }

    public function destroy(Request $request, FoundationEvent $event, EventRelease $release): RedirectResponse
    {
        $this->allow($request, $event, 'tickets');
        abort_unless($release->event_id === $event->id, 404);

        if ($release->registrations()->exists()) {
            return back()->with('error', 'Suppression impossible : des inscriptions utilisent cette release. Désactivez-la plutôt.');
        }

        $release->delete();
        $this->manageAudit($request, $event, 'release.deleted');

        return back()->with('status', 'Release supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
