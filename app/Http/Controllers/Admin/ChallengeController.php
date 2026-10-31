<?php

namespace App\Http\Controllers\Admin;

use App\Models\Challenge;
use App\Models\ChallengeCriterion;
use App\Models\User;
use App\Support\Challenges\ChallengeScoring;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Défis thématiques lancés par les bureaux de chapitres et de projets. */
class ChallengeController extends AdminController
{
    private const MAX_CRITERIA = 15;

    public function index(Request $request): View
    {
        $challenges = $this->scoped(Challenge::query(), $request, 'event.manage')
            ->withCount('activities')
            ->orderByDesc('starts_at')
            ->paginate(15);

        return view('admin.challenges.index', ['challenges' => $challenges]);
    }

    public function create(Request $request): View
    {
        return view('admin.challenges.form', $this->formData($request, new Challenge(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $challenge = DB::transaction(function () use ($request, $data): Challenge {
            $challenge = Challenge::query()->create($data['attributes'] + ['created_by' => $request->user()->id]);
            $this->syncCriteria($challenge, $data['criteria'], false);
            $challenge->committee()->sync($data['committee']);

            return $challenge;
        });

        $this->audit($request, 'admin.challenge.created', $challenge, ['title' => $challenge->title, 'scope' => $challenge->scope]);

        return redirect()->route('admin.challenges.show', $challenge)->with('status', "Défi « {$challenge->title} » créé.");
    }

    public function show(Request $request, Challenge $challenge): View
    {
        $this->authorizeScope($request, 'event.manage', $challenge->scope);

        $challenge->load(['criteria', 'committee:id,name,email']);
        $activities = $challenge->activities()->with(['club:id,title', 'organizers.club:id,title'])->withCount(['registrations' => fn ($query) => $query->where('status', 'registered')])->orderBy('starts_at')->get();

        return view('admin.challenges.show', [
            'challenge' => $challenge,
            'activities' => $activities,
            'totals' => ChallengeScoring::activityTotals($challenge),
            'ranking' => ChallengeScoring::ranking($challenge),
            'scopeName' => OrgScopes::label($challenge->scope),
        ]);
    }

    public function edit(Request $request, Challenge $challenge): View
    {
        $this->authorizeScope($request, 'event.manage', $challenge->scope);

        return view('admin.challenges.form', $this->formData($request, $challenge));
    }

    public function update(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->authorizeScope($request, 'event.manage', $challenge->scope);

        $data = $this->validated($request);
        $scored = $challenge->activities()->whereHas('scores')->exists();

        DB::transaction(function () use ($challenge, $data, $scored): void {
            $challenge->update($data['attributes']);
            $this->syncCriteria($challenge, $data['criteria'], $scored);
            $challenge->committee()->sync($data['committee']);
        });

        $this->audit($request, 'admin.challenge.updated', $challenge, ['title' => $challenge->title, 'status' => $challenge->status]);

        return redirect()->route('admin.challenges.show', $challenge)->with('status', 'Défi mis à jour.'.($scored ? ' Des notes existent : seuls les intitulés du barème ont été modifiés.' : ''));
    }

    public function destroy(Request $request, Challenge $challenge): RedirectResponse
    {
        $this->authorizeScope($request, 'event.manage', $challenge->scope);

        if ($challenge->activities()->exists()) {
            return back()->with('error', 'Suppression impossible : des activités existent. Archivez plutôt le défi.');
        }

        $title = $challenge->title;
        $this->audit($request, 'admin.challenge.deleted', $challenge, ['title' => $title]);
        $challenge->delete();

        return redirect()->route('admin.challenges.index')->with('status', "Défi « {$title} » supprimé.");
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, Challenge $challenge): array
    {
        $challenge->loadMissing('criteria', 'committee:id,name,email');

        return [
            'challenge' => $challenge,
            'scopes' => $this->scopeOptions($request, 'event.manage'),
            'allScopes' => OrgScopes::all(),
            'scored' => $challenge->exists && $challenge->activities()->whereHas('scores')->exists(),
        ];
    }

    /**
     * @return array{attributes: array<string, mixed>, criteria: array<string, array<int, array<string, mixed>>>, committee: array<int, int>}
     */
    private function validated(Request $request): array
    {
        $scopes = array_keys($this->scopeOptions($request, 'event.manage'));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'theme' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'scope' => ['required', Rule::in($scopes)],
            'invited_scopes' => ['nullable', 'array'],
            'invited_scopes.*' => [Rule::in(OrgScopes::slugs())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'evaluation_at' => ['required', 'date', 'after_or_equal:ends_at'],
            'results_at' => ['required', 'date', 'after_or_equal:evaluation_at'],
            'status' => ['required', Rule::in(array_keys(Challenge::STATUSES))],
            'criteria' => ['nullable', 'array'],
            'committee_emails' => ['nullable', 'string', 'max:5000'],
        ]);

        $criteria = [];

        foreach (array_keys(Challenge::KINDS) as $kind) {
            $rows = [];

            foreach ((array) ($data['criteria'][$kind] ?? []) as $row) {
                $label = trim((string) ($row['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                $points = $row['max_points'] ?? null;

                if (! is_numeric($points) || (int) $points < 1 || (int) $points > 1000 || (int) $points != $points) {
                    throw ValidationException::withMessages(["criteria.$kind" => "Chaque critère « {$label} » doit avoir un maximum de points entier entre 1 et 1000."]);
                }

                $rows[] = [
                    'id' => isset($row['id']) ? (int) $row['id'] : null,
                    'label' => mb_substr($label, 0, 160),
                    'description' => filled($row['description'] ?? null) ? mb_substr(trim((string) $row['description']), 0, 400) : null,
                    'max_points' => (int) $points,
                ];
            }

            if (count($rows) > self::MAX_CRITERIA) {
                throw ValidationException::withMessages(["criteria.$kind" => 'Pas plus de '.self::MAX_CRITERIA.' critères par barème.']);
            }

            $criteria[$kind] = $rows;
        }

        $committee = $this->committeeIds((string) ($data['committee_emails'] ?? ''));

        if ($data['status'] === 'published') {
            if ($criteria['theme'] === []) {
                throw ValidationException::withMessages(['criteria.theme' => 'Définissez au moins un critère de notation pour la thématique avant de publier.']);
            }

            if ($committee === []) {
                throw ValidationException::withMessages(['committee_emails' => 'Désignez au moins un membre du comité avant de publier.']);
            }
        }

        $invited = array_values(array_unique((array) ($data['invited_scopes'] ?? [])));

        return [
            'attributes' => [
                'title' => $data['title'],
                'theme' => $data['theme'],
                'description' => $data['description'] ?? null,
                'scope' => $data['scope'],
                'invited_scopes' => $invited === [] ? null : $invited,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'evaluation_at' => $data['evaluation_at'],
                'results_at' => $data['results_at'],
                'status' => $data['status'],
            ],
            'criteria' => $criteria,
            'committee' => $committee,
        ];
    }

    /**
     * Comité : une adresse e-mail par ligne ou séparée par des virgules ; chaque adresse doit être celle d'un compte existant.
     *
     * @return array<int, int>
     */
    private function committeeIds(string $raw): array
    {
        $emails = collect(preg_split('/[\s,;]+/', mb_strtolower($raw)) ?: [])->map(fn ($email) => trim($email))->filter()->unique()->values();

        if ($emails->isEmpty()) {
            return [];
        }

        $users = User::query()->whereIn('email', $emails)->pluck('id', 'email');
        $unknown = $emails->reject(fn (string $email): bool => $users->has($email))->values();

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['committee_emails' => 'Aucun compte pour : '.$unknown->implode(', ').'.']);
        }

        return $users->values()->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * Met à jour les barèmes. Dès que des notes existent, la structure est figée : seuls les intitulés changent.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $criteria
     */
    private function syncCriteria(Challenge $challenge, array $criteria, bool $frozen): void
    {
        foreach ($criteria as $kind => $rows) {
            $existing = $challenge->criteria()->where('kind', $kind)->get()->keyBy('id');

            if ($frozen) {
                foreach ($rows as $row) {
                    $existing->get($row['id'])?->update(['label' => $row['label'], 'description' => $row['description']]);
                }

                continue;
            }

            $keep = [];

            foreach ($rows as $order => $row) {
                $values = ['label' => $row['label'], 'description' => $row['description'], 'max_points' => $row['max_points'], 'sort_order' => $order];

                if ($row['id'] !== null && $existing->has($row['id'])) {
                    $existing[$row['id']]->update($values);
                    $keep[] = $row['id'];
                } else {
                    $keep[] = ChallengeCriterion::query()->create($values + ['challenge_id' => $challenge->id, 'kind' => $kind])->id;
                }
            }

            $challenge->criteria()->where('kind', $kind)->whereNotIn('id', $keep)->delete();
        }
    }
}
