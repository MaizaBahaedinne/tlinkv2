@extends('admin.layouts.app')

@section('title', $club->title)
@section('section', 'Organisation')

@php
    $percent = $positions->count() > 0 ? (int) round($filled / $positions->count() * 100) : 0;
    $vacant = $positions->count() - $filled;
    $members = array_sum($memberStats);
    $president = $positions->first(fn ($position) => $position->role_key === 'president' || str_starts_with($position->title, 'Président'));
    $presidentName = $president ? ($assignments[$president->id] ?? collect())->first()?->person?->displayName() : null;
    $memberLabels = ['approved' => 'Approuvées', 'pending' => 'En attente', 'rejected' => 'Rejetées', 'suspended' => 'Suspendues'];
@endphp

@section('content')
    <x-admin.page-header :title="$club->title" :subtitle="(\App\Models\OrgNode::CATEGORIES[$club->category] ?? $club->category).($club->governorate ? ' · '.$club->governorate->name : '').($club->institution_name && $club->institution_name !== $club->title ? ' · '.$club->institution_name : '')">
        <x-slot:actions>
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Clubs</a>
            <a href="{{ route('admin.postes.edit', $club) }}" class="btn btn-secondary"><x-admin.icon name="edit" :size="16" /> {{ $canEdit ? 'Modifier la fiche' : 'Fiche' }}</a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="stats" style="margin-bottom:22px">
        <x-admin.stat label="Postes pourvus" :value="$filled.' / '.$positions->count()" icon="users" tone="cyan" :hint="$vacant.' vacant(s)'" />
        <x-admin.stat label="Adhésions" :value="$members" icon="user" tone="green" :hint="($memberStats['pending'] ?? 0).' en attente'" />
        <x-admin.stat label="Publications" :value="$postCount" icon="file-text" tone="blue" />
        <x-admin.stat label="Événements" :value="$eventCount" icon="calendar" tone="amber" :hint="$upcomingEvents->count().' à venir · '.$pastEvents.' passé(s)'" />
    </section>

    <div class="grid-3-1">
        <div style="display:grid;gap:22px;align-content:start">
            <article class="card card-flush">
                <div class="card-head">
                    <div>
                        <h2>Organigramme du club</h2>
                        <p class="sub">Les postes sont les mêmes dans tous les clubs University / High School.</p>
                    </div>
                    <x-admin.badge :status="$club->status ?? 'draft'" />
                </div>
                <div class="card-body">
                    @if($positions->count())
                        <div class="progress" style="margin-bottom:18px"><span style="width:{{ $percent }}%"></span></div>
                        <ul class="team-tree team-root">
                            <li class="team-node">
                                <div class="team-card is-club">
                                    <div class="team-head"><span class="team-title">{{ $club->title }}</span></div>
                                </div>
                                <ul class="team-tree">
                                    @foreach($tree[$club->id] ?? [] as $child)
                                        @include('admin.structure.partials.team-node', ['item' => $child, 'tree' => $tree, 'assignments' => $assignments])
                                    @endforeach
                                </ul>
                            </li>
                        </ul>
                    @else
                        <x-admin.empty icon="network" title="Aucune équipe" text="Ce club n’a pas encore de postes. Utilisez « Compléter les équipes » dans la liste des clubs." />
                    @endif
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Postes</h2></div></div>
                @if($positions->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Poste</th><th>Titulaire(s)</th><th>Accès</th></tr></thead>
                            <tbody>
                                @foreach($positions as $position)
                                    @php $holders = $assignments[$position->id] ?? collect(); @endphp
                                    <tr>
                                        <td><a href="{{ route('admin.postes.edit', $position) }}" class="cell-main">{{ $position->title }}</a></td>
                                        <td>
                                            @forelse($holders as $assignment)
                                                <div>{{ $assignment->person?->displayName() }}</div>
                                            @empty
                                                <span class="badge badge-warning">Vacant</span>
                                            @endforelse
                                        </td>
                                        <td>{{ $levels[$position->access_level] ?? $position->access_level }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="card-body"><p class="text-sm text-muted">Aucun poste.</p></div>
                @endif
            </article>
        </div>

        <div style="display:grid;gap:22px;align-content:start">
            <article class="card card-flush">
                <div class="card-head"><div><h2>Le club</h2></div></div>
                <div class="card-body">
                    <dl class="kv" style="grid-template-columns:1fr">
                        <div><dt>Président</dt><dd>{{ $presidentName ?? 'Vacant' }}</dd></div>
                        <div><dt>Établissement</dt><dd>{{ $club->institution_name ?? '—' }}</dd></div>
                        <div><dt>Gouvernorat</dt><dd>{{ $club->governorate?->name ?? '—' }}</dd></div>
                        <div><dt>Fondé le</dt><dd>{{ $club->founded_on?->format('d/m/Y') ?? '—' }}</dd></div>
                        <div><dt>Charte</dt><dd>{{ $club->charter_signed ? 'Signée' : 'Non signée' }}</dd></div>
                        <div><dt>Contact</dt><dd>{{ $club->email ?? '—' }}</dd></div>
                    </dl>
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Adhésions</h2></div></div>
                <div class="card-body">
                    @if($members > 0)
                        <div class="chips" style="margin-bottom:12px">
                            @foreach($memberLabels as $status => $label)
                                @if(! empty($memberStats[$status]))
                                    <span class="chip">{{ $label }} : {{ $memberStats[$status] }}</span>
                                @endif
                            @endforeach
                        </div>
                        @foreach($recentMembers as $membership)
                            <div class="team-holder" style="margin-bottom:8px"><x-admin.avatar :name="$membership->user?->name" :size="24" /> {{ $membership->user?->name ?? '—' }} <x-admin.badge :status="$membership->status" /></div>
                        @endforeach
                    @else
                        <p class="text-sm text-muted">Aucune adhésion enregistrée pour ce club.</p>
                    @endif
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Événements à venir</h2></div></div>
                <div class="card-body">
                    @forelse($upcomingEvents as $event)
                        <div style="margin-bottom:10px">
                            <div class="cell-main">{{ $event->title }}</div>
                            <div class="cell-sub">{{ $event->starts_at?->translatedFormat('d M Y, H:i') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">Aucun événement à venir.</p>
                    @endforelse
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Dernières publications</h2></div></div>
                <div class="card-body">
                    @forelse($recentPosts as $post)
                        <div style="margin-bottom:10px">
                            <div class="cell-main">{{ \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 80) }}</div>
                            <div class="cell-sub">{{ $post->author?->name }} · {{ ($post->published_at ?? $post->created_at)?->diffForHumans() }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">Aucune publication.</p>
                    @endforelse
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><div><h2>Activité récente</h2></div></div>
                <div class="card-body">
                    @forelse($logs as $log)
                        <div style="margin-bottom:10px">
                            <div class="cell-main">{{ \App\Support\AuditLabels::label($log->action) }}</div>
                            <div class="cell-sub">{{ $log->actor?->name ?? 'Système' }} · {{ $log->created_at?->diffForHumans() }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">Aucune modification récente.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </div>
@endsection
