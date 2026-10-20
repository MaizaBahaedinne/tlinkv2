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
                <div class="card-head"><h2>Postes et droits</h2></div>
                @if($orgPositions->count())
                    <div class="list">
                        @foreach($orgPositions as $position)
                            <div class="list-item">
                                <div class="grow">
                                    <strong>{{ $position->node->title }}</strong>
                                    <span class="text-sm text-muted">{{ collect([$position->node->subtitle, $position->person->job_title])->filter()->implode(' · ') ?: '—' }}</span>
                                    <span class="text-sm">{{ \App\Support\AccessLevels::LEVELS[$position->node->access_level] ?? $position->node->access_level }}</span>
                                </div>
                                <a href="{{ route('orgchart.index', ['bloc' => $position->node_id]) }}" class="btn btn-secondary btn-icon btn-sm" title="Voir dans l'organigramme" aria-label="Voir dans l'organigramme"><x-admin.icon name="network" :size="15" /></a>
                            </div>
                        @endforeach
                    </div>
                    <p class="card-body text-sm text-muted">Les droits d’administration viennent uniquement des postes de l’organigramme. Pour les modifier, changez l’affectation ou le niveau d’accès du poste.</p>
                @else
                    <x-admin.empty icon="network" title="Aucun poste dans l'organigramme" text="Sans poste, l’utilisateur a les droits de membre de base, sans accès à l’administration." />
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
