@extends('admin.layouts.app')

@section('title', 'Membres de mon entité')
@section('section', 'Communauté')

@php
    $scopeNames = collect($scopes)->values()->implode(', ');
    $singleScope = count($scopes) === 1 ? array_key_first($scopes) : null;
    $query = array_filter(['q' => $filters['q'] ?? null, 'scope' => $filters['scope'] ?? null]);
@endphp

@section('content')
    <x-admin.page-header title="Membres de mon entité" :subtitle="$isGlobal ? 'Toutes les entités (administrateur global).' : 'Uniquement les membres de : '.$scopeNames.'.'" />

    @if($pending > 0)
        <div class="alert alert-info" role="status">
            <x-admin.icon name="user-check" />
            <span>{{ $pending }} demande{{ $pending > 1 ? 's' : '' }} d’adhésion en attente.@if($canReview) <a href="{{ route('admin.approvals.index') }}">Les traiter dans Approbations</a>@endif</span>
        </div>
    @endif

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.members.index', $query) }}" class="tab {{ empty($filters['status']) ? 'is-active' : '' }}">Tous <span class="n">{{ array_sum($counts) }}</span></a>
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.members.index', $query + ['status' => $code]) }}" class="tab {{ ($filters['status'] ?? '') === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
        </div>

        <div class="toolbar">
            <form method="get" action="{{ route('admin.members.index') }}">
                @if(! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un membre…">
                </div>
                @if(count($scopes) > 1)
                    <select name="scope" aria-label="Entité">
                        <option value="">Toutes les entités</option>
                        @foreach($scopes as $id => $name)
                            <option value="{{ $id }}" @selected((string) ($filters['scope'] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(! empty($filters['q']) || ! empty($filters['scope']))
                    <a href="{{ route('admin.members.index', array_filter(['status' => $filters['status'] ?? null])) }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($members->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Entité</th>
                            <th class="hide-mobile">Type</th>
                            <th class="hide-mobile">Depuis le</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $membership)
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <x-admin.avatar :name="$membership->user?->name" :size="34" />
                                        <div>
                                            @if($membership->user)
                                                <a href="{{ route('admin.users.show', $membership->user) }}" class="cell-main">{{ $membership->user->name }}</a>
                                            @else
                                                <div class="cell-main">Utilisateur supprimé</div>
                                            @endif
                                            <div class="cell-sub">{{ $membership->user?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ \App\Support\OrgScopes::label($membership->scope) ?? '—' }}</div>
                                    @if($membership->node)
                                        <div class="cell-sub">{{ $membership->node->title }}</div>
                                    @endif
                                </td>
                                <td class="hide-mobile text-sm">{{ $membership->membership_type }}</td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ ($membership->starts_at ?? $membership->created_at)?->format('d/m/Y') }}</td>
                                <td><x-admin.badge :status="$membership->status" /></td>
                                <td class="cell-actions">
                                    @if(isset($transitions[$membership->status]))
                                        <form method="post" action="{{ route('admin.members.update', $membership) }}" class="inline-form" style="justify-content:flex-end">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" aria-label="Nouveau statut">
                                                @foreach($transitions[$membership->status] as $target)
                                                    <option value="{{ $target }}">{{ ['approved' => 'Réactiver', 'suspended' => 'Suspendre', 'archived' => 'Retirer de l’entité'][$target] }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-secondary btn-sm">Appliquer</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $members->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user-check" title="Aucun membre" text="Aucun membre ne correspond à ces critères." />
        @endif

        {{-- Ajouter un membre --}}
        <div class="card-body" style="border-top:1px solid var(--line)">
            <h3 class="text-sm text-muted" style="margin-bottom:12px">Ajouter un membre{{ $singleScope ? ' à '.$scopes[$singleScope] : '' }}</h3>
            <form method="get" action="{{ route('admin.members.index') }}">
                @foreach($query + array_filter(['status' => $filters['status'] ?? null]) as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <div class="field">
                    <label for="f-u">Chercher un compte actif</label>
                    <div style="display:flex;gap:8px">
                        <input id="f-u" type="search" name="u" value="{{ $term }}" placeholder="Nom ou e-mail…" minlength="2">
                        <button type="submit" class="btn btn-secondary">Chercher</button>
                    </div>
                </div>
            </form>

            @if($term !== '')
                @forelse($candidates as $candidate)
                    <form method="post" action="{{ route('admin.members.store') }}" style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px">
                        @csrf
                        <div><div class="cell-main">{{ $candidate->name }}</div><div class="cell-sub">{{ str_ends_with((string) $candidate->email, '@legacy.invalid') ? '' : $candidate->email }}</div></div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="hidden" name="user_id" value="{{ $candidate->id }}">
                            @if($singleScope)
                                <input type="hidden" name="scope" value="{{ $singleScope }}">
                            @else
                                <select name="scope" aria-label="Entité">
                                    @foreach($scopes as $id => $name)
                                        <option value="{{ $id }}" @selected((string) ($filters['scope'] ?? '') === (string) $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
                        </div>
                    </form>
                @empty
                    <p class="text-sm text-muted" style="margin-top:10px">Aucun compte actif ne correspond.</p>
                @endforelse
            @endif
        </div>
    </article>
@endsection
