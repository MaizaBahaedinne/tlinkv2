<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\EventSession;
use App\Models\FoundationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Programme d'un événement : étapes (sessions) avec lieu, inscription éventuelle et intervenants. */
class EventProgramController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'program');

        return $this->render($request, $event, 'program', 'admin.events.manage.program', [
            'sessions' => $event->sessions()->with('speakers:id,name')->withCount(['registrations as seats' => fn ($query) => $query->where('status', 'registered')])->get(),
            'speakers' => $event->speakers()->get(['id', 'name', 'kind']),
        ]);
    }

    public function store(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'program');

        [$data, $speakerIds] = $this->validated($request, $event);
        $session = $event->sessions()->create($data);
        $session->speakers()->sync($speakerIds);
        $this->manageAudit($request, $event, 'session.created:'.$session->id);

        return back()->with('status', "Étape « {$session->title} » ajoutée au programme.");
    }

    public function update(Request $request, FoundationEvent $event, EventSession $session): RedirectResponse
    {
        $this->allow($request, $event, 'program');
        abort_unless($session->event_id === $event->id, 404);

        [$data, $speakerIds] = $this->validated($request, $event);
        $session->update($data);
        $session->speakers()->sync($speakerIds);
        $this->manageAudit($request, $event, 'session.updated:'.$session->id);

        return back()->with('status', 'Étape mise à jour.');
    }

    public function destroy(Request $request, FoundationEvent $event, EventSession $session): RedirectResponse
    {
        $this->allow($request, $event, 'program');
        abort_unless($session->event_id === $event->id, 404);

        $session->delete();
        $this->manageAudit($request, $event, 'session.deleted');

        return back()->with('status', 'Étape supprimée.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, int>}
     */
    private function validated(Request $request, FoundationEvent $event): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(EventSession::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'venue_mode' => ['required', Rule::in(array_keys(EventSession::VENUE_MODES))],
            'venue_name' => ['nullable', 'required_if:venue_mode,external', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:120'],
            'requires_registration' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'speaker_ids' => ['nullable', 'array'],
            'speaker_ids.*' => ['integer', Rule::exists('event_speakers', 'id')->where('event_id', $event->id)],
        ]);

        $speakerIds = array_map('intval', $data['speaker_ids'] ?? []);
        unset($data['speaker_ids']);

        $data['requires_registration'] = (bool) ($data['requires_registration'] ?? false);

        if ($data['venue_mode'] === 'external') {
            $data['room'] = null;
        } else {
            $data['venue_name'] = null;
            $data['venue_address'] = null;
        }

        return [$data, $speakerIds];
    }
}
