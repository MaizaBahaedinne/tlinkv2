@extends('admin.layouts.app')

@section('title', 'Postes et droits')
@section('section', 'Organisation')

@section('content')
    <x-admin.page-header title="Postes et droits" subtitle="Chaque poste est un rôle : son niveau d’accès donne des droits sur T.Link, dans son périmètre.">
        <x-slot:actions>
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="building" :size="16" /> Clubs</a>
            <a href="{{ route('orgchart.index') }}" class="btn btn-secondary"><x-admin.icon name="network" :size="16" /> Organigramme</a>
        </x-slot:actions>
    </x-admin.page-header>

    <details class="card card-flush" style="margin-bottom:18px">
        <summary class="card-head" style="cursor:pointer">
            <div>
                <h2>Que donne chaque niveau d’accès ?</h2>
                <p class="sub">Les droits d’une personne sont ceux des postes qu’elle occupe, limités au périmètre du poste (propre ou hérité).</p>
            </div>
        </summary>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Niveau</th>
                        @foreach($permissions as $label)
                            <th style="text-align:center">{{ $label }}</th>
                        @endforeach
                        <th>Portée</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(\App\Support\AccessLevels::BADGES as $level => [$short, $tone])
                        <tr>
                            <td><span class="badge badge-{{ $tone }}">{{ $short }}</span></td>
                            @foreach($permissions as $slug => $label)
                                <td style="text-align:center">
                                    @if(in_array($slug, \App\Support\AccessLevels::permissions($level), true))
                                        <x-admin.icon name="check" :size="16" />
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-sm text-muted">
                                @if($level === 'none') Aucune
                                @elseif($level === 'global_admin') Toute la plateforme
                                @else Périmètre du poste
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.postes.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Poste, club ou titulaire…">
                </div>
                <select name="kind" aria-label="Type">
                    <option value="">Tous les types</option>
                    @foreach($kinds as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['kind'] ?? '') === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="category" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['category'] ?? '') === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="scope" aria-label="Périmètre">
                    <option value="">Tous les périmètres</option>
                    @foreach($scopes as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['scope'] ?? '') === $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="access" aria-label="Niveau d'accès">
                    <option value="">Tous les niveaux</option>
                    @foreach($levels as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['access'] ?? '') === $id)>{{ \App\Support\AccessLevels::BADGES[$id][0] }}</option>
                    @endforeach
                </select>
                <select name="holder" aria-label="Titulaire">
                    <option value="">Pourvus et vacants</option>
                    <option value="filled" @selected(($filters['holder'] ?? '') === 'filled')>Pourvus</option>
                    <option value="vacant" @selected(($filters['holder'] ?? '') === 'vacant')>Vacants</option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                <a href="{{ route('admin.postes.index', ['kind' => '']) }}" class="btn btn-ghost btn-sm">Tout afficher</a>
            </form>
        </div>

        <form method="post" action="{{ route('admin.postes.access') }}">
            @csrf
            @if($canEdit)
                <div class="toolbar" style="border-bottom:1px solid var(--line)">
                    <span class="text-sm text-muted">Pour les postes cochés :</span>
                    <select name="access_level" aria-label="Niveau d'accès à appliquer" required>
                        <option value="">Choisir un niveau d’accès…</option>
                        @foreach($levels as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-secondary btn-sm" type="submit">Appliquer</button>
                    @error('nodes')<span class="text-sm" style="color:var(--danger)">{{ $message }}</span>@enderror
                </div>
            @endif

            @if($nodes->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                @if($canEdit)
                                    <th style="width:36px"><input type="checkbox" data-check-all aria-label="Tout cocher"></th>
                                @endif
                                <th>Poste</th>
                                <th class="hide-mobile">Type</th>
                                <th class="hide-mobile">Périmètre</th>
                                <th>Accès</th>
                                <th>Titulaires</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($nodes as $node)
                                @php
                                    $isPosition = $node->kind === 'position';
                                    $effective = $scopeMap[$node->id] ?? null;
                                    [$short, $tone] = \App\Support\AccessLevels::BADGES[$node->access_level] ?? \App\Support\AccessLevels::BADGES['none'];
                                @endphp
                                <tr>
                                    @if($canEdit)
                                        <td><input type="checkbox" name="nodes[]" value="{{ $node->id }}" data-check-item @disabled(! $isPosition) aria-label="Sélectionner {{ $node->title }}"></td>
                                    @endif
                                    <td>
                                        <a href="{{ route('admin.postes.edit', $node) }}" class="cell-main">{{ $node->title }}</a>
                                        <div class="cell-sub">
                                            {{ collect([$crumbs[$node->id]['club'] ?? null, $crumbs[$node->id]['parent'] ?? null])->filter()->unique()->implode(' › ') }}
                                        </div>
                                    </td>
                                    <td class="hide-mobile"><span class="chip">{{ $kinds[$node->kind] ?? $node->kind }}</span></td>
                                    <td class="hide-mobile text-sm">
                                        {{ \App\Support\OrgScopes::label($effective) ?: '—' }}
                                        @if($effective && $node->scope === null)<span class="text-muted">(hérité)</span>@endif
                                    </td>
                                    <td>
                                        @if($isPosition)
                                            <span class="badge badge-{{ $tone }}">{{ $short }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isPosition)
                                            @forelse($node->assignments as $assignment)
                                                <span class="chip">{{ $assignment->person?->displayName() }}</span>
                                            @empty
                                                <span class="badge badge-warning">Vacant</span>
                                            @endforelse
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="cell-actions">
                                        <a href="{{ route('admin.postes.edit', $node) }}" class="btn btn-secondary btn-sm">{{ $canEdit ? 'Modifier' : 'Voir' }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $nodes->links('admin.partials.pagination') }}
            @else
                <x-admin.empty icon="key" title="Aucun bloc" text="Aucun bloc ne correspond à ces critères." />
            @endif
        </form>
    </article>
@endsection
