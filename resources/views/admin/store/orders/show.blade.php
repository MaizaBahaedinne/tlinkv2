@extends('admin.layouts.app')

@section('title', 'Commande '.$order->reference)
@section('section', 'Tunivisions Stores')

@php
    $pickup = $order->isPickup();
    $flow = [
        'pending' => 'Reçue',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'shipped' => $pickup ? 'Prête au retrait' : 'Expédiée',
        'delivered' => $pickup ? 'Retirée' : 'Livrée',
    ];
    $position = array_search($order->status, array_keys($flow), true);
@endphp

@section('content')
    <x-admin.page-header :title="'Commande '.$order->reference" :subtitle="'Passée le '.$order->created_at->translatedFormat('d F Y à H:i').' par '.($order->user?->name ?? 'un compte supprimé')">
        <x-slot:actions>
            <a href="{{ route('admin.store.orders.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Commandes</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

    <article class="card card-flush">
        @if($order->status === 'cancelled')
            <div class="card-body">
                <div class="callout is-danger"><x-admin.icon name="x" :size="18" /> Commande annulée{{ $order->cancelled_at ? ' le '.$order->cancelled_at->translatedFormat('d F Y') : '' }}. Le stock a été remis en vente.</div>
            </div>
        @else
            <ol class="stepper is-5">
                @foreach($flow as $key => $label)
                    @php
                        $index = array_search($key, array_keys($flow), true);
                        $state = $order->status === 'delivered' || $index < $position ? 'is-done' : ($index === $position ? 'is-current' : '');
                    @endphp
                    <li class="{{ $state }}">{{ $label }}</li>
                @endforeach
            </ol>
        @endif
    </article>

    <div class="grid-3-1">
        <div class="store-aside">
            <article class="card card-flush">
                <div class="card-head"><h2>Articles</h2></div>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr><th>Article</th><th class="num">Prix</th><th class="num">Qté</th><th class="num">Total</th><th class="num">Retournés</th></tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                @if($item->isChild())
                                    <tr class="pack-child">
                                        <td>↳ {{ $item->name }}@if($item->variant_label) · {{ $item->variant_label }}@endif</td>
                                        <td class="num">—</td>
                                        <td class="num">{{ $item->quantity }}</td>
                                        <td class="num">—</td>
                                        <td class="num">{{ $item->returned_quantity ?: '—' }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>
                                            <span class="cell-main">{{ $item->name }}</span>
                                            @if($item->kind === 'pack')<span class="badge badge-info">Pack</span>@endif
                                            @if($item->variant_label)<div class="cell-sub">{{ $item->variant_label }}@if($item->sku) · {{ $item->sku }}@endif</div>@endif
                                        </td>
                                        <td class="num">{{ \App\Support\Store\StoreSettings::money((float) $item->unit_price) }}</td>
                                        <td class="num">{{ $item->quantity }}</td>
                                        <td class="num cell-main">{{ \App\Support\Store\StoreSettings::money((float) $item->line_total) }}</td>
                                        <td class="num">{{ $item->returned_quantity ?: '—' }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="store-totals">
                    <div><span>Sous-total</span><span>{{ \App\Support\Store\StoreSettings::money((float) $order->subtotal) }}</span></div>
                    <div><span>Livraison ({{ $order->delivery_name }})</span><span>{{ (float) $order->delivery_fee > 0 ? \App\Support\Store\StoreSettings::money((float) $order->delivery_fee) : 'Gratuite' }}</span></div>
                    <div class="grand"><span>Total</span><span>{{ $order->totalLabel() }}</span></div>
                    @if((float) $order->refunded_amount > 0)
                        <div class="refund"><span>Remboursé</span><span>{{ \App\Support\Store\StoreSettings::money((float) $order->refunded_amount) }}</span></div>
                    @endif
                </div>
            </article>

            @if($order->returns->count())
                <article class="card card-flush">
                    <div class="card-head"><h2>Retours</h2></div>
                    <div class="list">
                        @foreach($order->returns as $return)
                            <a href="{{ route('admin.store.returns.show', $return) }}" class="list-item">
                                <div class="grow">
                                    <strong>{{ $return->reference }}</strong>
                                    <span class="text-sm text-muted">{{ $return->reason }} · {{ $return->resolutionLabel() }}</span>
                                </div>
                                <span class="badge badge-{{ $return->statusTone() }}">{{ $return->statusLabel() }}</span>
                            </a>
                        @endforeach
                    </div>
                </article>
            @endif
        </div>

        <div class="store-aside">
            <article class="card">
                <div class="card-head"><h2>Traitement</h2></div>
                <div class="card-body stack">
                    <div class="chips">
                        <span class="badge badge-{{ $order->statusTone() }}">{{ $order->statusLabel() }}</span>
                        <span class="badge badge-{{ $order->paymentTone() }}">{{ $order->paymentStatusLabel() }}</span>
                    </div>

                    @if($order->nextStatuses() !== [] && $order->status !== 'cancelled')
                        <form method="post" action="{{ route('admin.store.orders.status', $order) }}" class="inline-form">
                            @csrf
                            <select name="status" aria-label="Nouveau statut" style="flex:1">
                                @foreach($order->nextStatuses() as $next)
                                    <option value="{{ $next }}">{{ $flow[$next] ?? \App\Models\StoreOrder::STATUSES[$next] }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
                        </form>
                    @endif

                    @if($order->payment_status === 'unpaid' && $order->status !== 'cancelled')
                        <form method="post" action="{{ route('admin.store.orders.pay', $order) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-block"><x-admin.icon name="check" :size="15" /> Marquer comme payée</button>
                        </form>
                    @endif

                    @if($order->canBeCancelled())
                        <form method="post" action="{{ route('admin.store.orders.cancel', $order) }}" onsubmit="return confirm('Annuler cette commande et remettre le stock en vente ?')">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-block">Annuler la commande</button>
                        </form>
                    @endif
                </div>
            </article>

            <article class="card">
                <div class="card-head"><h2>Client et livraison</h2></div>
                <div class="card-body">
                    <dl class="uinfo">
                        <div><dt>Nom</dt><dd>{{ $order->ship_name }}</dd></div>
                        <div><dt>Téléphone</dt><dd>{{ $order->ship_phone }}</dd></div>
                        @if($order->user)
                            <div><dt>Compte</dt><dd><a class="link-strong" href="{{ route('admin.users.show', $order->user) }}">{{ $order->user->email }}</a></dd></div>
                        @endif
                        <div><dt>Livraison</dt><dd>{{ $order->delivery_name }}</dd></div>
                        <div>
                            <dt>Adresse</dt>
                            @if($pickup)
                                <dd>Retrait sur place</dd>
                            @else
                                <dd>{{ $order->ship_address }}<br>{{ $order->ship_city }}@if($order->governorate) · {{ $order->governorate->name }}@endif</dd>
                            @endif
                        </div>
                        <div><dt>Paiement</dt><dd>{{ $order->payment_name }}@if($order->paid_at) · reçu le {{ $order->paid_at->translatedFormat('d M Y') }}@endif</dd></div>
                        @if($order->note)<div><dt>Note du client</dt><dd>{{ $order->note }}</dd></div>@endif
                    </dl>
                </div>
            </article>

            <article class="card">
                <div class="card-head"><h2>Suivi interne</h2></div>
                <form method="post" action="{{ route('admin.store.orders.tracking', $order) }}" class="card-body stack">
                    @csrf
                    @method('PUT')
                    <x-admin.input name="tracking_number" label="Numéro de suivi" :value="$order->tracking_number" maxlength="100" />
                    <x-admin.textarea name="admin_note" label="Note interne" :value="$order->admin_note" rows="3" maxlength="2000" hint="Visible uniquement par l’équipe." />
                    <div><button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button></div>
                </form>
            </article>
        </div>
    </div>
@endsection
