<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sponsor;
use App\Models\SponsorContact;
use App\Models\SponsorLead;
use App\Support\ClubReach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Carnet d'adresses des sponsors et partenaires : partagé par toute la Fondation pour que les clubs s'appuient sur les
 * contacts existants (et évitent de démarcher deux fois la même organisation), avec leurs interlocuteurs.
 */
class SponsorController extends AdminController
{
    public function index(Request $request): View
    {
        $relation = array_key_exists((string) $request->query('relation'), Sponsor::RELATIONS) ? (string) $request->query('relation') : null;
        $status = array_key_exists((string) $request->query('status'), Sponsor::STATUSES) ? (string) $request->query('status') : null;
        $needle = trim(mb_substr((string) $request->query('q', ''), 0, 80));

        $sponsors = Sponsor::query()
            ->with('ownerClub:id,title')
            ->withCount(['contacts', 'leads as open_leads_count' => fn ($query) => $query->whereNotIn('status', SponsorLead::CLOSED)])
            ->when($relation, fn ($query) => $query->where('relation', $relation))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($needle !== '', function ($query) use ($needle): void {
                $like = '%'.addcslashes($needle, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('sector', 'like', $like)->orWhere('city', 'like', $like)
                    ->orWhereHas('contacts', fn ($contact) => $contact->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.sponsors.index', [
            'sponsors' => $sponsors,
            'filters' => ['relation' => $relation, 'status' => $status, 'q' => $needle],
            'clubOptions' => ClubReach::options($request->user()),
            'canCreate' => $this->context($request)->allowsModule(['event.manage'], 'sponsoring.contacts', ['create']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireModuleAction($request, 'sponsoring.contacts', 'create');

        $data = $this->validated($request);
        $this->assertClub($request, $data);

        $sponsor = Sponsor::query()->create($data + ['created_by' => $request->user()->id]);
        $this->audit($request, 'admin.sponsor.created', $sponsor, ['name' => $sponsor->name, 'relation' => $sponsor->relation]);

        return redirect()->route('admin.sponsors.show', $sponsor)->with('status', '« '.$sponsor->name.' » ajouté au carnet.');
    }

    public function show(Request $request, Sponsor $sponsor): View
    {
        $user = $request->user();
        $sponsor->load(['contacts', 'ownerClub:id,title', 'creator:id,name']);

        $reach = ClubReach::ids($user);
        $leads = ClubReach::scope(SponsorLead::query(), $user)->where('sponsor_id', $sponsor->id)->with(['club:id,title', 'contact:id,name'])->latest('id')->get();
        $others = SponsorLead::query()->where('sponsor_id', $sponsor->id)->whereNotIn('status', SponsorLead::CLOSED)
            ->when($reach !== null, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('club_id')->orWhereNotIn('club_id', $reach)))
            ->with('club:id,title')->get();

        return view('admin.sponsors.show', [
            'sponsor' => $sponsor,
            'leads' => $leads,
            'otherClubs' => $others->map(fn (SponsorLead $lead): string => $lead->club?->title ?? 'Fondation')->unique()->values(),
            'clubOptions' => ClubReach::options($user),
            'canBeFoundation' => ClubReach::isGlobal($user),
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], 'sponsoring.contacts', ['update']),
            'canDelete' => $this->canDelete($request, $sponsor),
            'canLead' => $this->context($request)->allowsModule(['event.manage'], 'sponsoring.leads', ['create']),
        ]);
    }

    public function update(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertClub($request, $data);

        $sponsor->update($data);
        $this->audit($request, 'admin.sponsor.updated', $sponsor, ['name' => $sponsor->name, 'status' => $sponsor->status]);

        return back()->with('status', 'Fiche enregistrée.');
    }

    public function destroy(Request $request, Sponsor $sponsor): RedirectResponse
    {
        abort_unless($this->canDelete($request, $sponsor), 403, 'Seul l’administrateur global ou la personne qui a ajouté cette fiche peut la supprimer.');

        $this->audit($request, 'admin.sponsor.deleted', $sponsor, ['name' => $sponsor->name]);
        $name = $sponsor->name;
        $sponsor->delete();

        return redirect()->route('admin.sponsors.index')->with('status', '« '.$name.' » retiré du carnet, avec ses contacts et ses pistes.');
    }

    public function storeContact(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $contact = $sponsor->contacts()->create($this->validatedContact($request));
        $this->normalisePrimary($contact);
        $this->audit($request, 'admin.sponsor.contact.created', $sponsor, ['name' => $contact->name]);

        return back()->with('status', 'Interlocuteur ajouté.');
    }

    public function updateContact(Request $request, Sponsor $sponsor, SponsorContact $contact): RedirectResponse
    {
        abort_unless($contact->sponsor_id === $sponsor->id, 404);

        $contact->update($this->validatedContact($request));
        $this->normalisePrimary($contact);
        $this->audit($request, 'admin.sponsor.contact.updated', $sponsor, ['name' => $contact->name]);

        return back()->with('status', 'Interlocuteur mis à jour.');
    }

    public function destroyContact(Request $request, Sponsor $sponsor, SponsorContact $contact): RedirectResponse
    {
        abort_unless($contact->sponsor_id === $sponsor->id, 404);

        $this->audit($request, 'admin.sponsor.contact.deleted', $sponsor, ['name' => $contact->name]);
        $contact->delete();

        return back()->with('status', 'Interlocuteur retiré.');
    }

    private function canDelete(Request $request, Sponsor $sponsor): bool
    {
        return $this->context($request)->allowsModule(['event.manage'], 'sponsoring.contacts', ['delete'])
            && (ClubReach::isGlobal($request->user()) || $sponsor->created_by === $request->user()->id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'relation' => ['required', Rule::in(array_keys(Sponsor::RELATIONS))],
            'status' => ['required', Rule::in(array_keys(Sponsor::STATUSES))],
            'sector' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'owner_club_id' => ['nullable', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')],
        ]);

        $data['owner_club_id'] = ($data['owner_club_id'] ?? null) ?: null;
        $data['name'] = trim($data['name']);

        return $data;
    }

    /** Le club porteur doit être dans la portée de l'utilisateur ; sans club, seule la Fondation (admin global) peut porter la fiche. */
    private function assertClub(Request $request, array $data): void
    {
        if ($data['owner_club_id'] !== null) {
            ClubReach::validate($request->user(), $data['owner_club_id'], 'owner_club_id');
        }
    }

    /** @return array<string, mixed> */
    private function validatedContact(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $data['is_primary'] = $request->boolean('is_primary');

        return $data;
    }

    /** Un seul interlocuteur principal par sponsor. */
    private function normalisePrimary(SponsorContact $contact): void
    {
        if ($contact->is_primary) {
            SponsorContact::query()->where('sponsor_id', $contact->sponsor_id)->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }
    }
}
