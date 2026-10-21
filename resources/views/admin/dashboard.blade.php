@extends('admin.layouts.app')

@section('title', 'Tableau de bord')
@section('section', 'Tableau de bord')

@section('content')
    <x-admin.page-header :title="'Bonjour, '.$firstName" :subtitle='ucfirst($today)." — voici l’état de la plateforme."'>
        <x-slot:actions>
            @if($ctx->can('entity.manage'))
                <a href="{{ route('orgchart.index') }}" class="btn btn-secondary"><x-admin.icon name="network" :size="16" /> Organigramme</a>
            @endif
            @if($ctx->can('event.manage'))
                <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvel événement</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if(count($stats))
        <section class="stats">
            @foreach($stats as $stat)
                <x-admin.stat :label="$stat['label']" :value="number_format($stat['value'], 0, ',', ' ')" :icon="$stat['icon']" :tone="$stat['tone']" :hint="$stat['hint']" :href="$stat['href']" />
            @endforeach
        </section>
    @endif

    @if($chart || $ctx->can('entity.manage'))
        <section class="grid-3-1">
            @if($chart)
                <article class="card">
                    <div class="card-head">
                        <div>
                            <h2>Activité des 14 derniers jours</h2>
                            <p class="sub">{{ $chart['total_users'] }} inscription(s) · {{ $chart['total_posts'] }} publication(s)</p>
                        </div>
                        <div class="legend">
                            <span><i style="background: var(--cyan)"></i>Inscriptions</span>
                            <span><i style="background: var(--navy-800)"></i>Publications</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart" role="img" aria-label="Graphique d'activité sur 14 jours">
                            @foreach($chart['days'] as $day)
                                <div class="chart-col" title="{{ $day['label'] }} : {{ $day['users'] }} inscription(s), {{ $day['posts'] }} publication(s)">
                                    <div class="chart-bars">
                                        <span class="chart-bar users" style="height: {{ max($day['users_pct'], 2) }}%"></span>
                                        <span class="chart-bar posts" style="height: {{ max($day['posts_pct'], 2) }}%"></span>
                                    </div>
                                    <span class="chart-label">{{ substr($day['label'], 0, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @endif

            <article class="card">
                <div class="card-head"><h2>Accès rapides</h2></div>
                <div class="quick">
                    @if($ctx->can('entity.manage'))
                        <a href="{{ route('orgchart.index') }}"><x-admin.icon name="network" /> Organigramme</a>
                    @endif
                    @if($ctx->canAny(['entity.manage', 'membership.manage']))
                        <a href="{{ route('admin.users.index') }}"><x-admin.icon name="user" /> Utilisateurs</a>
                    @endif
                    @if($ctx->can('post.moderate'))
                        <a href="{{ route('admin.moderation.index') }}"><x-admin.icon name="flag" /> Modération</a>
                    @endif
                    @if($ctx->can('approval.review'))
                        <a href="{{ route('admin.approvals.index') }}"><x-admin.icon name="shield" /> Approbations</a>
                    @endif
                    @if($ctx->can('event.manage'))
                        <a href="{{ route('admin.events.index') }}"><x-admin.icon name="calendar" /> Événements</a>
                    @endif
                </div>
            </article>
        </section>
    @endif

    <section class="grid-2">
        @if($pendingMemberships !== null)
            <article class="card card-flush">
                <div class="card-head">
                    <div><h2>Adhésions en attente</h2><p class="sub">Demandes à examiner</p></div>
                    <a href="{{ route('admin.memberships.index', ['status' => 'pending']) }}" class="btn btn-ghost btn-sm">Tout voir <x-admin.icon name="arrow-right" :size="14" /></a>
                </div>
                @forelse($pendingMemberships as $membership)
                    <a href="{{ route('admin.memberships.index', ['status' => 'pending']) }}" class="list-item">
                        <x-admin.avatar :name="$membership->user?->name ?? '?'" :size="38" />
                        <div class="grow">
                            <strong>{{ $membership->user?->name ?? 'Utilisateur supprimé' }}</strong>
                            <span class="cell-sub">{{ \App\Support\OrgScopes::label($membership->scope) ?? '—' }} · {{ $membership->membership_type }}</span>
                        </div>
                        <span class="text-sm text-muted nowrap">{{ $membership->created_at?->diffForHumans() }}</span>
                    </a>
                @empty
                    <x-admin.empty icon="check" title="Tout est à jour" text="Aucune adhésion en attente." />
                @endforelse
            </article>
        @endif

        @if($pendingReports !== null)
            <article class="card card-flush">
                <div class="card-head">
                    <div><h2>Signalements ouverts</h2><p class="sub">Contenus à modérer</p></div>
                    <a href="{{ route('admin.moderation.index') }}" class="btn btn-ghost btn-sm">Tout voir <x-admin.icon name="arrow-right" :size="14" /></a>
                </div>
                @forelse($pendingReports as $report)
                    <a href="{{ route('admin.moderation.index') }}" class="list-item">
                        <span class="stat-icon tone-red" style="width:38px;height:38px;border-radius:11px"><x-admin.icon name="flag" :size="18" /></span>
                        <div class="grow">
                            <strong>{{ \Illuminate\Support\Str::limit($report->reason, 60) }}</strong>
                            <span class="cell-sub">Signalement #{{ $report->id }}</span>
                        </div>
                        <span class="text-sm text-muted nowrap">{{ $report->created_at?->diffForHumans() }}</span>
                    </a>
                @empty
                    <x-admin.empty icon="check" title="Aucun signalement" text="La communauté est calme." />
                @endforelse
            </article>
        @endif
    </section>

    @if($organization !== null || $recentAudit !== null)
        <section class="grid-2">
            @if($organization !== null)
                <article class="card card-flush">
                    <div class="card-head">
                        <div><h2>Organisation</h2><p class="sub">Répartition par périmètre</p></div>
                        <a href="{{ route('orgchart.index') }}" class="btn btn-ghost btn-sm">Gérer <x-admin.icon name="arrow-right" :size="14" /></a>
                    </div>
                    @forelse($organization as $scopeRow)
                        <a href="{{ route('orgchart.index') }}" class="list-item">
                            <span class="stat-icon tone-blue" style="width:38px;height:38px;border-radius:11px"><x-admin.icon name="building" :size="18" /></span>
                            <div class="grow">
                                <strong>{{ $scopeRow['name'] }}</strong>
                                <span class="cell-sub">{{ $scopeRow['clubs'] }} clubs · {{ $scopeRow['memberships'] }} adhésions</span>
                            </div>
                        </a>
                    @empty
                        <x-admin.empty icon="building" title="Aucun périmètre" text="Aucun périmètre n’est géré par votre compte." />
                    @endforelse
                </article>
            @endif

            @if($recentAudit !== null)
                <article class="card card-flush">
                    <div class="card-head">
                        <div><h2>Activité récente</h2><p class="sub">Dernières actions tracées</p></div>
                        <a href="{{ route('admin.audit.index') }}" class="btn btn-ghost btn-sm">Journal <x-admin.icon name="arrow-right" :size="14" /></a>
                    </div>
                    @forelse($recentAudit as $log)
                        <div class="list-item">
                            <x-admin.avatar :name="$log->actor?->name ?? 'Système'" :size="38" />
                            <div class="grow">
                                <strong>{{ \App\Support\AuditLabels::label($log->action) }}</strong>
                                <span class="cell-sub">{{ $log->actor?->name ?? 'Système' }}</span>
                            </div>
                            <span class="text-sm text-muted nowrap">{{ $log->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <x-admin.empty icon="activity" title="Aucune activité" text="Les actions apparaîtront ici." />
                    @endforelse
                </article>
            @endif
        </section>
    @endif
@endsection
