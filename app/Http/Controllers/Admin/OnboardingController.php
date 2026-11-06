<?php

namespace App\Http\Controllers\Admin;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\Onboarding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suivi du parcours d'intégration de chaque compte (étape en cours, étapes franchies) et relance d'un parcours.
 * Réservé aux administrateurs globaux : un compte en cours d'intégration n'a pas encore de rattachement à un périmètre.
 */
class OnboardingController extends AdminController
{
    /** Étape bloquante → filtre SQL (les trois premières étapes se lisent directement sur le compte). */
    private const STEP_FILTERS = ['email', 'info_session', 'history', 'next'];

    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['progress', 'done', 'all'])],
            'step' => ['nullable', Rule::in(self::STEP_FILTERS)],
        ]);

        $status = $filters['status'] ?? 'progress';
        $step = $status === 'progress' ? ($filters['step'] ?? null) : null;

        $search = function (Builder $query) use ($filters): Builder {
            return $query->when($filters['q'] ?? null, function (Builder $inner, string $needle): void {
                $inner->where(fn (Builder $match) => $match->where('name', 'like', "%{$needle}%")->orWhere('first_name', 'like', "%{$needle}%")->orWhere('last_name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"));
            });
        };

        $inProgress = fn (): Builder => $search(User::query())->whereNull('onboarding_done_at');

        $counts = [
            'all' => $search(User::query())->count(),
            'progress' => $inProgress()->count(),
            'done' => $search(User::query())->whereNotNull('onboarding_done_at')->count(),
        ];

        $stepCounts = [
            'email' => $this->atStep($inProgress(), 'email')->count(),
            'info_session' => $this->atStep($inProgress(), 'info_session')->count(),
            'history' => $this->atStep($inProgress(), 'history')->count(),
            'next' => $this->atStep($inProgress(), 'next')->count(),
        ];

        $users = $search(User::query())
            ->with('profile:id,user_id,photo_path,privacy_settings')
            ->when($status === 'progress', fn (Builder $query) => $query->whereNull('onboarding_done_at'))
            ->when($status === 'done', fn (Builder $query) => $query->whereNotNull('onboarding_done_at'))
            ->when($step !== null, fn (Builder $query) => $this->atStep($query, $step))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $attempts = QuizAttempt::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('status', '!=', 'in_progress')
            ->orderBy('id')
            ->get(['user_id', 'status', 'score', 'total', 'submitted_at'])
            ->groupBy('user_id');

        $rows = $users->getCollection()->map(fn (User $user): array => [
            'user' => $user,
            'progress' => Onboarding::progress($user),
            'attempts' => $attempts->get($user->id, collect()),
        ]);

        return view('admin.onboarding.index', [
            'rows' => $rows,
            'users' => $users,
            'filters' => ['q' => $filters['q'] ?? null, 'status' => $status, 'step' => $step],
            'counts' => $counts,
            'stepCounts' => $stepCounts,
            'steps' => Onboarding::STEPS,
        ]);
    }

    public function restart(Request $request, User $user): RedirectResponse
    {
        $this->authorizeGlobal($request);

        // Se relancer soi-même bloquerait aussitôt l'accès à l'application, administration comprise.
        abort_if($user->is($request->user()), 403, 'Vous ne pouvez pas relancer votre propre intégration.');

        $data = $request->validate(['revalidate_email' => ['nullable', 'boolean']]);
        $revalidate = (bool) ($data['revalidate_email'] ?? false);

        Onboarding::restart(User::query()->whereKey($user->id), keepEmail: ! $revalidate);

        $this->audit($request, 'admin.onboarding.restarted', $user, ['target' => $user->name, 'email_revalidation' => $revalidate]);

        return back()->with('status', 'Intégration relancée pour '.$user->name.' : le parcours reprend à '.($revalidate ? 'la vérification de l’e-mail.' : 'la session d’information.'));
    }

    /**
     * Comptes dont l'étape bloquante est celle-ci. « next » regroupe le profil et l'adhésion ou le poste du mandat en cours,
     * qui se calculent d'après des données liées (photo, CV, adhésions) et non sur le compte seul.
     *
     * @param Builder<User> $query
     * @return Builder<User>
     */
    private function atStep(Builder $query, string $step): Builder
    {
        return match ($step) {
            'email' => $query->whereNull('email_verified_at'),
            'info_session' => $query->whereNotNull('email_verified_at')->whereNull('onboarding_completed_at'),
            'history' => $query->whereNotNull('email_verified_at')->whereNotNull('onboarding_completed_at')->whereNull('history_onboarding_at'),
            default => $query->whereNotNull('email_verified_at')->whereNotNull('onboarding_completed_at')->whereNotNull('history_onboarding_at'),
        };
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Seuls les administrateurs globaux suivent l’intégration des membres.');
    }
}
