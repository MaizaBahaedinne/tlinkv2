@extends('admin.layouts.app')

@section('title', 'Clubs')
@section('section', 'Organisation')

@section('content')
    <x-admin.page-header title="Clubs" subtitle="Chaque club University / High School a la même équipe : un Président et 4 départements (VP, Assistant, membres). Elle est créée automatiquement.">
        <x-slot:actions>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary"><x-admin.icon name="shield" :size="16" /> Droits des clubs</a>
            @if($canEdit)
                <form method="post" action="{{ route('admin.clubs.align') }}" data-confirm="Les clubs University et High School qui n’ont pas encore l’équipe standard (ou dont elle est incomplète) seront complétés. Les personnes déjà affectées ne bougent pas." data-confirm-title="Compléter les équipes des clubs ?">
                    @csrf
                    <button type="submit" class="btn btn-secondary"><x-admin.icon name="layers" :size="16" /> Compléter les équipes</button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @php $inactiveCount = ($statusCounts['inactive'] ?? 0) + ($statusCounts['draft'] ?? 0) + ($statusCounts['archived'] ?? 0); @endphp
    <div class="chips" style="margin-bottom:14px">
        @foreach($statuses as $id => $name)
            <a href="{{ route('admin.clubs.index', ['status' => $id]) }}" class="chip">{{ $name }} : {{ $statusCounts[$id] ?? 0 }}</a>
        @endforeach
    </div>
    @if($inactiveCount > 0)
        <div class="alert alert-info" role="status">
            <x-admin.icon name="eye" />
            <span>Seuls les clubs <strong>Actifs</strong> apparaissent sur le site public, dans l’application et dans les statistiques. {{ $canEdit ? 'Cochez des clubs ci-dessous, ou tous ceux de la liste, pour changer leur statut.' : '' }}</span>
        </div>
    @endif
    @if($canEdit)
        <details class="card card-flush" style="margin-bottom:18px" @if($errors->has('name') || $errors->has('type') || $errors->has('governorate_id')) open @endif>
            <summary class="card-head" style="cursor:pointer">
                <div>
                    <h2>Créer un club</h2>
                    <p class="sub">Le club est rangé dans sa branche et reçoit l’équipe type.</p>
                </div>
            </summary>
            <form method="post" action="{{ route('admin.clubs.store') }}">
                @csrf
                <div class="card-body">
                    <div class="form-grid">
                        <x-admin.input name="name" label="Nom du club" required :maxlength="120" />
                        <x-admin.select name="type" label="Type" required :options="['university' => 'University club', 'highschool' => 'High School club', 'regional' => 'Club régional We Are']" placeholder="Choisir…" />
                        <x-admin.select name="governorate_id" label="Gouvernorat" :options="$governorates" placeholder="Non renseigné" hint="Obligatoire pour un club régional." />
                        <div class="field">
                            <label>&nbsp;</label>
                            <input type="hidden" name="apply_template" value="0">
                            <label class="check"><input type="checkbox" name="apply_template" value="1" checked> Appliquer l’équipe type</label>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Créer le club</button>
                </div>
            </form>
        </details>
    @endif

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.clubs.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom du club ou de l’établissement…">
                </div>
                <select name="category" aria-label="Type de club">
                    <option value="">Tous les types</option>
                    @foreach(['university_club' => 'University', 'highschool_club' => 'High School', 'regional_club' => 'We Are régionaux'] as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['category'] ?? '') === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach($statuses as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['status'] ?? '') === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="governorate_id" aria-label="Gouvernorat">
                    <option value="">Tous les gouvernorats</option>
                    @foreach($governorates as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['governorate_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.clubs.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($clubs->count())
            <form method="post" action="{{ route('admin.clubs.status') }}">
            @csrf
            @foreach(['q', 'category', 'governorate_id'] as $field)
                <input type="hidden" name="{{ $field }}" value="{{ $filters[$field] ?? '' }}">
            @endforeach
            <input type="hidden" name="filter_status" value="{{ $filters['status'] ?? '' }}">
            @if($canEdit)
                <div class="toolbar" style="border-bottom:1px solid var(--line)">
                    <span class="text-sm text-muted">Statut des clubs :</span>
                    <select name="status" aria-label="Statut à appliquer" required>
                        <option value="">Choisir…</option>
                        @foreach($statuses as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <label class="check"><input type="checkbox" name="all_filtered" value="1"> Tous les {{ $total }} clubs de la liste (pas seulement cette page)</label>
                    <button class="btn btn-secondary btn-sm" type="submit">Appliquer</button>
                    @error('nodes')<span class="text-sm" style="color:var(--danger)">{{ $message }}</span>@enderror
                    @error('status')<span class="text-sm" style="color:var(--danger)">{{ $message }}</span>@enderror
                </div>
            @endif
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            @if($canEdit)
                                <th style="width:36px"><input type="checkbox" data-check-all aria-label="Tout cocher"></th>
                            @endif
                            <th>Club</th>
                            <th class="hide-mobile">Gouvernorat</th>
                            <th>Statut</th>
                            <th>Président</th>
                            <th>Équipe</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $club = $row['club'];
                                $president = $row['president_id'] ? ($presidents[$row['president_id']] ?? collect())->first()?->person?->displayName() : null;
                                $percent = $row['positions'] > 0 ? (int) round($row['filled'] / $row['positions'] * 100) : 0;
                            @endphp
                            <tr>
                                @if($canEdit)
                                    <td><input type="checkbox" name="nodes[]" value="{{ $club->id }}" data-check-item aria-label="Sélectionner {{ $club->title }}"></td>
                                @endif
                                <td>
                                    <a href="{{ route('admin.clubs.show', $club) }}" class="cell-main">{{ $club->title }}</a>
                                    <div class="cell-sub">{{ \App\Models\OrgNode::CATEGORIES[$club->category] ?? $club->category }}</div>
                                </td>
                                <td class="hide-mobile">{{ $club->governorate?->name ?? '—' }}</td>
                                <td><x-admin.badge :status="$club->status ?? 'draft'" /></td>
                                <td>
                                    @if($president)
                                        {{ $president }}
                                    @elseif($row['president_id'])
                                        <span class="badge badge-warning">Vacant</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td style="min-width:150px">
                                    @if($row['positions'] > 0)
                                        <div class="text-sm">{{ $row['filled'] }} / {{ $row['positions'] }} postes pourvus · {{ $row['departments'] }} dép.</div>
                                        <div class="progress" style="margin-top:6px"><span style="width:{{ $percent }}%"></span></div>
                                    @else
                                        <span class="badge badge-neutral">Aucune équipe</span>
                                    @endif
                                </td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.clubs.show', $club) }}" class="btn btn-secondary btn-sm">Ouvrir</a>
                                    <a href="{{ route('admin.postes.edit', $club) }}" class="btn btn-ghost btn-sm">{{ $canEdit ? 'Modifier' : 'Fiche' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </form>
            {{ $clubs->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="building" title="Aucun club" text="Aucun club ne correspond à ces critères." />
        @endif
    </article>
@endsection
