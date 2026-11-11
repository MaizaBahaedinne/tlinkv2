@extends('admin.layouts.app')

@section('title', 'Adhésions')
@section('section', 'Adhésions')

@section('content')
    <x-admin.page-header title="Adhésions" :subtitle="($globalView ? 'Toutes les adhésions de la plateforme' : 'Les adhésions et demandes sous votre responsabilité') . ($mandate ? ' — mandat '.$mandate->label : ' — tous les mandats').'. Les titulaires de postes de l’organigramme sont des adhérents validés de leur mandat. Les demandes en attente sont affichées en tête : acceptez-les ou refusez-les directement ici.'" />

    <article class="card card-flush">
        <div class="tabs">
            @php
                $query = array_filter(['q' => $filters['q'] ?? null, 'scope' => $filters['scope'] ?? null, 'mandate' => $filters['mandate']]);
            @endphp
            <a href="{{ route('admin.memberships.index', $query) }}" class="tab {{ empty($filters['status']) ? 'is-active' : '' }}">Toutes <span class="n">{{ array_sum($counts) }}</span></a>
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.memberships.index', $query + ['status' => $code]) }}" class="tab {{ ($filters['status'] ?? '') === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
        </div>

        <div class="toolbar">
            <form method="get" action="{{ route('admin.memberships.index') }}">
                @if(! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un membre…" style="padding-left:40px">
                </div>
                <select name="mandate" aria-label="Mandat">
                    <option value="all" @selected($filters['mandate'] === 'all')>Tous les mandats</option>
                    @foreach($mandates as $item)
                        <option value="{{ $item->id }}" @selected($filters['mandate'] === (string) $item->id)>{{ $item->label }} ({{ \App\Models\Mandate::STATUSES[$item->status] ?? $item->status }})</option>
                    @endforeach
                </select>
                <select name="scope" aria-label="Périmètre">
                    <option value="">Toutes les entités</option>
                    @foreach($scopes as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['scope'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(! empty($filters['q']) || ! empty($filters['scope']))
                    <a href="{{ route('admin.memberships.index', array_filter(['status' => $filters['status'] ?? null, 'mandate' => $filters['mandate']])) }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($memberships->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Rattachement</th>
                            <th>Mandat</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($memberships as $membership)
                            <tr>
                                <td style="min-width:240px">
                                    <div class="cell-user">
                                        <x-admin.avatar :name="$membership->user?->name" :size="38" />
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
                                <td style="min-width:200px">
                                    <div class="cell-main">{{ $membership->node?->title ?? (\App\Support\OrgScopes::label($membership->scope) ?: '—') }}</div>
                                    <div class="cell-sub">
                                        {{ \App\Support\OrgScopes::label($membership->scope) ?: '—' }}
                                        @if($membership->position)
                                            · <strong>{{ $membership->position->title }}</strong>
                                        @elseif($membership->membership_type && $membership->membership_type !== 'member')
                                            · {{ $membership->membership_type }}
                                        @endif
                                    </div>
                                </td>
                                <td class="nowrap">
                                    <div class="cell-main">{{ $mandateLabels[$membership->id] ?? '—' }}</div>
                                    <div class="cell-sub">{{ $membership->status === 'pending' ? 'Demandée' : 'Depuis' }} le {{ ($membership->status === 'pending' ? $membership->created_at : ($membership->starts_at ?? $membership->created_at))?->format('d/m/Y') }}</div>
                                </td>
                                <td class="nowrap">
                                    <x-admin.badge :status="$membership->status" />
                                    @if($membership->status === 'pending')
                                        @php $reviewer = ($approvals[$membership->id] ?? null)?->reviewer; @endphp
                                        <div class="cell-sub">{{ $reviewer ? 'Validateur : '.$reviewer->name : 'Sans validateur assigné' }}@if(! ($decidable[$membership->id] ?? false)) · vous ne pouvez pas statuer @endif</div>
                                    @endif
                                    @if($membership->status === 'rejected' && $membership->rejection_reason)
                                        <div class="cell-sub">{{ \Illuminate\Support\Str::limit($membership->rejection_reason, 60) }}</div>
                                    @endif
                                </td>
                                <td class="cell-actions">
                                    @if(isset($transitions[$membership->status]))
                                        @foreach($transitions[$membership->status] as $target)
                                            <form method="post" action="{{ route('admin.memberships.update', $membership) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $target }}">
                                                <button type="submit" class="btn {{ $target === 'approved' ? 'btn-secondary' : 'btn-ghost' }} btn-sm">{{ ['approved' => 'Réactiver', 'suspended' => 'Suspendre', 'archived' => 'Archiver'][$target] }}</button>
                                            </form>
                                        @endforeach
                                    @elseif($membership->status === 'pending' && $canReview && ($decidable[$membership->id] ?? false))
                                        <form method="post" action="{{ route('admin.memberships.decide', $membership) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="decision" value="approved">
                                            <button type="submit" class="btn btn-primary btn-sm">Accepter</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.memberships.decide', $membership) }}" onsubmit="var r = prompt('Motif du refus (facultatif)'); if (r === null) { return false; } this.explanation.value = r;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="decision" value="rejected">
                                            <input type="hidden" name="explanation" value="">
                                            <button type="submit" class="btn btn-ghost btn-sm">Refuser</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $memberships->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user-check" title="Aucune adhésion" text="Aucune adhésion sous votre responsabilité ne correspond à ces critères." />
        @endif
    </article>

    @if($participations->isNotEmpty())
        <article class="card card-flush" style="margin-top:1rem">
            <div class="card-head">
                <h2 class="card-title">Participations aux mandats précédents</h2>
                @if($canReview)
                    <a href="{{ route('admin.approvals.index') }}" class="btn btn-ghost btn-sm">Approbations</a>
                @endif
            </div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Mandat</th>
                            <th>Rattachement</th>
                            <th>Poste</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($participations as $participation)
                            <tr>
                                <td>
                                    @if($participation->user)
                                        <a href="{{ route('admin.users.show', $participation->user) }}" class="cell-main">{{ $participation->user->name }}</a>
                                    @else
                                        <div class="cell-main">Utilisateur supprimé</div>
                                    @endif
                                </td>
                                <td class="text-sm">{{ $participation->mandate?->label ?? '—' }}</td>
                                <td>
                                    <div>{{ \App\Support\OrgScopes::label($participation->scope) ?? '—' }}</div>
                                    @if($participation->node)
                                        <div class="cell-sub">{{ $participation->node->title }}</div>
                                    @endif
                                </td>
                                <td class="text-sm">{{ $participation->position_title }}</td>
                                <td><x-admin.badge :status="$participation->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endif
@endsection
