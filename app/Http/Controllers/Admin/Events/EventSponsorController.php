<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\EventSponsor;
use App\Models\EventSponsorLevel;
use App\Models\FoundationEvent;
use App\Models\Partner;
use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'directorySponsors' => Sponsor::query()->orderBy('name')->get(['id', 'name', 'relation', 'city']),
            'directoryPartners' => Partner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
        $directory = $this->directoryEntry($request);

        if ($directory !== null) {
            // Fiche reprise du carnet de partenaires : les champs saisis à la main restent prioritaires.
            $data['name'] = $data['name'] ?? $directory['name'];
            $data['website_url'] = $data['website_url'] ?? $directory['website_url'];
            $data['directory_sponsor_id'] = $directory['sponsor_id'];
            $data['directory_partner_id'] = $directory['partner_id'];
        }

        if (empty($data['name'])) {
            return back()->withInput()->withErrors(['name' => 'Choisissez une entrée de la base ou saisissez un nom.']);
        }

        $data['logo_path'] = $this->storePublicImage($request, 'logo', 'events/sponsors')
            ?? ($directory['logo_copy'] ?? null);
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
     * Entrée choisie dans le carnet de partenaires (« s:12 » = sponsor du carnet, « p:3 » = partenaire du site).
     *
     * @return array{name: string, website_url: ?string, sponsor_id: ?int, partner_id: ?int, logo_copy: ?string}|null
     */
    private function directoryEntry(Request $request): ?array
    {
        $choice = (string) $request->input('directory', '');

        if (preg_match('/^([sp]):(\d+)$/', $choice, $match) !== 1) {
            return null;
        }

        if ($match[1] === 's') {
            $sponsor = Sponsor::query()->find((int) $match[2]);

            return $sponsor === null ? null : [
                'name' => mb_substr($sponsor->name, 0, 160),
                'website_url' => $this->httpUrl($sponsor->website),
                'sponsor_id' => $sponsor->id,
                'partner_id' => null,
                'logo_copy' => null,
            ];
        }

        $partner = Partner::query()->find((int) $match[2]);

        if ($partner === null) {
            return null;
        }

        $copy = null;

        if ($partner->logo_path && Storage::disk('public')->exists($partner->logo_path)) {
            $copy = 'events/sponsors/'.Str::random(24).'.'.(pathinfo($partner->logo_path, PATHINFO_EXTENSION) ?: 'png');
            Storage::disk('public')->copy($partner->logo_path, $copy);
        }

        return [
            'name' => mb_substr($partner->name, 0, 160),
            'website_url' => $partner->safeUrl(),
            'sponsor_id' => null,
            'partner_id' => $partner->id,
            'logo_copy' => $copy,
        ];
    }

    private function httpUrl(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) === 1 ? mb_substr($url, 0, 500) : null;
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
            'name' => [$request->routeIs('admin.events.sponsors.store') && $request->filled('directory') ? 'nullable' : 'required', 'string', 'max:160'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        unset($data['logo']);

        return $data;
    }
}
