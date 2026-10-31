@extends('admin.layouts.app')

@section('title', 'Commande '.$order->reference)
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header :title="'Commande '.$order->reference" :subtitle="'Passée le '.$order->created_at->translatedFormat('d F Y à H:i').' par '.($order->user?->name ?? 'un compte supprimé')">
        <x-slot:actions>
            <a href="{{ route('admin.store.orders.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    @error('order')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <div class="grid-3-1">
        <div>
            <article class="card card-flush">
                <div class="card-head"><h2 class="card-title">Articles</h2></div>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Article</th><th>Prix</th><th>Qté</th><th>Total</th><th>Retournés</th></tr></thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td @if($item->isChild()) style="padding-left:28px" @endif>
                                        {{ $item->isChild() ? '↳ ' : '' }}<strong style="display:inline">{{ $item->name }}</strong>
                                        @if($item->kind === 'pack')<x-admin.badge status="info" label="Pack" />@endif
                                        @if($item->variant_label)<div class="cell-sub">{{ $item->variant_label }}@if($item->sku) · {{ $item->sku }}@endif</div>@endif
                                    </td>
                                    <td>{{ $item->isChild() ? '—' : \App\Support\Store\StoreSettings::money((float) $item->unit_price) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ $item->isChild() ? '—' : \App\Support\Store\StoreSettings::money((float) $item->line_total) }}</td>
                                    <td>{{ $item->returned_quantity ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px;text-align:right">
                    <div class="text-sm text-muted">Sous-total : {{ \App\Support\Store\StoreSettings::money((float) $order->subtotal) }}</div>
                    <div class="text-sm text-muted">Livraison ({{ $order->delivery_name }}) : {{ (float) $order->delivery_fee > 0 ? \App\Support\Store\StoreSettings::money((float) $order->delivery_fee) : 'gratuite' }}</div>
                    <div style="font-weight:700;font-size:1.1rem">Total : {{ $order->totalLabel() }}</div>
                    @if((float) $order->refunded_amount > 0)<div class="text-sm" style="color:var(--danger)">Remboursé : {{ \App\Support\Store\StoreSettings::money((float) $order->refunded_amount) }}</div>@endif
                </div>
            </article>

            @if($order->returns->count())
                <article class="card card-flush" style="margin-top:20px">
                    <div class="card-head"><h2 class="card-title">Retours</h2></div>
                    <div class="list">
                        @foreach($order->returns as $return)
                            <div class="list-item">
                                <div class="grow"><a href="{{ route('admin.store.returns.show', $return) }}"><strong style="display:inline">{{ $return->reference }}</strong></a> <x-admin.badge :status="$return->status" :label="$return->statusLabel()" /> <span class="text-sm text-muted">{{ $return->reason }}</span></div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endif
        </div>

        <div>
            <article class="card">
                <h2 class="card-title">Statut</h2>
                <p><x-admin.badge :status="$order->status" :label="$order->statusLabel()" /> <x-admin.badge :status="$order->payment_status === 'paid' ? 'approved' : ($order->payment_status === 'unpaid' ? 'pending' : 'rejected')" :label="$order->paymentStatusLabel()" /></p>

                @if($order->nextStatuses() !== [] && $order->status !== 'cancelled')
                    <form method="post" action="{{ route('admin.store.orders.status', $order) }}" class="inline-form" style="margin-top:10px">
                        @csrf
                        <select name="status" aria-label="Nouveau statut">
                            @foreach($order->nextStatuses() as $next)
                                <option value="{{ $next }}">{{ $next === 'shipped' && $order->isPickup() ? 'Prête au retrait' : \App\Models\StoreOrder::STATUSES[$next] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
                    </form>
                @endif

                @if($order->payment_status === 'unpaid' && $order->status !== 'cancelled')
                    <form method="post" action="{{ route('admin.store.orders.pay', $order) }}" style="margin-top:10px">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm"><x-admin.icon name="check" :size="15" /> Marquer comme payée</button>
                    </form>
                @endif

                @if($order->canBeCancelled())
                    <form method="post" action="{{ route('admin.store.orders.cancel', $order) }}" onsubmit="return confirm('Annuler cette commande et remettre le stock en vente ?')" style="margin-top:10px">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">Annuler la commande</button>
                    </form>
                @endif
            </article>

            <article class="card" style="margin-top:20px">
                <h2 class="card-title">Client et livraison</h2>
                <p class="text-sm">
                    <strong>{{ $order->ship_name }}</strong><br>
                    {{ $order->ship_phone }}<br>
                    @if($order->user)<a href="{{ route('admin.users.show', $order->user) }}">{{ $order->user->email }}</a><br>@endif
                </p>
                <p class="text-sm">
                    <strong>{{ $order->delivery_name }}</strong><br>
                    @if($order->isPickup())
                        Retrait sur place
                    @else
                        {{ $order->ship_address }}<br>{{ $order->ship_city }}@if($order->governorate) · {{ $order->governorate->name }}@endif
                    @endif
                </p>
                <p class="text-sm"><strong>Paiement :</strong> {{ $order->payment_name }}@if($order->paid_at) · reçu le {{ $order->paid_at->translatedFormat('d M Y') }}@endif</p>
                @if($order->note)<p class="text-sm"><strong>Note du client :</strong> {{ $order->note }}</p>@endif
            </article>

            <article class="card" style="margin-top:20px">
                <h2 class="card-title">Suivi interne</h2>
                <form method="post" action="{{ route('admin.store.orders.tracking', $order) }}" class="form-grid" style="grid-template-columns:1fr">
                    @csrf
                    @method('PUT')
                    <x-admin.input name="tracking_number" label="Numéro de suivi" :value="$order->tracking_number" maxlength="100" />
                    <x-admin.textarea name="admin_note" label="Note interne" :value="$order->admin_note" rows="3" maxlength="2000" />
                    <div><button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button></div>
                </form>
            </article>
        </div>
    </div>
@endsection
