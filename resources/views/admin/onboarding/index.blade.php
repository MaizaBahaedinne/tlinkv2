@extends('admin.layouts.app')

@section('title', 'Intégration des membres')
@section('section', 'Intégration des membres')

@section('content')
    <x-admin.page-header title="Intégration des membres" subtitle="Avancement du parcours de chaque compte : e-mail, info session, ancien ou nouveau membre, profil, puis adhésion ou poste du mandat en cours. Un parcours peut être relancé." />

    @php
        $query = array_filter(['q' => $filters['q'] ?? null]);
        $stepLabels = ['email' => 'E-mail à vérifier', 'info_session' => 'Info session', 'history' => 'Ancien ou nouveau', 'next' => 'Profil / adhésion'];
    @endphp

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.onboarding.index', $query + ['status' => 'progress']) }}" class="tab {{ $filters['status'] === 'progress' ? 'is-active' : '' }}">En cours <span class="n">{{ $counts['progress'] }}</span></a>
            <a href="{{ route('admin.onboarding.index', $query + ['status' => 'done']) }}" class="tab {{ $filters['status'] === 'done' ? 'is-active' : '' }}">Terminé <span class="n">{{ $counts['done'] }}</span></a>
            <a href="{{ route('admin.onboarding.index', $query + ['status' => 'all']) }}" class="tab {{ $filters['status'] === 'all' ? 'is-active' : '' }}">Tous <span class="n">{{ $counts['all'] }}</span></a>
        </div>

        @if($filters['status'] === 'progress')
            <div class="tabs" aria-label="Étape bloquante">
                <a href="{{ route('admin.onboarding.index', $query + ['status' => 'progress']) }}" class="tab {{ empty($filters['step']) ? 'is-active' : '' }}">Toutes les étapes</a>
                @foreach($stepLabels as $key => $label)
                    <a href="{{ route('admin.onboarding.index', $query + ['status' => 'progress', 'step' => $key]) }}" class="tab {{ ($filters['step'] ?? '') === $key ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $stepCounts[$key] }}</span></a>
                @endforeach
            </div>
        @endif

        <div class="toolbar">
            <form method="get" action="{{ route('admin.onboarding.index') }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @if(! empty($filters['step']))<input type="hidden" name="step" value="{{ $filters['step'] }}">@endif
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher par nom ou e-mail…">
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Rechercher</button>
                @if(! empty($filters['q']))
                    <a href="{{ route('admin.onboarding.index', array_filter(['status' => $filters['status'], 'step' => $filters['step'] ?? null])) }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($rows->isNotEmpty())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Avancement</th>
                            <th class="hide-mobile">Quiz</th>
                            <th class="hide-mobile">Inscrit le</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $user = $row['user'];
                                $progress = $row['progress'];
                                $attempts = $row['attempts'];
                                $lastAttempt = $attempts->last();
                                $doneCount = collect($progress['steps'])->filter()->count();
                            @endphp
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <x-admin.avatar :name="$user->name" :size="34" :src="$user->profile?->photoUrl()" />
                                        <div>
                                            <a href="{{ route('admin.users.show', $user) }}" class="cell-main">{{ $user->name }}</a>
                                            <div class="cell-sub">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($progress['complete'])
                                        <span class="badge badge-success">Terminé{{ $user->onboarding_done_at ? ' le '.$user->onboarding_done_at->format('d/m/Y') : '' }}</span>
                                    @else
                                        <div class="text-sm"><strong>{{ $doneCount }} / {{ count($steps) }}</strong>
                                            @if($progress['current'])
                                                · bloqué à « {{ $steps[$progress['current']] }} »
                                            @else
                                                · tout est validé, en attente de la prochaine connexion
                                            @endif
                                        </div>
                                        <div class="flex gap-1" style="margin-top:.35rem;flex-wrap:wrap" aria-label="Étapes">
                                            @foreach($steps as $key => $label)
                                                @php $ok = $progress['steps'][$key]; $now = $progress['current'] === $key; @endphp
                                                <span class="badge {{ $ok ? 'badge-success' : ($now ? 'badge-warning' : 'badge-neutral') }}" title="{{ $label }}">{{ $ok ? '✓' : ($now ? '●' : '○') }} {{ $label }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="hide-mobile text-sm">
                                    @if($lastAttempt)
                                        {{ $attempts->count() }} tentative(s)<div class="cell-sub">dernière : {{ $lastAttempt->status === 'expired' ? 'temps écoulé' : $lastAttempt->score.' / '.$lastAttempt->total }}</div>
                                    @else
                                        <span class="text-muted">Aucune</span>
                                    @endif
                                </td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ $user->created_at?->format('d/m/Y') }}</td>
                                <td class="cell-actions">
                                    @if($user->is(auth()->user()))
                                        <span class="text-muted text-sm">Vous</span>
                                    @else
                                        <details>
                                            <summary class="btn btn-secondary btn-sm">Relancer</summary>
                                            <form method="post" action="{{ route('admin.onboarding.restart', $user) }}" style="margin-top:.5rem;min-width:15rem" onsubmit="return confirm('Relancer l’intégration de {{ addslashes($user->name) }} ?');">
                                                @csrf
                                                <label class="text-sm" style="display:flex;gap:.5rem;align-items:flex-start">
                                                    <input type="checkbox" name="revalidate_email" value="1">
                                                    <span>Faire revérifier l’e-mail</span>
                                                </label>
                                                <p class="text-sm text-muted" style="margin:.4rem 0">Reprend à l’info session, puis « ancien ou nouveau ». Le profil et l’adhésion restent validés s’ils existent déjà.</p>
                                                <button type="submit" class="btn btn-primary btn-sm">Relancer le parcours</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user" title="Aucun membre" text="Aucun compte ne correspond à ces critères." />
        @endif
    </article>
@endsection
