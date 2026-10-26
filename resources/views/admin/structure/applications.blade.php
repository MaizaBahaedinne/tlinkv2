@extends('admin.layouts.app')

@section('title', 'Candidatures aux postes')
@section('section', 'Candidatures aux postes')

@section('content')
    <x-admin.page-header title="Candidatures aux postes" subtitle="Personnes inscrites via le lien d’inscription d’un poste. Accepter une candidature affecte la personne au poste et lui donne les droits de ce poste." />

    <article class="card card-flush">
        <div class="tabs">
            @foreach($statuses as $code => $label)
                <a href="{{ route('admin.candidatures.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }} <span class="n">{{ $counts[$code] ?? 0 }}</span></a>
            @endforeach
            <a href="{{ route('admin.candidatures.index', ['status' => 'all']) }}" class="tab {{ $status === 'all' ? 'is-active' : '' }}">Toutes <span class="n">{{ $counts->sum() }}</span></a>
        </div>

        @if($applications->count())
            <div class="list">
                @foreach($applications as $application)
                    @php($profile = $application->user?->profile)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <x-admin.avatar :name="$application->user?->name" :size="48" :src="$profile?->photoUrl()" />
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:10px;align-items:center;margin-bottom:4px;flex-wrap:wrap">
                                <strong style="display:inline">{{ $application->user?->name ?? 'Compte supprimé' }}</strong>
                                <x-admin.badge :status="$application->status" :label="$statuses[$application->status] ?? null" />
                                <span class="text-sm text-muted">{{ $application->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="text-sm">
                                Poste : <strong>{{ $application->node?->title ?? '—' }}</strong>
                                @if($application->node && $application->node->capacity !== 'multiple')
                                    <span class="text-muted">(titulaire unique)</span>
                                @endif
                            </div>
                            <div class="text-sm text-muted">
                                {{ $application->user?->email }}@if($profile?->phone) · {{ $profile->phone }}@endif
                                @if($application->user) · <a href="{{ route('admin.users.show', $application->user) }}">Voir le profil complet</a>@endif
                            </div>
                            @if($application->message)
                                <p class="text-sm" style="margin:8px 0 0;white-space:pre-line">{{ $application->message }}</p>
                            @endif
                            @if($application->decided_at)
                                <div class="text-sm text-muted" style="margin-top:6px">
                                    Décidée le {{ $application->decided_at->format('d/m/Y H:i') }}@if($application->decider) par {{ $application->decider->name }}@endif
                                    @if($application->decision_note) — {{ $application->decision_note }}@endif
                                </div>
                            @endif
                        </div>

                        @if($application->status === 'pending')
                            @if($canDecide)
                                <form method="post" action="{{ route('admin.candidatures.decide', $application) }}" class="inline-form" style="flex-basis:100%;margin-top:8px">
                                    @csrf
                                    <input type="text" name="note" maxlength="1000" placeholder="Commentaire (optionnel, transmis au candidat)" style="flex:1;min-width:220px">
                                    <button type="submit" name="decision" value="approved" class="btn btn-success btn-sm"><x-admin.icon name="check" :size="15" /> Accepter</button>
                                    <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm"><x-admin.icon name="x" :size="15" /> Refuser</button>
                                </form>
                            @else
                                <span class="text-sm text-muted">Seuls les administrateurs globaux peuvent décider.</span>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
            {{ $applications->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user-check" title="Aucune candidature" text="Aucune candidature dans cette catégorie. Le lien d’inscription de chaque poste se trouve dans l’organigramme." />
        @endif
    </article>
@endsection
