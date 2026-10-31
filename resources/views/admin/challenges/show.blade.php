@extends('admin.layouts.app')

@section('title', $challenge->title)
@section('section', 'Défis')

@section('content')
    <x-admin.page-header :title="$challenge->title" :subtitle="'Thématique : '.$challenge->theme">
        <x-slot:actions>
            <a href="{{ route('admin.challenges.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            <a href="{{ route('challenges.show', $challenge) }}" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Vue membres</a>
            <a href="{{ route('admin.challenges.edit', $challenge) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="16" /> Modifier</a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="stats">
        <x-admin.stat label="Étape" :value="$challenge->phaseLabel()" icon="flag" tone="blue" :hint="($scopeName ?? '—')" />
        <x-admin.stat label="Début → fin" :value="$challenge->starts_at->format('d/m').' → '.$challenge->ends_at->format('d/m/Y')" icon="calendar" tone="cyan" hint="Activités à organiser" />
        <x-admin.stat label="Évaluation" :value="$challenge->evaluation_at->format('d/m/Y')" icon="users" tone="amber" hint="Réunion du comité" />
        <x-admin.stat label="Résultats" :value="$challenge->results_at->format('d/m/Y')" icon="award" tone="green" hint="Publication du classement" />
    </section>

    <div class="grid-3-1">
        <div style="min-width:0;display:grid;gap:22px">
            <article class="card card-flush">
                <div class="card-head"><div><h2>Activités</h2><div class="sub">{{ $activities->count() }} activité(s) proposée(s) par les clubs</div></div></div>
                @if($activities->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Activité</th><th>Club(s)</th><th>Nature</th><th>Inscrits</th><th>Note</th><th>Statut</th></tr></thead>
                            <tbody>
                                @foreach($activities as $activity)
                                    @php($total = $totals[$activity->id] ?? null)
                                    <tr>
                                        <td><a href="{{ route('activities.show', $activity) }}" class="cell-main">{{ $activity->title }}</a><div class="cell-sub">{{ $activity->typeLabel() }} · {{ $activity->starts_at->format('d/m/Y H:i') }}</div></td>
                                        <td class="text-sm">{{ $activity->club?->title }}@foreach($activity->organizers->where('status', 'accepted') as $organizer), {{ $organizer->club?->title }}@endforeach</td>
                                        <td>{{ $activity->in_theme ? 'Thématique' : 'Hors thématique' }}</td>
                                        <td>{{ $activity->registrations_count }}</td>
                                        <td class="nowrap">@if(($total['total'] ?? null) !== null){{ rtrim(rtrim(number_format($total['total'], 2, ',', ''), '0'), ',') }} / {{ $total['max'] }}@else<span class="text-muted">—</span>@endif</td>
                                        <td><x-admin.badge :status="$activity->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty icon="calendar" title="Aucune activité" text="Les clubs invités n’ont encore proposé aucune activité." />
                @endif
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Classement{{ $challenge->resultsVisible() ? '' : ' provisoire' }}</h2><div class="sub">{{ $challenge->resultsVisible() ? 'Publié aux membres.' : 'Visible des seuls gestionnaires jusqu’au '.$challenge->results_at->format('d/m/Y H:i').'.' }}</div></div></div>
                @if($ranking->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Rang</th><th>Club</th><th>Activités notées</th><th>Thématique</th><th>Hors thématique</th><th>Total</th></tr></thead>
                            <tbody>
                                @foreach($ranking as $row)
                                    <tr>
                                        <td>{{ $row['rank'] }}</td><td class="cell-main">{{ $row['club']->title }}</td><td>{{ $row['activities'] }}</td>
                                        <td>{{ rtrim(rtrim(number_format($row['theme_points'], 2, ',', ''), '0'), ',') }}</td>
                                        <td>{{ rtrim(rtrim(number_format($row['off_theme_points'], 2, ',', ''), '0'), ',') }}</td>
                                        <td><strong>{{ rtrim(rtrim(number_format($row['points'], 2, ',', ''), '0'), ',') }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty icon="award" title="Pas encore de note" text="Le classement apparaît dès que le comité a noté des activités." />
                @endif
            </article>
        </div>

        <div style="min-width:0;display:grid;gap:22px;align-content:start">
            <article class="card">
                <div class="card-head"><div><h2>Barèmes</h2></div></div>
                <div class="card-body">
                    @foreach(\App\Models\Challenge::KINDS as $kind => $label)
                        <h3 style="margin-top:0">{{ $label }}</h3>
                        @forelse($challenge->criteriaOf($kind) as $criterion)
                            <p class="text-sm" style="display:flex;justify-content:space-between;gap:10px"><span>{{ $criterion->label }}</span><strong>{{ $criterion->max_points }} pts</strong></p>
                        @empty
                            <p class="text-sm text-muted">Aucun critère{{ $kind === 'off_theme' ? ' : activités hors thématique non acceptées.' : '.' }}</p>
                        @endforelse
                        @if($kind === 'theme')<hr style="margin:12px 0;border:0;border-top:1px solid var(--line)">@endif
                    @endforeach
                </div>
            </article>
            <article class="card">
                <div class="card-head"><div><h2>Comité</h2><div class="sub">{{ $challenge->committee->count() }} membre(s)</div></div></div>
                <div class="card-body">
                    @forelse($challenge->committee as $member)
                        <p class="text-sm" style="margin:0 0 6px"><strong>{{ $member->name }}</strong><br><span class="text-muted">{{ $member->email }}</span></p>
                    @empty
                        <p class="text-sm text-muted">Aucun membre désigné.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </div>
@endsection
