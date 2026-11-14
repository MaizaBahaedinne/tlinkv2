<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Mes réunions : les invitations reçues par le membre, sa réponse, et les décisions prises une fois la réunion tenue. */
class MyMeetingsController extends Controller
{
    public function index(Request $request): View
    {
        $rows = MeetingAttendee::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('invited_at')
            ->whereHas('meeting')
            ->with(['meeting.club:id,title', 'meeting.items'])
            ->get();

        [$upcoming, $past] = $rows->partition(fn (MeetingAttendee $row): bool => $row->meeting->isOpen() && $row->meeting->endsAt()->isFuture());

        return view('meetings.mine', [
            'upcoming' => $upcoming->sortBy(fn (MeetingAttendee $row) => $row->meeting->starts_at)->values(),
            'past' => $past->sortByDesc(fn (MeetingAttendee $row) => $row->meeting->starts_at)->take(20)->values(),
        ]);
    }

    public function respond(Request $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', Rule::in(['accepted', 'declined'])]]);
        $user = $request->user();

        $attendee = MeetingAttendee::query()->where('meeting_id', $meeting->id)->where('user_id', $user->id)->whereNotNull('invited_at')->firstOrFail();
        abort_unless($meeting->isOpen(), 422, 'Cette réunion n’accepte plus de réponse.');

        $attendee->update(['response' => $data['response'], 'responded_at' => now()]);

        if ($meeting->organizer_id !== null && $meeting->organizer_id !== $user->id) {
            AppNotification::query()->create([
                'user_id' => $meeting->organizer_id,
                'type' => 'meeting.response',
                'title' => 'Réponse à votre réunion',
                'body' => $user->name.($data['response'] === 'accepted' ? ' sera présent(e)' : ' ne pourra pas venir').' à « '.$meeting->title.' ».',
                'data' => ['url' => route('admin.meetings.show', $meeting, false)],
            ]);
        }

        return back()->with('status', $data['response'] === 'accepted' ? 'Votre présence est confirmée.' : 'Votre absence est enregistrée.');
    }
}
