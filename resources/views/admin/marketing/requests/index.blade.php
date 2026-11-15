@extends('admin.layouts.app')

@section('title', 'Demandes d’accès aux réseaux')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Demandes d’accès aux réseaux" subtitle="Demandez l’accès aux pages Facebook et Instagram de votre entité, pour vous ou pour un autre membre. Le digital manager de la Fondation valide ou refuse la demande.">
        <x-slot:actions>
            @if($canCreate)
                <a href="{{ route('admin.marketing.requests.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle demande</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field">
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <option value="">Tous</option>
                    @foreach(\App\Models\MarketingAccessRequest::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
        </form>

        @if($requests->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Entité</th><th>Bénéficiaire</th><th class="hide-mobile">Pages</th><th class="hide-mobile">Demandeur</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach($requests as $item)
                            <tr>
                                <td><span class="cell-main">{{ $item->entityLabel() }}</span><div class="cell-sub">{{ $item->created_at->format('d/m/Y') }}</div></td>
                                <td>{{ $item->beneficiary?->name }}</td>
                                <td class="hide-mobile">{{ $item->platformLabels() }}</td>
                                <td class="hide-mobile">{{ $item->requester?->name }}</td>
                                <td><span class="badge badge-{{ $item->tone() }}">{{ $item->statusLabel() }}</span></td>
                                <td class="cell-actions"><a href="{{ route('admin.marketing.requests.show', $item) }}" class="btn btn-secondary btn-sm">Voir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $requests->links() }}</div>
        @else
            <x-admin.empty icon="users" title="Aucune demande" text="Les demandes d’accès de votre entité apparaîtront ici." />
        @endif
    </article>
@endsection
