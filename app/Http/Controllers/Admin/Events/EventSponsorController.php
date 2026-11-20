<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\EventSponsor;
use App\Models\EventSponsorLevel;
use App\Models\FoundationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sponsors et partenaires, classés par niveaux définis par l'organisateur. */
class EventSponsorController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'sponsors');

        return $this->render($request, $event, 'sponsors', 'admin.events.manage.sponsors', [
            'levels' => $event->sponsorLevels()->with('sponsors')->get(),
        ]);
    }

    public function storeLevel(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'sponsors');

        $level = $event->sponsorLevels()->create($this->validatedLevel($request));
        $this->manageAudit($request, $event, 'sponsor_level.created:'.$level->id);

        return back()->with('status', "Niveau « {$level->name} » créé.");
    }

    public function updateLevel(Request $request, FoundationEvent $event, EventSponsorLevel $level): RedirectResponse
    {
        $this->allow($request, $event, 'sponsors');
        abort_unless($level->event_id === $event->id, 404);

        $level->update($this->validatedLevel($request));
        $this->manageAudit($request, $event, 'sponsor_level.updated:'.$level->id);

        return back()->with('status', 'Niveau mis à jour.');
    }

    public function destroyLevel(Request $request, FoundationEvent $event, EventSponsorLevel $level): RedirectResponse
    {
        $this->allow($request, $event, 'sponsors');
        abort_unless($level->event_id === $event->id, 404);

        if ($level->sponsors()->exists()) {
            return back()->with('error', 'Suppression impossible : ce niveau contient des sponsors ou partenaires.');
        }

        $level->delete();
        $this->manageAudit($request, $event, 'sponsor_level.deleted');

        return back()->with('status', 'Niveau supprimé.');
    }

    public function store(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'sponsors');

        $data = $this->validatedSponsor($request, $event);
        $data['logo_path'] = $this->storePublicImage($request, 'logo', 'events/sponsors');
        $sponsor = $event->sponsors()->create($data);
        $this->manageAudit($request, $event, 'sponsor.created:'.$sponsor->id);

        return back()->with('status', "« {$sponsor->name} » ajouté.");
    }

    public function update(Request $request, FoundationEvent $event, EventSponsor $sponsor): RedirectResponse
    {
        $this->allow($request, $event, 'sponsors');
        abort_unless($sponsor->event_id === $event->id, 404);

        $data = $this->validatedSponsor($request, $event);

        if ($request->boolean('logo_remove') && $sponsor->logo_path) {
            Storage::disk('public')->delete($sponsor->logo_path);
            $sponsor->logo_path = null;
        }

        $data['logo_path'] = $this->storePublicImage($request, 'logo', 'events/sponsors', $sponsor->logo_path);
        $sponsor->update($data);
        $this->manageAudit($request, $event, 'sponsor.updated:'.$sponsor->id);

        return back()->with('status', 'Mis à jour.');
    }

    public function destroy(Request $request, FoundationEvent $event, EventSponsor $sponsor): RedirectResponse
    {
        $this->allow($request, $event, 'sponsors');
        abort_unless($sponsor->event_id === $event->id, 404);

        if ($sponsor->logo_path) {
            Storage::disk('public')->delete($sponsor->logo_path);
        }

        $sponsor->delete();
        $this->manageAudit($request, $event, 'sponsor.deleted');

        return back()->with('status', 'Supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedLevel(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(array_keys(EventSponsorLevel::KINDS))],
            'rank' => ['required', 'integer', 'min:1', 'max:99'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSponsor(Request $request, FoundationEvent $event): array
    {
        $data = $request->validate([
            'level_id' => ['required', 'integer', Rule::exists('event_sponsor_levels', 'id')->where('event_id', $event->id)],
            'name' => ['required', 'string', 'max:160'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        unset($data['logo']);

        return $data;
    }
}
