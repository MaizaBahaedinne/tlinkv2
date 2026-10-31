<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityOrganizer;
use App\Models\ActivityRegistration;
use App\Models\AppNotification;
use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Models\OrgNode;
use App\Support\Challenges\ActivityAccess;
use App\Support\Challenges\Challenges;
use App\Support\Challenges\ChallengeScoring;
use App\Support\OrgAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Activités des clubs dans un défi : création, co-organisation, inscriptions et présence (QR code). */
class ActivityController extends AdminController
{
    public function index(Request $request): View
    {
        $ids = ActivityAccess::manageableClubIds($request->user());

        $activities = ClubActivity::query()
            ->with(['club:id,title', 'challenge:id,title'])
            ->withCount(['registrations' => fn ($query) => $query->where('status', 'registered')])
            ->when($ids !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereIn('club_id', $ids)
                ->orWhereHas('organizers', fn (Builder $organizer) => $organizer->whereIn('club_id', $ids)->where('status', 'accepted'))))
            ->orderByDesc('starts_at')
            ->paginate(15);

        $invitations = ActivityOrganizer::query()->where('status', 'pending')
            ->when($ids !== null, fn (Builder $query) => $query->whereIn('club_id', $ids))
            ->with(['club:id,title', 'activity.club:id,title', 'activity.challenge:id,title'])
            ->latest('id')->get();

