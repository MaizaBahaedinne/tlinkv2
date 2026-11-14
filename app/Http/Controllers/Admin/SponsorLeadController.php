<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sponsor;
use App\Models\SponsorContact;
use App\Models\SponsorLead;
use App\Support\ClubReach;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pistes de sponsoring et de partenariat : chaque club suit ses démarches auprès des sponsors du carnet, de la première
 * idée jusqu'à l'accord (ou au refus), avec la prochaine action à mener. Un club ne voit que ses propres pistes.
 */
class SponsorLeadController extends AdminController
{
    private const MODULE = 'sponsoring.leads';

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = array_key_exists((string) $request->query('status'), SponsorLead::STATUSES) ? (string) $request->query('status') : null;
        $club = $request->integer('club') ?: null;
        $open = $request->boolean('open');

        $base = ClubReach::scope(SponsorLead::query(), $user);
        $leads = (clone $base)->with(['sponsor:id,name', 'club:id,title', 'contact:id,name'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($club, fn ($query) => $query->where('club_id', $club))
            ->when($open, fn ($query) => $query->whereNotIn('status', SponsorLead::CLOSED))
            ->orderByRaw('closed_at is not null')->orderByRaw('next_action_at is null')->orderBy('next_action_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.sponsors.leads', [
            'leads' => $leads,
            'counts' => $counts,
            'openAmount' => (float) (clone $base)->whereNotIn('status', SponsorLead::CLOSED)->sum('amount'),
            'wonAmount' => (float) (clone $base)->where('status', 'won')->sum('amount'),
            'filters' => ['status' => $status, 'club' => $club, 'open' => $open],
            'clubOptions' => ClubReach::options($user),
            'canBeFoundation' => ClubReach::isGlobal($user),
            'sponsorOptions' => Sponsor::query()->orderBy('name')->pluck('name', 'id')->all(),
            'preset' => $request->integer('sponsor') ?: null,
            'canCreate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['create']),
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['update']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $data = $this->validated($request);
        ClubReach::validate($request->user(), $data['club_id']);

        $lead = SponsorLead::query()->create($data + [
            'created_by' => $request->user()->id,
            'owner_id' => $request->user()->id,
            'closed_at' => in_array($data['status'], SponsorLead::CLOSED, true) ? now() : null,
        ]);

        $this->audit($request, 'admin.sponsor.lead.created', $lead, ['title' => $lead->title, 'sponsor_id' => $lead->sponsor_id, 'club_id' => $lead->club_id]);

        return back()->with('status', 'Piste « '.$lead->title.' » ajoutée.');
    }

    public function update(Request $request, SponsorLead $lead): RedirectResponse
    {
        ClubReach::authorize($request->user(), $lead->club_id);

        $data = $this->validated($request);
        ClubReach::validate($request->user(), $data['club_id']);

        $closed = in_array($data['status'], SponsorLead::CLOSED, true);
        $before = $lead->status;

        $lead->update($data + ['closed_at' => $closed ? ($lead->closed_at ?? now()) : null]);

        $this->audit($request, 'admin.sponsor.lead.updated', $lead, ['title' => $lead->title, 'from' => $before, 'to' => $lead->status]);

        return back()->with('status', 'Piste mise à jour.');
    }

    public function destroy(Request $request, SponsorLead $lead): RedirectResponse
    {
        ClubReach::authorize($request->user(), $lead->club_id);

        $this->audit($request, 'admin.sponsor.lead.deleted', $lead, ['title' => $lead->title, 'sponsor_id' => $lead->sponsor_id]);
        $lead->delete();

        return back()->with('status', 'Piste supprimée.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'sponsor_id' => ['required', 'integer', 'exists:sponsors,id'],
            'club_id' => ['nullable', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')],
            'contact_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:190'],
            'kind' => ['required', Rule::in(array_keys(SponsorLead::KINDS))],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'status' => ['required', Rule::in(array_keys(SponsorLead::STATUSES))],
            'next_action' => ['nullable', 'string', 'max:1000'],
            'next_action_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'sponsor_id.required' => 'Choisissez le sponsor ou partenaire concerné.',
            'title.required' => 'Donnez un intitulé à la piste (ex. : « Soirée de gala 2027 »).',
        ]);

        $data['club_id'] = ($data['club_id'] ?? null) ?: null;
        $data['contact_id'] = ($data['contact_id'] ?? null) ?: null;
        $data['title'] = trim($data['title']);

        if ($data['contact_id'] !== null && ! SponsorContact::query()->where('id', $data['contact_id'])->where('sponsor_id', $data['sponsor_id'])->exists()) {
            throw ValidationException::withMessages(['contact_id' => 'Cet interlocuteur n’appartient pas à ce sponsor.']);
        }

        return $data;
    }
}
