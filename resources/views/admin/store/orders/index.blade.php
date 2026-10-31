@extends('admin.layouts.app')

@section('title', 'Commandes boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Commandes" subtitle="Confirmation, préparation, expédition, paiement et annulation des commandes de la boutique." />

    @include('admin.store._tabs')

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.store.orders.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Référence, client, téléphone…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts ({{ $pending }} en attente)</option>
                    @foreach(\App\Models\StoreOrder::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="payment" aria-label="Paiement">
                    <option value="">Tous les paiements</option>
                    @foreach(\App\Models\StoreOrder::PAYMENT_STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($payment === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $status !== '' || $payment !== '')
                    <a href="{{ route('admin.store.orders.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($orders->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Commande</th>
                            <th>Client</th>
                            <th>Livraison</th>
                            <th class="num">Total</th>
                            <th>Statut</th>
                            <th>Paiement</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.store.orders.show', $order) }}" class="cell-main link-strong">{{ $order->reference }}</a>
                                    <div class="cell-sub">{{ $order->created_at->translatedFormat('d M Y H:i') }}</div>
                                </td>
                                <td>
                                    <div class="cell-main">{{ $order->ship_name }}</div>
                                    <div class="cell-sub">{{ $order->user?->email ?? 'Compte supprimé' }}</div>
                                </td>
                                <td class="text-sm">{{ $order->delivery_name }}</td>
                                <td class="num cell-main">{{ $order->totalLabel() }}</td>
                                <td><span class="badge badge-{{ $order->statusTone() }}">{{ $order->statusLabel() }}</span></td>
                                <td><span class="badge badge-{{ $order->paymentTone() }}">{{ $order->paymentStatusLabel() }}</span></td>
                                <td class="cell-actions"><a href="{{ route('admin.store.orders.show', $order) }}" class="btn btn-secondary btn-sm">Ouvrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="shopping-bag" title="Aucune commande" text="Les commandes apparaissent ici dès qu’un membre valide son panier." />
        @endif
    </article>
@endsection
