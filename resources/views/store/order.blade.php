@extends('layouts.base', ['title' => 'Commande '.$order->reference.' | '.$settings['name']])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('store.orders.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> Mes commandes</a>

        @include('store._flash')

        <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Commande {{ $order->reference }}</h1>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Statut</dt><dd class="font-medium text-slate-900">{{ $order->statusLabel() }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Paiement</dt><dd class="font-medium text-slate-900">{{ $order->paymentStatusLabel() }} — {{ $order->payment_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Livraison</dt><dd class="font-medium text-slate-900">{{ $order->delivery_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Date</dt><dd class="font-medium text-slate-900">{{ $order->created_at->translatedFormat('d F Y') }}</dd></div>
                @if($order->tracking_number)<div><dt class="text-xs font-semibold uppercase text-slate-500">Suivi</dt><dd class="font-medium text-slate-900">{{ $order->tracking_number }}</dd></div>@endif
                @if(! $order->isPickup())
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-slate-500">Adresse</dt><dd class="font-medium text-slate-900">{{ $order->ship_name }} · {{ $order->ship_phone }}<br>{{ $order->ship_address }}, {{ $order->ship_city }} {{ $order->governorate?->name }}</dd></div>
                @endif
            </dl>

            @if($order->status === 'pending' && $payment && $payment->instructions)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">{{ $payment->name }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $payment->instructions }}</p>
                    <p class="mt-2">Indiquez la référence <strong>{{ $order->reference }}</strong>.</p>
                </div>
            @endif
        </section>

        <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Articles</h2>
            <ul class="space-y-2 text-sm">
                @foreach($order->items->where('kind', '!=', 'pack_item') as $item)
                    <li>
                        <div class="flex justify-between gap-3">
                            <span class="font-medium text-slate-900">{{ $item->quantity }} × {{ $item->name }}@if($item->variant_label) <span class="font-normal text-slate-500">({{ $item->variant_label }})</span>@endif</span>
                            <span>{{ \App\Support\Store\StoreSettings::money((float) $item->line_total) }}</span>
                        </div>
                        @foreach($order->items->where('parent_id', $item->id) as $child)
                            <p class="pl-4 text-xs text-slate-500">{{ $child->quantity }} × {{ $child->name }}@if($child->variant_label) ({{ $child->variant_label }})@endif</p>
                        @endforeach
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-1 border-t border-slate-100 pt-3 text-sm">
                <div class="flex justify-between"><dt>Sous-total</dt><dd>{{ \App\Support\Store\StoreSettings::money((float) $order->subtotal) }}</dd></div>
                <div class="flex justify-between"><dt>Livraison</dt><dd>{{ \App\Support\Store\StoreSettings::money((float) $order->delivery_fee) }}</dd></div>
                <div class="flex justify-between font-semibold text-slate-900"><dt>Total</dt><dd>{{ $order->totalLabel() }}</dd></div>
                @if((float) $order->refunded_amount > 0)<div class="flex justify-between text-emerald-700"><dt>Remboursé</dt><dd>{{ \App\Support\Store\StoreSettings::money((float) $order->refunded_amount) }}</dd></div>@endif
            </dl>

            @if($order->status === 'pending')
                <form method="post" action="{{ route('store.orders.cancel', $order) }}" onsubmit="return confirm('Annuler cette commande ?')">
                    @csrf
                    <button type="submit" class="rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Annuler la commande</button>
                </form>
            @endif
        </section>

        @if($order->returns->isNotEmpty())
            <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Mes retours</h2>
                @foreach($order->returns as $return)
                    <article class="rounded-2xl border border-slate-200 p-4 text-sm">
                        <p class="font-semibold text-slate-900">{{ $return->reference }} · {{ $return->statusLabel() }} · {{ $return->resolutionLabel() }}</p>
                        <ul class="mt-1 text-xs text-slate-500">
                            @foreach($return->items as $row)<li>{{ $row->quantity }} × {{ $row->orderItem?->name }}</li>@endforeach
                        </ul>
                        @if($return->admin_note)<p class="mt-1 text-xs text-slate-600">Réponse : {{ $return->admin_note }}</p>@endif
                        @if((float) $return->refund_amount > 0)<p class="mt-1 text-xs font-semibold text-emerald-700">Remboursement : {{ \App\Support\Store\StoreSettings::money((float) $return->refund_amount) }}</p>@endif
                    </article>
                @endforeach
            </section>
        @endif

        @if($canReturn && $returnable !== [])
            <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Demander un retour</h2>
                @if($settings['return_policy'] !== '')<p class="whitespace-pre-line text-xs text-slate-500">{{ $settings['return_policy'] }}</p>@endif
                <form method="post" action="{{ route('store.orders.return', $order) }}" class="space-y-3">
                    @csrf
                    <ul class="space-y-2 text-sm">
                        @foreach($returnable as $row)
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $row['item']->name }}@if($row['item']->variant_label) ({{ $row['item']->variant_label }})@endif <span class="text-xs text-slate-500">· max {{ $row['remaining'] }}</span></span>
                                <input type="number" name="quantities[{{ $row['item']->id }}]" min="0" max="{{ $row['remaining'] }}" value="{{ old('quantities.'.$row['item']->id, 0) }}" class="w-20 rounded-xl border border-slate-300 px-2 py-1.5 text-sm" aria-label="Quantité à retourner">
                            </li>
                        @endforeach
                    </ul>
                    <label class="block text-sm font-semibold text-slate-700">Motif
                        <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" required class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Détails (facultatif)
                        <textarea name="details" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">{{ old('details') }}</textarea>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Souhait
                        <select name="resolution" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                            @foreach(\App\Models\StoreReturn::RESOLUTIONS as $value => $label)<option value="{{ $value }}" @selected(old('resolution') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Envoyer la demande</button>
                </form>
            </section>
        @endif
    </main>
</div>
@endsection
