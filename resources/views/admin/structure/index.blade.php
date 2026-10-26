@extends('admin.layouts.app')

@section('title', 'Postes et droits')
@section('section', 'Organisation')

@section('content')
    <x-admin.page-header title="Postes et droits" subtitle="Un poste de l’organigramme est un rôle : ses droits se règlent module par module dans la fiche du poste (matrice C R U D) ; ce tableau les résume et règle le périmètre sur lequel chaque poste agit.">
        <x-slot:actions>
            <a href="{{ route('admin.scopes.edit') }}" class="btn btn-secondary"><x-admin.icon name="layers" :size="16" /> Périmètres</a>
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="building" :size="16" /> Clubs</a>
            <a href="{{ route('orgchart.index') }}" class="btn btn-secondary"><x-admin.icon name="network" :size="16" /> Organigramme</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <details class="card card-flush" style="margin-bottom:18px">
        <summary class="card-head" style="cursor:pointer">
            <div>
                <h2>Comment ça marche ?</h2>
                <p class="sub">Les droits d’une personne sont ceux des postes qu’elle occupe, limités au périmètre de chaque poste.</p>
            </div>
        </summary>
        <div class="card-body">
            <ul style="margin:0;padding-left:18px;display:grid;gap:6px">
                @foreach($permissions as $slug => $label)
                    <li><strong>{{ \App\Support\AccessLevels::SHORT[$slug] }}</strong> : {{ $label }}.</li>
                @endforeach
                <li><strong>Périmètre</strong> : le poste agit dans ce périmètre et dans ses sous-périmètres. « Hérité » reprend le périmètre du bloc parent. Sans périmètre, un poste ne donne aucun droit.</li>
                <li><strong>Plateforme entière</strong> : le poste agit partout, sans limite de périmètre (réservé aux administrateurs).</li>
                <li>Les postes de l’équipe standard des clubs se règlent ensemble dans <a href="{{ route('admin.roles.index') }}">Droits des clubs</a>.</li>
            </ul>
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
                <select name="permission" aria-label="Droit">
                    <option value="">Tous les droits</option>
                    @foreach($permissions as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['permission'] ?? '') === $id)>{{ \App\Support\AccessLevels::SHORT[$id] }}</option>
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

        @if($nodes->count())
            <form method="post" action="{{ route('admin.postes.permissions') }}">
                @csrf

                @if($canEdit)
                    <div class="toolbar" style="border-bottom:1px solid var(--line)">
                        <span class="text-sm text-muted">Choisissez le périmètre de chaque poste, puis enregistrez (postes de cette page). Les droits (colonnes) se modifient dans la fiche du poste.</span>
                        <button class="btn btn-primary btn-sm" type="submit"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
                    </div>
                @endif

                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Poste</th>
                                <th>Périmètre</th>
                                @foreach($permissions as $slug => $label)
                                    <th style="text-align:center" title="{{ $label }}">{{ \App\Support\AccessLevels::SHORT[$slug] }}</th>
                                @endforeach
                                <th style="text-align:center" title="Agit partout, sans limite de périmètre">Plateforme entière</th>
                                <th>Titulaires</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($nodes as $node)
                                @php
                                    $isPosition = $node->kind === 'position';
                                    $locked = $isPosition && $node->role_key !== null;
                                    $editable = $canEdit && $isPosition && ! $locked;
                                    $rights = $isPosition ? \App\Support\AccessLevels::forPosition((string) $node->access_level, $node->permissions) : [];
                                    $everywhere = $isPosition && $rights !== [] && \App\Support\AccessLevels::reachesEverywhere((string) $node->access_level, $node->permissions);
                                    $effective = $scopeMap[$node->id] ?? null;
                                    $inherited = $node->parent_id ? ($scopeMap[$node->parent_id] ?? null) : null;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.postes.edit', $node) }}" class="cell-main">{{ $node->title }}</a>
                                        <div class="cell-sub">
                                            <span class="chip">{{ $kinds[$node->kind] ?? $node->kind }}</span>
                                            {{ collect([$crumbs[$node->id]['club'] ?? null, $crumbs[$node->id]['parent'] ?? null])->filter()->unique()->implode(' › ') }}
                                        </div>
                                    </td>

                                    @if(! $isPosition)
                                        <td class="text-sm">{{ \App\Support\OrgScopes::label($effective) ?: '—' }}</td>
                                        <td colspan="{{ count($permissions) + 1 }}" class="text-sm text-muted">Seuls les postes portent des droits.</td>
                                        <td><span class="text-muted">—</span></td>
                                    @else
                                        <td>
                                            @if($editable)
                                                <select name="rows[{{ $node->id }}][scope]" aria-label="Périmètre de {{ $node->title }}" style="min-width:150px">
                                                    <option value="">Hérité{{ $inherited ? ' : '.\App\Support\OrgScopes::label($inherited) : ' (aucun)' }}</option>
                                                    @foreach($scopes as $id => $name)
                                                        <option value="{{ $id }}" @selected($node->scope === $id)>{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-sm">{{ \App\Support\OrgScopes::label($effective) ?: '—' }}</span>
                                                @if($effective && $node->scope === null)<span class="text-muted text-sm">(hérité)</span>@endif
                                            @endif
                                            @if(! empty($node->extra_scopes))
                                                <div class="text-muted text-sm" style="margin-top:4px">+ {{ collect($node->extra_scopes)->map(fn ($slug) => \App\Support\OrgScopes::label($slug))->implode(', ') }}</div>
                                            @endif
                                        </td>

                                        @foreach($permissions as $slug => $label)
                                            <td style="text-align:center">
                                                @if(in_array($slug, $rights, true))
                                                    <x-admin.icon name="check" :size="16" />
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td style="text-align:center">
                                            @if($editable)
                                                <input type="hidden" name="rows[{{ $node->id }}][scope_all]" value="0">
                                                <input type="checkbox" name="rows[{{ $node->id }}][scope_all]" value="1" @checked($everywhere) aria-label="Plateforme entière : {{ $node->title }}">
                                            @elseif($everywhere)
                                                <x-admin.icon name="check" :size="16" />
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>

                                        <td>
                                            @forelse($node->assignments as $assignment)
                                                <span class="chip">{{ $assignment->person?->displayName() }}</span>
                                            @empty
                                                <span class="badge badge-warning">Vacant</span>
                                            @endforelse
                                        </td>
                                    @endif

                                    <td class="cell-actions">
                                        @if($locked)
                                            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost btn-sm" title="Droits communs à tous les clubs">Droits des clubs</a>
                                        @endif
                                        <a href="{{ route('admin.postes.edit', $node) }}" class="btn btn-secondary btn-sm">{{ $canEdit ? 'Modifier' : 'Voir' }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            {{ $nodes->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="key" title="Aucun bloc" text="Aucun bloc ne correspond à ces critères." />
        @endif
    </article>
@endsection
