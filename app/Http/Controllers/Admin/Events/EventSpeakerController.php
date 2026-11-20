<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\EventSpeaker;
use App\Models\FoundationEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Intervenants : speakers, formateurs, invités d'honneur (Tunivisions ou externes). */
class EventSpeakerController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'program');

        return $this->render($request, $event, 'speakers', 'admin.events.manage.speakers', [
            'speakers' => $event->speakers()->with('sessions:id,title')->get(),
        ]);
    }

    public function store(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'program');

        $data = $this->validated($request);
        $data['photo_path'] = $this->storePublicImage($request, 'photo', 'events/speakers');
        $speaker = $event->speakers()->create($data);
        $this->manageAudit($request, $event, 'speaker.created:'.$speaker->id);

        return back()->with('status', "Intervenant « {$speaker->name} » ajouté.");
    }

    public function update(Request $request, FoundationEvent $event, EventSpeaker $speaker): RedirectResponse
    {
        $this->allow($request, $event, 'program');
        abort_unless($speaker->event_id === $event->id, 404);

        $data = $this->validated($request);

        if ($request->boolean('photo_remove') && $speaker->photo_path) {
            Storage::disk('public')->delete($speaker->photo_path);
            $speaker->photo_path = null;
        }

        $data['photo_path'] = $this->storePublicImage($request, 'photo', 'events/speakers', $speaker->photo_path);
        $speaker->update($data);
        $this->manageAudit($request, $event, 'speaker.updated:'.$speaker->id);

        return back()->with('status', 'Intervenant mis à jour.');
    }

    public function destroy(Request $request, FoundationEvent $event, EventSpeaker $speaker): RedirectResponse
    {
        $this->allow($request, $event, 'program');
        abort_unless($speaker->event_id === $event->id, 404);

        if ($speaker->photo_path) {
            Storage::disk('public')->delete($speaker->photo_path);
        }

        $speaker->delete();
        $this->manageAudit($request, $event, 'speaker.deleted');

        return back()->with('status', 'Intervenant supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(EventSpeaker::KINDS))],
            'origin' => ['required', Rule::in(array_keys(EventSpeaker::ORIGINS))],
            'user_email' => ['nullable', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        // Un intervenant Tunivisions peut être rattaché à son compte (recherche par e-mail).
        $data['user_id'] = $data['origin'] === 'tunivisions' && ! empty($data['user_email'])
            ? User::query()->where('email', $data['user_email'])->value('id')
            : null;

        unset($data['user_email'], $data['photo']);

        return $data;
    }
}
