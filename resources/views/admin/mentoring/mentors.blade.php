@extends('admin.layouts.app')

@section('title', 'Mentors')
@section('section', 'Mentors')

@section('content')
    <x-admin.page-header title="Mentors" subtitle="Le manager mentor reçoit, qualifie et affecte les tickets ; les mentors accompagnent les membres et rédigent le REX." />

    @error('member')<div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $message }}</span></div>@enderror
    @error('mentor')<div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $message }}</span></div>@enderror

    @if($managers === 0)
        <div class="alert alert-error" role="status"><x-admin.icon name="flag" /> <span>Aucun manager mentor actif : les nouveaux tickets ne sont reçus par personne.@if($canWrite) Ajoutez-en un ci-dessous.@endif</span></div>
    @endif

    @if($canWrite)
        <article class="card" style="margin-bottom:16px">
            <h2 style="margin-top:0">Ajouter un membre</h2>
            <form method="get" action="{{ route('admin.mentoring.mentors.index') }}" class="toolbar" style="padding:0">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" maxlength="80" placeholder="Nom du membre (2 lettres minimum)" aria-label="Rechercher un membre">
                </div>
                <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
            </form>

            @if($term !== '')
                <div class="list" style="margin-top:12px">
                    @forelse($found as $member)
                        <div class="list-item" style="flex-wrap:wrap">
                            <strong class="grow">{{ $member->name }}</strong>
                            <form method="post" action="{{ route('admin.mentoring.mentors.store') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                <input type="text" name="specialties" maxlength="255" placeholder="Spécialités (facultatif)" aria-label="Spécialités">
                                <label class="check"><input type="checkbox" name="manager" value="1"> Manager</label>
                                <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="14" /> Ajouter</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted" style="margin:8px 0 0">Aucun membre trouvé (ou déjà dans l’annuaire).</p>
                    @endforelse
                </div>
            @endif
        </article>
    @endif

    <article class="card card-flush">
        @if($rows->count())
            <div class="list">
                @foreach($rows as $row)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:220px">
                            <strong style="display:inline">{{ $row->user?->name ?? 'Membre supprimé' }}</strong>
                            @if($row->is_manager)<span class="badge badge-info">Manager mentor</span>@endif
                            @unless($row->is_active)<span class="badge badge-neutral">Inactif</span>@endunless
                            <div class="text-sm text-muted">{{ $workload[$row->user_id] ?? 0 }} ticket(s) en cours @if(filled($row->specialties)) · {{ $row->specialties }} @endif</div>
                        </div>
                        @if($canWrite)
                            <form method="post" action="{{ route('admin.mentoring.mentors.update', $row) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="specialties" value="{{ $row->specialties }}" maxlength="255" placeholder="Spécialités" aria-label="Spécialités">
                                <label class="check"><input type="checkbox" name="manager" value="1" @checked($row->is_manager)> Manager</label>
                                <label class="check"><input type="checkbox" name="active" value="1" @checked($row->is_active)> Actif</label>
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                            </form>
                            <x-admin.delete-button :action="route('admin.mentoring.mentors.destroy', $row)" message="Ce membre ne pourra plus être choisi comme mentor." title="Retirer de l’annuaire ?" />
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="users" title="Annuaire vide" text="Recherchez un membre pour le nommer mentor ou manager mentor." />
        @endif
    </article>
@endsection
