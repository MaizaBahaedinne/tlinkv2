@extends('admin.layouts.app')

@section('title', 'Locaux')
@section('section', 'Cartes et pointage')

@section('content')
    <x-admin.page-header title="Locaux" subtitle="Siège, salles, espaces partagés : les lieux où les membres pointent leur arrivée et leur départ.">
        <x-slot:actions>
            <a href="{{ route('admin.checkpoint.index') }}" class="btn btn-secondary"><x-admin.icon name="eye" :size="16" /> Pointage</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid-3-1">
        <article class="card card-flush">
            @if($premises->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Local</th><th class="hide-mobile">Entité</th><th>Statut</th><th></th></tr></thead>
                        <tbody>
                            @foreach($premises as $place)
                                <tr>
                                    <td><span class="cell-main">{{ $place->name }}</span><div class="cell-sub">{{ $place->address ?? 'Adresse non précisée' }}</div></td>
                                    <td class="hide-mobile">{{ $place->node?->title ?? (\App\Support\OrgScopes::label($place->scope) ?: 'Toute la Fondation') }}</td>
                                    <td><x-admin.badge :status="$place->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="cell-actions">
                                        <form method="post" action="{{ route('admin.premises.update', $place) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="is_active" value="{{ $place->is_active ? 0 : 1 }}">
                                            <button class="btn btn-secondary btn-sm" type="submit">{{ $place->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                        </form>
                                        <x-admin.delete-button :action="route('admin.premises.destroy', $place)" :message="'« '.$place->name.' » sera supprimé ; l’historique des pointages est conservé.'" title="Supprimer ce local ?" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-admin.empty icon="building" title="Aucun local" text="Ajoutez un premier local pour pouvoir y pointer." />
            @endif
        </article>

        <article class="card" style="align-self:start">
            <div class="card-head"><div><h2>Ajouter un local</h2></div></div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.premises.store') }}" style="display:grid;gap:14px">
                    @csrf
                    <div class="field @error('name') has-error @enderror">
                        <label for="p-name">Nom <span class="req">*</span></label>
                        <input id="p-name" type="text" name="name" value="{{ old('name') }}" maxlength="150" required>
                        @error('name')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field @error('address') has-error @enderror">
                        <label for="p-address">Adresse</label>
                        <input id="p-address" type="text" name="address" value="{{ old('address') }}" maxlength="255">
                        @error('address')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    @if($scopes !== [])
                        <div class="field @error('scope') has-error @enderror">
                            <label for="p-scope">Entité @unless($canBeGlobal)<span class="req">*</span>@endunless</label>
                            <select id="p-scope" name="scope" @required(! $canBeGlobal)>
                                @if($canBeGlobal)<option value="">Toute la Fondation</option>@endif
                                @foreach($scopes as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('scope') === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('scope')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <button class="btn btn-primary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
                </form>
            </div>
        </article>
    </div>
@endsection
