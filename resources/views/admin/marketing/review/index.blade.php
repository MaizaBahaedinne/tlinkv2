@extends('admin.layouts.app')

@section('title', 'Validation des accès')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header title="Validation des accès aux réseaux" subtitle="Le digital manager de la Fondation valide ou refuse les demandes. Après validation, le membre signe la charte d’usage des réseaux sociaux, puis reçoit les accès et un portfolio Meta.">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.charters.index') }}" class="btn btn-secondary"><x-admin.icon name="file-text" :size="16" /> Charte d’usage</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field">
                <label for="status">Statut</label>
                <select id="status" name="status">
                    <option value="" @selected($status === null)>Tous</option>
                    @foreach(\App\Models\MarketingAccessRequest::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
            @if($pending > 0)<span class="badge badge-warning">{{ $pending }} en attente</span>@endif
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
                                <td class="cell-actions"><a href="{{ route('admin.marketing.review.show', $item) }}" class="btn btn-secondary btn-sm">Examiner</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $requests->links() }}</div>
        @else
            <x-admin.empty icon="shield" title="Aucune demande" text="Aucune demande ne correspond à ce filtre." />
        @endif
    </article>
@endsection
