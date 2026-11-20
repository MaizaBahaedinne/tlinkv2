<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\AppNotification;
use App\Models\EventTeamMember;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Support\Events\EventAccess;
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(EventAccess::ROLES))],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null) {
            return back()->withInput()->withErrors(['email' => 'Aucun membre avec cet e-mail.']);
        }

        $member = EventTeamMember::query()->firstOrNew(['event_id' => $event->id, 'user_id' => $user->id]);
        $isNew = ! $member->exists;
        $member->fill(['role' => $data['role'], 'notes' => $data['notes'] ?? null])->save();

        if ($isNew) {
            AppNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'event.team',
                'title' => 'Équipe d’organisation',
                'body' => 'Vous avez rejoint l’équipe d’organisation de « '.$event->title.' » ('.EventAccess::ROLES[$data['role']].').',
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
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $member->update($data);
        $this->manageAudit($request, $event, 'team.updated:'.$member->user_id);

        return back()->with('status', 'Rôle mis à jour.');
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
