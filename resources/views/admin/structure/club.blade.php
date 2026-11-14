@extends('admin.layouts.app')

@section('title', $club->title)
@section('section', 'Organisation')

@php
    $total = $positions->count();
    $percent = $total > 0 ? (int) round($filled / $total * 100) : 0;
    $vacant = $total - $filled;
    $president = $positions->first(fn ($position) => $position->role_key === 'president' || str_starts_with($position->title, 'Président'));
    $presidentName = $president ? ($assignments[$president->id] ?? collect())->first()?->person?->displayName() : null;
    $mandateHint = $mandate ? 'Mandat '.$mandate->label : 'Aucun mandat en cours';
    $levelLabel = \App\Models\OrgNode::CATEGORIES[$club->category] ?? ($club->category ?: '—');
    $columnTotals = [];
    foreach ($matrix as $cells) {
        foreach ($cells as $mandateId => $count) {
            $columnTotals[$mandateId] = ($columnTotals[$mandateId] ?? 0) + $count;
        }
    }
@endphp

@section('content')
    <x-admin.page-header :title="$club->title" subtitle="Fiche du club">
        <x-slot:actions>
            <x-admin.badge :status="$club->status ?? 'draft'" />
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Clubs</a>
            <a href="{{ route('admin.postes.edit', $club) }}" class="btn btn-secondary"><x-admin.icon name="edit" :size="16" /> {{ $canEdit ? 'Modifier la fiche' : 'Fiche' }}</a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="stats" style="margin-bottom:22px">
        <x-admin.stat label="Postes pourvus" :value="$filled.' / '.$total" icon="users" tone="cyan" :hint="$vacant.' vacant(s)'" />
        <x-admin.stat label="Adhérents" :value="$currentMembers ?? '—'" icon="user" tone="green" :hint="$mandateHint" />
        <x-admin.stat label="Activités" :value="$mandateActivities ?? '—'" icon="calendar" tone="amber" :hint="$mandateHint" />
        <x-admin.stat label="Niveau du club" :value="$levelLabel" icon="award" tone="blue" />
    </section>

    <div class="grid-3-1" style="margin-bottom:22px">
        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>Organigramme du club</h2>
                    <p class="sub">Les postes suivent le modèle du type de club (Admin > Modèles de clubs).</p>
                </div>
            </div>
            <div class="card-body">
                @if($total > 0)
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

        <article class="card card-flush" style="align-self:start">
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
    </div>

    <article class="card card-flush" style="margin-bottom:22px">
        <div class="card-head">
            <div>
                <h2>Adhésions par mandat et par département</h2>
                <p class="sub">Adhésions approuvées du club ; le département est celui du poste visé ou occupé.</p>
            </div>
        </div>
        @if($mandates->isNotEmpty() && $departments !== [])
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Département</th>
                            @foreach($mandates as $item)
                                <th style="text-align:right">{{ $item->label }}@if($mandate && $item->id === $mandate->id) <span class="badge badge-info">En cours</span>@endif</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($departments as $key => $label)
                            <tr>
                                <td class="cell-main">{{ $label }}</td>
                                @foreach($mandates as $item)
                                    <td style="text-align:right">{{ $matrix[$key][$item->id] ?? 0 }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <td class="cell-main">Total</td>
                            @foreach($mandates as $item)
                                <td style="text-align:right;font-weight:700">{{ $columnTotals[$item->id] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="card-body"><p class="text-sm text-muted">Aucune adhésion à afficher : aucun mandat ou aucun département défini.</p></div>
        @endif
    </article>

    <div class="grid-2">
        @foreach([['Activités à venir', $upcomingActivities, 'Aucune activité à venir.'], ['Activités passées', $pastActivities, 'Aucune activité passée.']] as [$heading, $list, $empty])
            <article class="card card-flush">
                <div class="card-head"><div><h2>{{ $heading }}</h2></div></div>
                <div class="card-body">
                    @forelse($list as $activity)
                        <div style="margin-bottom:12px">
                            <a href="{{ route('admin.activities.show', $activity) }}" class="cell-main">{{ $activity->title }}</a>
                            <div class="cell-sub">
                                {{ $activity->starts_at?->translatedFormat('d M Y, H:i') }}
                                @if($activity->challenge) · {{ $activity->challenge->title }} @endif
                                @if($activity->status !== 'published') · {{ $activityStatuses[$activity->status] ?? $activity->status }} @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ $empty }}</p>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
@endsection
