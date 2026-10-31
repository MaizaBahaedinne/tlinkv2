@extends('admin.layouts.app')

@section('title', 'Retours boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Retours" subtitle="Demandes de retour des membres : acceptation, réception des articles, remboursement ou échange." />

    @include('admin.store._tabs')

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.store.returns.index') }}" class="tab {{ $status === '' ? 'is-active' : '' }}">Tous</a>
            @foreach(\App\Models\StoreReturn::STATUSES as $code => $label)
                <a href="{{ route('admin.store.returns.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }}@if($code === 'requested' && $requested > 0) <span class="n">{{ $requested }}</span>@endif</a>
            @endforeach
        </div>

        @if($returns->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Retour</th>
                            <th>Commande</th>
                            <th>Client</th>
                            <th>Motif</th>
                            <th>Souhait</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returns as $return)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.store.returns.show', $return) }}" class="cell-main link-strong">{{ $return->reference }}</a>
                                    <div class="cell-sub">{{ $return->created_at->translatedFormat('d M Y') }}</div>
                                </td>
                                <td>
                                    @if($return->order)<a href="{{ route('admin.store.orders.show', $return->order) }}" class="link-strong">{{ $return->order->reference }}</a>@else—@endif
                                </td>
                                <td>{{ $return->user?->name ?? 'Compte supprimé' }}</td>
                                <td><div class="truncate-2 text-sm">{{ $return->reason }}</div></td>
                                <td>{{ $return->resolutionLabel() }}</td>
                                <td><span class="badge badge-{{ $return->statusTone() }}">{{ $return->statusLabel() }}</span></td>
                                <td class="cell-actions"><a href="{{ route('admin.store.returns.show', $return) }}" class="btn btn-secondary btn-sm">Ouvrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $returns->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="inbox" title="Aucun retour" text="Les demandes de retour des membres apparaissent ici." />
        @endif
    </article>
@endsection
