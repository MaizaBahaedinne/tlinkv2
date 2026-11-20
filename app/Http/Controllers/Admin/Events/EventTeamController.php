<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\AppNotification;
use App\Models\EventTeamMember;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Support\Events\EventAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Équipe d'organisation (OC) d'un événement et rôles de gestion. */
class EventTeamController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'team');

        return $this->render($request, $event, 'team', 'admin.events.manage.team', [
            'members' => $event->team()->with('user:id,name,email')->orderBy('role')->get(),
            'roles' => EventAccess::ROLES,
            'roleAbilities' => EventAccess::ROLE_ABILITIES,
            'abilityLabels' => EventAccess::ABILITIES,
        ]);
    }

    public function store(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'team');

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:user_id'],
            'role' => ['required', Rule::in(array_keys(EventAccess::ROLES))],
            'job_title' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.required_without' => 'Recherchez et choisissez un membre dans la liste.',
            'email.required_without' => 'Recherchez et choisissez un membre dans la liste.',
        ]);

        $user = ! empty($data['user_id'])
            ? User::query()->find($data['user_id'])
            : User::query()->where('email', $data['email'])->first();

        if ($user === null) {
            return back()->withInput()->withErrors(['user_id' => 'Membre introuvable : choisissez-le dans la liste de recherche.']);
        }

        $member = EventTeamMember::query()->firstOrNew(['event_id' => $event->id, 'user_id' => $user->id]);
        $isNew = ! $member->exists;
        $member->fill(['role' => $data['role'], 'job_title' => $data['job_title'] ?? null, 'notes' => $data['notes'] ?? null])->save();

        if ($isNew) {
            AppNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'event.team',
                'title' => 'Équipe d’organisation',
                'body' => 'Vous avez rejoint l’équipe d’organisation de « '.$event->title.' » ('.(($data['job_title'] ?? '') !== '' ? $data['job_title'] : EventAccess::ROLES[$data['role']]).').',
                'data' => ['event_id' => $event->id, 'role' => $data['role']],
            ]);
        }

        $this->manageAudit($request, $event, 'team.saved:'.$user->id);

        return back()->with('status', $user->name.' fait partie de l’équipe ('.EventAccess::ROLES[$data['role']].').');
    }

    public function update(Request $request, FoundationEvent $event, EventTeamMember $member): RedirectResponse
    {
        $this->allow($request, $event, 'team');
        abort_unless($member->event_id === $event->id, 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(EventAccess::ROLES))],
            'job_title' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $member->update($data);
        $this->manageAudit($request, $event, 'team.updated:'.$member->user_id);

        return back()->with('status', 'Membre mis à jour.');
    }

    /** Recherche d'un membre dans la base (nom, prénom ou e-mail) pour l'ajouter à l'équipe. */
    public function search(Request $request, FoundationEvent $event): JsonResponse
    {
        $this->allow($request, $event, 'team');

        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $term = trim((string) ($validated['q'] ?? ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['members' => []]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $inTeam = $event->team()->pluck('user_id')->all();

        $members = User::query()
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->whereNotIn('id', $inTeam)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

        return response()->json(['members' => $members]);
    }

    public function destroy(Request $request, FoundationEvent $event, EventTeamMember $member): RedirectResponse
    {
        $this->allow($request, $event, 'team');
        abort_unless($member->event_id === $event->id, 404);

        $member->delete();
        $this->manageAudit($request, $event, 'team.removed');

        return back()->with('status', 'Membre retiré de l’équipe.');
    }
}
