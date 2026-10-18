@extends('admin.layouts.app')

@section('title', $user->name)
@section('section', 'Utilisateurs')

@section('content')
    <x-admin.page-header :title="$user->name" :subtitle="$user->email">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la liste</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid-3-1">
        <div style="display:grid;gap:22px;align-content:start;min-width:0">
            <article class="card card-flush">
                <div class="card-head"><h2>Rôles et permissions</h2></div>

                @if($assignments->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr><th>Rôle</th><th>Périmètre</th><th>Depuis</th><th>Statut</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach($assignments as $assignment)
                                    @php($revocable = $assignment->status === 'active' && $canManageRoles && $user->id !== auth()->id() && ($roleScope === [] || ($assignment->entity_id !== null && in_array((int) $assignment->entity_id, $roleScope, true))))
                                    <tr>
                                        <td class="cell-main">{{ $assignment->role?->name }}</td>
                                        <td>{{ $assignment->entity?->name ?? 'Global' }}</td>
                                        <td class="text-sm text-muted nowrap">{{ $assignment->starts_at?->format('d/m/Y') ?? $assignment->created_at?->format('d/m/Y') }}</td>
                                        <td><x-admin.badge :status="$assignment->status" /></td>
                                        <td class="cell-actions">
                                            @if($revocable)
                                                <form method="post" action="{{ route('admin.users.roles.destroy', [$user, $assignment]) }}" data-confirm="Ce rôle sera retiré à {{ $user->name }}." data-confirm-title="Retirer ce rôle ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">Retirer</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty icon="key" title="Aucun rôle" text="Cet utilisateur n’a aucune affectation de rôle." />
                @endif

                @if($canManageRoles)
                    <form method="post" action="{{ route('admin.users.roles.store', $user) }}" class="card-body" style="border-top:1px solid var(--line);background:#fbfcfe">
                        @csrf
                        <h3 style="font-size:14px;margin-bottom:12px">Attribuer un rôle</h3>
                        <div class="form-grid">
                            <x-admin.select name="role_id" label="Rôle" :options="$roleOptions" required />
                            <x-admin.select name="entity_id" label="Entité" :options="$entityOptions" :required="! $canAssignGlobal" :placeholder="$canAssignGlobal ? 'Global (toutes les entités)' : 'Sélectionner…'" />
                            <x-admin.input name="ends_at" type="date" label="Fin de l’affectation" hint="Optionnel. Laisser vide pour une durée illimitée." />
                        </div>
                        <div style="margin-top:16px;text-align:right">
                            <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Attribuer</button>
                        </div>
                    </form>
                @endif
            </article>

            <article class="card card-flush">
                <div class="card-head"><h2>Adhésions</h2></div>
                @if($memberships->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr><th>Entité</th><th>Département</th><th>Club</th><th>Type</th><th>Statut</th></tr>
                            </thead>
                            <tbody>
                                @foreach($memberships as $membership)
                                    <tr>
                                        <td class="cell-main">{{ $membership->entity?->name ?? '—' }}</td>
                                        <td>{{ $membership->department?->name ?? '—' }}</td>
                                        <td>{{ $membership->club?->name ?? '—' }}</td>
                                        <td class="text-sm">{{ $membership->membership_type }}</td>
                                        <td><x-admin.badge :status="$membership->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty icon="user-check" title="Aucune adhésion" />
                @endif
            </article>
        </div>

        <div style="display:grid;gap:22px;align-content:start;min-width:0">
            <article class="card card-flush">
                <div class="profile-head">
                    <x-admin.avatar :name="$user->name" :size="64" />
                    <div>
                        <h2>{{ $user->name }}</h2>
                        <p class="text-muted">{{ $user->profile?->headline ?: 'Aucun titre renseigné' }}</p>
                    </div>
                </div>
                <div class="card-body" style="border-top:1px solid var(--line)">
                    <dl class="kv">
                        <div><dt>E-mail</dt><dd style="word-break:break-all">{{ $user->email }}</dd></div>
                        <div><dt>Inscrit le</dt><dd>{{ $user->created_at?->format('d/m/Y') }}</dd></div>
                        <div><dt>E-mail vérifié</dt><dd>{{ $user->email_verified_at ? 'Oui' : 'Non' }}</dd></div>
                        <div><dt>Identifiant</dt><dd>#{{ $user->id }}</dd></div>
                    </dl>
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head"><h2>Dernières publications</h2></div>
                @if($recentPosts->count())
                    <div class="list">
                        @foreach($recentPosts as $post)
                            <div class="list-item">
                                <div class="grow">
                                    <strong>{{ \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 60) ?: '(sans texte)' }}</strong>
                                    <span class="text-sm text-muted">{{ $post->created_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty icon="file-text" title="Aucune publication" />
                @endif
            </article>
        </div>
    </div>
@endsection