        return view('admin.activities.index', ['activities' => $activities, 'invitations' => $invitations, 'manageable' => $ids]);
    }

    public function create(Request $request): View
    {
        return view('admin.activities.form', $this->formData($request, new ClubActivity([
            'status' => 'draft', 'in_theme' => true, 'audience' => 'public', 'type' => 'event',
            'challenge_id' => $request->integer('defi') ?: null,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $activity = ClubActivity::query()->create($data + ['created_by' => $request->user()->id]);
        $this->syncCover($request, $activity);

        $this->audit($request, 'admin.activity.created', $activity, ['title' => $activity->title, 'challenge_id' => $activity->challenge_id, 'club_id' => $activity->club_id]);

        return redirect()->route('admin.activities.show', $activity)->with('status', "Activité « {$activity->title} » créée.");
    }

    public function show(Request $request, ClubActivity $activity): View
    {
        $this->authorizeActivity($request, $activity);

        $activity->load(['club:id,title,scope', 'challenge', 'department:id,title', 'organizers.club:id,title']);
        $taken = array_merge($activity->organizers->pluck('club_id')->all(), [$activity->club_id]);

        return view('admin.activities.show', [
            'activity' => $activity,
            'registrations' => $activity->registrations()->with('user:id,name,email')->orderBy('id')->get(),
            'total' => ChallengeScoring::activityTotals($activity->challenge)[$activity->id] ?? null,
            'candidates' => OrgNode::query()->where('kind', 'club')->whereNotIn('id', $taken)->orderBy('title')->get(['id', 'title', 'scope'])
                ->filter(fn (OrgNode $club): bool => $activity->challenge->invitesClub($club))->values(),
            'canLead' => ActivityAccess::canManageClub($request->user(), $activity->club_id),
            'manageable' => ActivityAccess::manageableClubIds($request->user()),
        ]);
    }

    public function edit(Request $request, ClubActivity $activity): View
    {
        $this->authorizeActivity($request, $activity);
        abort_unless(ActivityAccess::canManageClub($request->user(), $activity->club_id), 403, 'Seul le club porteur modifie l’activité.');

        return view('admin.activities.form', $this->formData($request, $activity));
    }

    public function update(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->authorizeActivity($request, $activity);
        abort_unless(ActivityAccess::canManageClub($request->user(), $activity->club_id), 403, 'Seul le club porteur modifie l’activité.');

        $activity->update($this->validated($request, $activity));
        $this->syncCover($request, $activity);
        $this->audit($request, 'admin.activity.updated', $activity, ['title' => $activity->title, 'status' => $activity->status]);

        return redirect()->route('admin.activities.show', $activity)->with('status', 'Activité mise à jour.');
    }

    public function destroy(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->authorizeActivity($request, $activity);
        abort_unless(ActivityAccess::canManageClub($request->user(), $activity->club_id), 403, 'Seul le club porteur supprime l’activité.');

        if ($activity->scores()->exists() || $activity->report?->isValidated()) {
            return back()->with('error', 'Suppression impossible : l’activité a déjà été notée ou son compte rendu est validé. Passez-la plutôt en « Annulée ».');
        }

        $title = $activity->title;
        $registrants = $activity->registrations()->where('status', '!=', 'cancelled')->pluck('user_id')->all();
        $reportId = $activity->report?->id;
        $cover = $activity->cover_path;

        $this->audit($request, 'admin.activity.deleted', $activity, ['title' => $title, 'registrations' => count($registrants)]);
        $activity->delete();
        $this->deleteCover($cover);

        if ($reportId !== null) {
            Storage::disk('public')->deleteDirectory('activity-reports/'.$reportId);
        }

        foreach (array_unique($registrants) as $userId) {
            AppNotification::query()->create([
                'user_id' => $userId,
                'type' => 'activity.deleted',
                'title' => 'Activité supprimée',
                'body' => "L’activité « {$title} » a été supprimée par les organisateurs : votre inscription est annulée.",
                'data' => ['url' => route('challenges.index', [], false)],
            ]);
        }

        return redirect()->route('admin.activities.index')->with('status', "Activité « {$title} » supprimée.");
    }

    /** Page imprimable du QR code de présence : les participants le scannent avec leur smartphone. */
    public function qr(Request $request, ClubActivity $activity): View
    {
        $this->authorizeActivity($request, $activity);

        return view('admin.activities.qr', ['activity' => $activity]);
    }

    public function addOrganizer(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->authorizeActivity($request, $activity);
        abort_unless(ActivityAccess::canManageClub($request->user(), $activity->club_id), 403, 'Seul le club porteur invite des co-organisateurs.');

        $data = $request->validate(['club_id' => ['required', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')]]);
        $club = OrgNode::query()->findOrFail($data['club_id']);

        if ($club->id === $activity->club_id || $activity->organizers()->where('club_id', $club->id)->exists()) {
            return back()->with('error', 'Ce club est déjà organisateur ou invité.');
        }

        abort_unless($activity->challenge->invitesClub($club), 422, 'Ce club n’est pas invité à ce défi.');

        $organizer = $activity->organizers()->create(['club_id' => $club->id, 'status' => 'pending']);

        foreach (OrgAccess::userIdsInClub(ActivityAccess::PERMISSION, $club->id, $request->user()->id) as $userId) {
            AppNotification::query()->create([
                'user_id' => $userId,
                'type' => 'activity.coorganizer',
                'title' => 'Invitation à co-organiser une activité',
                'body' => "« {$activity->club->title} » invite « {$club->title} » à co-organiser « {$activity->title} ».",
                'data' => ['activity_id' => $activity->id, 'url' => route('admin.activities.index', [], false)],
            ]);
        }

        $this->audit($request, 'admin.activity.updated', $activity, ['coorganizer_invited' => $club->title, 'organizer_id' => $organizer->id]);

        return back()->with('status', "« {$club->title} » est invité à co-organiser l’activité.");
    }

    /** Le club invité accepte ou refuse la co-organisation. */
    public function respondOrganizer(Request $request, ActivityOrganizer $organizer): RedirectResponse
    {
        abort_unless(ActivityAccess::canManageClub($request->user(), $organizer->club_id), 403, 'Cette invitation concerne un autre club.');

        $data = $request->validate(['status' => ['required', Rule::in(['accepted', 'declined'])]]);

        $organizer->update(['status' => $data['status'], 'responded_at' => now()]);
        $this->audit($request, 'admin.activity.updated', $organizer->activity, ['coorganizer_'.$data['status'] => $organizer->club_id]);

        return back()->with('status', $data['status'] === 'accepted' ? 'Co-organisation acceptée.' : 'Invitation refusée.');
    }

    /** Retire un co-organisateur : par le club porteur ou par le club concerné lui-même. */
    public function removeOrganizer(Request $request, ActivityOrganizer $organizer): RedirectResponse
    {
        $activity = $organizer->activity;
        abort_unless(
            ActivityAccess::canManageClub($request->user(), $activity->club_id) || ActivityAccess::canManageClub($request->user(), $organizer->club_id),
            403,
        );

        $organizer->delete();
        $this->audit($request, 'admin.activity.updated', $activity, ['coorganizer_removed' => $organizer->club_id]);

        return back()->with('status', 'Co-organisateur retiré.');
    }

    /** Présence saisie à la main (en plus du QR code) : présent ou absent. */
    public function updateRegistration(Request $request, ClubActivity $activity, ActivityRegistration $registration): RedirectResponse
    {
        $this->authorizeActivity($request, $activity);
        abort_unless($registration->activity_id === $activity->id, 404);

        $data = $request->validate(['presence' => ['required', Rule::in(['present', 'absent'])]]);

        $registration->update(['checked_in_at' => $data['presence'] === 'present' ? ($registration->checked_in_at ?? now()) : null]);

        return back()->with('status', 'Présence mise à jour.');
    }

    private function authorizeActivity(Request $request, ClubActivity $activity): void
    {
        abort_unless(ActivityAccess::canManageActivity($request->user(), $activity), 403, 'Cette activité n’est pas celle de votre club.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ClubActivity $activity): array
    {
        $clubs = ActivityAccess::manageableClubs($request->user())->get(['id', 'title', 'scope']);
        $challenges = Challenge::query()->where('status', 'published')->where('ends_at', '>=', now())->with('criteria')->orderBy('starts_at')->get()
            ->filter(fn (Challenge $challenge): bool => $clubs->contains(fn (OrgNode $club): bool => $challenge->invitesClub($club)))->values();

        if ($activity->exists && ! $challenges->contains('id', $activity->challenge_id)) {
            $challenges->push($activity->challenge->loadMissing('criteria'));
        }

        return [
            'activity' => $activity,
            'clubs' => $clubs,
            'challenges' => $challenges,
            'departments' => Challenges::departmentsByClub(),
            'fieldRows' => collect(old('fields', $activity->registration_fields ?? []))->map(fn ($row) => [
                'label' => $row['label'] ?? '',
                'type' => $row['type'] ?? 'text',
                'required' => ! empty($row['required']),
                'options' => is_array($row['options'] ?? null) ? implode(', ', $row['options']) : (string) ($row['options'] ?? ''),
            ])->values()->all(),
        ];
    }

    /** Enregistre, remplace ou retire l'image de couverture (disque public, dossier activities/covers). */
    private function syncCover(Request $request, ClubActivity $activity): void
    {
        $old = $activity->cover_path;

        if ($request->hasFile('cover')) {
            $activity->update(['cover_path' => $request->file('cover')->store('activities/covers', 'public')]);
            $this->deleteCover($old);
        } elseif ($request->boolean('cover_remove') && $old) {
            $activity->update(['cover_path' => null]);
            $this->deleteCover($old);
        }
    }

    private function deleteCover(?string $path): void
    {
        if ($path && str_starts_with($path, 'activities/covers/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ClubActivity $existing): array
    {
        $user = $request->user();

        $data = $request->validate([
            'challenge_id' => [$existing ? 'nullable' : 'required', 'integer', Rule::exists('challenges', 'id')],
            'club_id' => [$existing ? 'nullable' : 'required', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(array_keys(ClubActivity::TYPES))],
            'audience' => ['required', Rule::in(array_keys(ClubActivity::AUDIENCES))],
            'department_id' => ['nullable', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club_department')],
            'in_theme' => ['required', 'boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_ends_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(ClubActivity::STATUSES))],
            'fields' => ['nullable', 'array', 'max:'.ClubActivity::MAX_FIELDS],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_remove' => ['nullable', 'boolean'],
        ]);

        // Le défi et le club porteur sont choisis à la création ; ensuite ils sont figés.
        $challenge = $existing ? $existing->challenge : Challenge::query()->with('criteria')->findOrFail($data['challenge_id']);
        $clubId = $existing ? $existing->club_id : (int) $data['club_id'];

        if (! $existing) {
            $club = OrgNode::query()->findOrFail($clubId);

            if (! ActivityAccess::canManageClub($user, $clubId)) {
                throw ValidationException::withMessages(['club_id' => 'Vous ne gérez pas ce club.']);
            }

            if (! $challenge->invitesClub($club)) {
                throw ValidationException::withMessages(['club_id' => 'Ce club n’est pas invité à ce défi.']);
            }
        }

        if (! $challenge->acceptsActivities()) {
            throw ValidationException::withMessages(['challenge_id' => 'Ce défi n’accepte plus d’activités (non publié ou terminé).']);
        }

        $inTheme = (bool) $data['in_theme'];

        if ($challenge->criteriaOf($inTheme ? 'theme' : 'off_theme')->isEmpty()) {
            throw ValidationException::withMessages(['in_theme' => $inTheme ? 'Ce défi n’a pas de barème pour la thématique.' : 'Ce défi n’a pas de barème pour les activités hors thématique : elles n’y sont pas acceptées.']);
        }

        $starts = \Illuminate\Support\Carbon::parse($data['starts_at']);

        if ($starts->lt($challenge->starts_at->copy()->startOfDay()) || $starts->gt($challenge->ends_at)) {
            throw ValidationException::withMessages(['starts_at' => 'L’activité doit commencer pendant le défi (du '.$challenge->starts_at->format('d/m/Y').' au '.$challenge->ends_at->format('d/m/Y').').']);
        }

        $department = null;

        if ($data['audience'] === 'department') {
            $department = isset($data['department_id']) ? (int) $data['department_id'] : null;

            if ($department === null || ! array_key_exists($department, Challenges::departmentsByClub()[$clubId] ?? [])) {
                throw ValidationException::withMessages(['department_id' => 'Choisissez un département de ce club.']);
            }
        }

        $attributes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'audience' => $data['audience'],
            'department_id' => $department,
            'in_theme' => $inTheme,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'registration_ends_at' => $data['registration_ends_at'] ?? null,
            'status' => $data['status'],
            'registration_fields' => $this->fields((array) ($data['fields'] ?? [])),
        ];

        return $existing ? $attributes : $attributes + ['challenge_id' => $challenge->id, 'club_id' => $clubId];
    }

    /**
     * Questions du formulaire d'inscription (lignes sans intitulé ignorées).
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>|null
     */
    private function fields(array $rows): ?array
    {
        $fields = [];

        foreach ($rows as $row) {
            if (! is_array($row) || trim((string) ($row['label'] ?? '')) === '') {
                continue;
            }

            $label = trim((string) $row['label']);

            $type = array_key_exists($row['type'] ?? '', ClubActivity::FIELD_TYPES) ? $row['type'] : 'text';
            $options = collect(explode(',', (string) ($row['options'] ?? '')))->map(fn ($option) => mb_substr(trim($option), 0, 80))->filter()->unique()->take(20)->values()->all();

            if ($type === 'choice' && count($options) < 2) {
                throw ValidationException::withMessages(['fields' => "La question « {$label} » doit proposer au moins deux choix séparés par des virgules."]);
            }

            $fields[] = ['label' => mb_substr($label, 0, 160), 'type' => $type, 'required' => ! empty($row['required']), 'options' => $type === 'choice' ? $options : []];
        }

        return $fields === [] ? null : $fields;
    }
}
