@extends('admin.layouts.app')

@section('title', 'Sponsors et partenaires')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Sponsors et partenaires" subtitle="Carnet d’adresses partagé de la Fondation et des clubs : les organisations avec lesquelles nous travaillons, leurs interlocuteurs et les pistes en cours. Consultez-le avant de démarcher quelqu’un, pour vous appuyer sur les contacts existants.">
        <x-slot:actions>
            <a href="{{ route('admin.sponsor-leads.index') }}" class="btn btn-secondary"><x-admin.icon name="flag" :size="16" /> Pistes de sponsoring</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid-3-1">
        <article class="card card-flush">
            <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                <div class="field" style="min-width:200px">
                    <label for="q">Recherche</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Nom, secteur, ville, interlocuteur…" maxlength="80">
                </div>
                <div class="field">
                    <label for="relation">Type</label>
                    <select id="relation" name="relation">
                        <option value="">Tous</option>
                        @foreach(\App\Models\Sponsor::RELATIONS as $key => $label)<option value="{{ $key }}" @selected($filters['relation'] === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="">Tous</option>
                        @foreach(\App\Models\Sponsor::STATUSES as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
            </form>

            @if($sponsors->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Organisation</th><th class="hide-mobile">Type</th><th>Statut</th><th class="hide-mobile">Contacts</th><th class="hide-mobile">Pistes en cours</th></tr></thead>
                        <tbody>
                            @foreach($sponsors as $sponsor)
                                <tr>
                                    <td>
                                        <a class="cell-main" href="{{ route('admin.sponsors.show', $sponsor) }}">{{ $sponsor->name }}</a>
                                        <div class="cell-sub">{{ collect([$sponsor->sector, $sponsor->city])->filter()->implode(' · ') ?: '—' }}@if($sponsor->ownerClub) · porté par {{ $sponsor->ownerClub->title }}@endif</div>
                                    </td>
                                    <td class="hide-mobile">{{ \App\Models\Sponsor::RELATIONS[$sponsor->relation] ?? $sponsor->relation }}</td>
                                    <td>{{ \App\Models\Sponsor::STATUSES[$sponsor->status] ?? $sponsor->status }}</td>
                                    <td class="hide-mobile">{{ $sponsor->contacts_count }}</td>
                                    <td class="hide-mobile">{{ $sponsor->open_leads_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body">{{ $sponsors->links() }}</div>
            @else
                <x-admin.empty icon="building" title="Aucun sponsor ni partenaire" text="Ajoutez la première organisation du carnet." />
            @endif
        </article>

        @if($canCreate)
            <article class="card" style="align-self:start">
                <div class="card-head"><div><h2>Ajouter au carnet</h2></div></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.sponsors.store') }}" style="display:grid;gap:12px">
                        @csrf
                        <x-admin.input name="name" label="Nom de l’organisation" :required="true" maxlength="190" />
                        <x-admin.select name="relation" label="Type" :options="\App\Models\Sponsor::RELATIONS" value="sponsor" :required="true" :placeholder="null" />
                        <x-admin.select name="status" label="Statut" :options="\App\Models\Sponsor::STATUSES" value="prospect" :required="true" :placeholder="null" />
                        <x-admin.input name="sector" label="Secteur d’activité" maxlength="120" />
                        <x-admin.input name="city" label="Ville" maxlength="120" />
                        <x-admin.input name="email" type="email" label="E-mail général" maxlength="190" />
                        <x-admin.input name="phone" label="Téléphone" maxlength="40" />
                        <x-admin.select name="owner_club_id" label="Club qui a l’entrée" :options="$clubOptions" placeholder="Aucun (Fondation)" />
                        <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
                    </form>
                </div>
            </article>
        @endif
    </div>
@endsection
