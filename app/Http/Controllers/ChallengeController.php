<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Support\AccessScope;
use App\Support\Challenges\ActivityAccess;
use App\Support\Challenges\ChallengeScoring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Défis thématiques : consultation par les membres, évaluation par le comité. */
class ChallengeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $evaluating = Challenge::query()->where('status', 'published')->whereHas('committee', fn ($query) => $query->whereKey($user->id))->get();
        $scopes = ActivityAccess::scopesOf($user);

        // Seuls les défis des périmètres de l'utilisateur (ou ceux où il siège au comité).
        $challenges = Challenge::query()->where('status', 'published')->withCount(['activities' => fn ($query) => $query->where('status', 'published')])
            ->orderByDesc('starts_at')->get()
            ->filter(fn (Challenge $challenge): bool => $evaluating->contains('id', $challenge->id) || $challenge->reachesScopes($scopes))
            ->groupBy(fn (Challenge $challenge): string => in_array($challenge->phase(), ['upcoming', 'running'], true) ? 'open' : 'past');

        return view('challenges.index', [
            'open' => $challenges->get('open', collect()),
            'past' => $challenges->get('past', collect()),
            'evaluating' => $evaluating,
        ]);
    }

    public function show(Request $request, Challenge $challenge): View
    {
        $user = $request->user();
        $this->authorizeView($user, $challenge);

        $challenge->load(['criteria', 'committee:id,name']);
        $activities = $challenge->activities()->where('status', 'published')->with(['club:id,title', 'organizers.club:id,title'])->orderBy('starts_at')->get();
        $results = $challenge->resultsVisible();

        return view('challenges.show', [
            'challenge' => $challenge,
            'activities' => $activities,
            'results' => $results,
            'ranking' => $results ? ChallengeScoring::ranking($challenge) : collect(),
            'totals' => $results ? ChallengeScoring::activityTotals($challenge) : [],
            'canPropose' => $challenge->acceptsActivities() && ActivityAccess::manageableClubs($user)->get()->contains(fn ($club) => $challenge->invitesClub($club)),
            'isCommittee' => $challenge->isCommitteeMember($user),
        ]);
    }

    /** Écran du comité : une fiche de notation par activité. */
    public function evaluation(Request $request, Challenge $challenge): View
    {
        $user = $request->user();
        abort_unless($challenge->isPublished() && $challenge->isCommitteeMember($user), 403, 'Réservé au comité d’évaluation.');

        $challenge->load('criteria');
        $activities = $challenge->activities()->where('status', 'published')->with(['club:id,title', 'organizers.club:id,title', 'report.members.user:id,name', 'report.items', 'report.partners', 'tasks.assignees:id,name', 'scores' => fn ($query) => $query->where('evaluator_id', $user->id)])->orderBy('starts_at')->get();
        $activities->each(fn (ClubActivity $activity) => $activity->report?->setRelation('activity', $activity->setRelation('challenge', $challenge)));
        $myNodes = ActivityAccess::nodeIds($user);

        return view('challenges.evaluation', [
            'challenge' => $challenge,
            'activities' => $activities,
            'totals' => ChallengeScoring::activityTotals($challenge),
            'conflicts' => $activities->filter(fn (ClubActivity $activity): bool => array_intersect($activity->organizerClubIds(), $myNodes) !== [])->pluck('id')->all(),
        ]);
    }

    public function score(Request $request, Challenge $challenge, ClubActivity $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($challenge->isCommitteeMember($user), 403, 'Réservé au comité d’évaluation.');
        abort_unless($activity->challenge_id === $challenge->id && $activity->status === 'published', 404);

        if (! $challenge->scoringOpen()) {
            return back()->with('error', 'La notation est fermée : elle ouvre à la fin du défi et ferme à la publication des résultats.');
        }

        if (array_intersect($activity->organizerClubIds(), ActivityAccess::nodeIds($user)) !== []) {
            return back()->with('error', 'Conflit d’intérêt : vous appartenez à un club organisateur de cette activité.');
        }

        if ($activity->report?->isValidated() !== true) {
            return back()->with('error', 'Cette activité ne peut pas encore être notée : son compte rendu n’a pas été validé par le président du club.');
        }

        $data = $request->validate([
            'points' => ['required', 'array'],
            'comments' => ['nullable', 'array'],
        ]);

        $challenge->loadMissing('criteria');
        ChallengeScoring::record($challenge, $activity, $user, $data['points'], (array) ($data['comments'] ?? []));

        return redirect()->to(route('challenges.evaluation', $challenge).'#activite-'.$activity->id)->with('status', "Notes enregistrées pour « {$activity->title} ».");
    }

    /** Un défi publié est visible des membres de ses périmètres et de son comité ; un brouillon seulement de ses gestionnaires. */
    private function authorizeView($user, Challenge $challenge): void
    {
        if ($challenge->isPublished() && $challenge->status === 'published') {
            abort_unless($challenge->reachesScopes(ActivityAccess::scopesOf($user)) || $challenge->isCommitteeMember($user), 404);

            return;
        }

        abort_unless(AccessScope::can($user, 'event.manage', $challenge->scope), 404);
    }
}
