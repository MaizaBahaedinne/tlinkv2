@extends('admin.layouts.app')

@section('title', 'Commandes boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Commandes" subtitle="Commandes de la boutique : confirmation, préparation, expédition, paiement et annulation." />

    @error('order')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

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
            <div class="list">
                @foreach($orders as $order)
                    <div class="list-item">
                        <div class="grow">
                            <a href="{{ route('admin.store.orders.show', $order) }}"><strong style="display:inline">{{ $order->reference }}</strong></a>
                            <x-admin.badge :status="$order->status" :label="$order->statusLabel()" />
                            <x-admin.badge :status="$order->payment_status === 'paid' ? 'approved' : ($order->payment_status === 'unpaid' ? 'pending' : 'rejected')" :label="$order->paymentStatusLabel()" />
                            <div class="text-sm text-muted">
                                {{ $order->totalLabel() }} · {{ $order->ship_name }} ({{ $order->user?->email ?? 'compte supprimé' }}) · {{ $order->delivery_name }} · {{ $order->created_at->translatedFormat('d M Y H:i') }}
                            </div>
                        </div>
                        <a href="{{ route('admin.store.orders.show', $order) }}" class="btn btn-secondary btn-sm">Ouvrir</a>
                    </div>
                @endforeach
            </div>
            {{ $orders->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="shopping-bag" title="Aucune commande" text="Les commandes apparaissent ici dès qu’un membre valide son panier." />
        @endif
    </article>
@endsection
