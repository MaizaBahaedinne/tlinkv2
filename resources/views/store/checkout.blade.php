@extends('layouts.base', ['title' => 'Commande | '.$settings['name']])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('store.cart.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> Panier</a>
        <h1 class="font-display text-2xl font-semibold text-slate-900">Valider ma commande</h1>

        @include('store._flash')

        @if($deliveries->isEmpty() || $payments->isEmpty())
            <p class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">La commande n’est pas encore disponible : aucun mode de {{ $deliveries->isEmpty() ? 'livraison' : 'paiement' }} n’est proposé pour le moment.</p>
        @else
            <form method="post" action="{{ route('store.checkout.place') }}" class="space-y-5">
                @csrf

                <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Livraison</h2>
                    @foreach($deliveries as $delivery)
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 has-[:checked]:border-tlink-blue has-[:checked]:bg-blue-50">
                            <input type="radio" name="delivery_method_id" value="{{ $delivery->id }}" class="mt-1" @checked((int) old('delivery_method_id') === $delivery->id) required>
                            <span class="text-sm">
                                <span class="font-semibold text-slate-900">{{ $delivery->name }}</span>
                                <span class="text-slate-600">· {{ $delivery->isPickup() ? 'gratuit' : \App\Support\Store\StoreSettings::money((float) $delivery->fee) }}@if($delivery->free_above !== null && ! $delivery->isPickup()) (offert dès {{ \App\Support\Store\StoreSettings::money((float) $delivery->free_above) }})@endif</span>
                                @if($delivery->estimated_delay)<span class="block text-xs text-slate-500">Délai : {{ $delivery->estimated_delay }}</span>@endif
                                @if($delivery->instructions)<span class="block whitespace-pre-line text-xs text-slate-500">{{ $delivery->instructions }}</span>@endif
                                @if(! $delivery->isPickup() && $delivery->governorate_fees)<span class="block text-xs text-slate-500">Le tarif dépend de votre gouvernorat.</span>@endif
                            </span>
                        </label>
                    @endforeach
                </section>

                <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:grid-cols-2">
                    <h2 class="font-display text-lg font-semibold text-slate-900 sm:col-span-2">Coordonnées</h2>
                    <label class="text-sm font-semibold text-slate-700">Nom complet
                        <input type="text" name="ship_name" value="{{ old('ship_name', auth()->user()->name) }}" maxlength="120" required class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Téléphone
                        <input type="tel" name="ship_phone" value="{{ old('ship_phone') }}" maxlength="30" required class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Gouvernorat
                        <select name="governorate_id" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                            <option value="">—</option>
                            @foreach($governorates as $governorate)<option value="{{ $governorate->id }}" @selected((int) old('governorate_id') === $governorate->id)>{{ $governorate->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Ville
                        <input type="text" name="city" value="{{ old('city') }}" maxlength="100" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                    </label>
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Adresse (obligatoire pour une livraison à domicile)
                        <input type="text" name="address" value="{{ old('address') }}" maxlength="255" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">
                    </label>
                    <label class="text-sm font-semibold text-slate-700 sm:col-span-2">Note (facultatif)
                        <textarea name="note" rows="2" maxlength="1000" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-normal">{{ old('note') }}</textarea>
                    </label>
                </section>

                <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Paiement</h2>
                    @foreach($payments as $payment)
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 has-[:checked]:border-tlink-blue has-[:checked]:bg-blue-50">
                            <input type="radio" name="payment_method_id" value="{{ $payment->id }}" class="mt-1" @checked((int) old('payment_method_id') === $payment->id) required>
                            <span class="text-sm">
                                <span class="font-semibold text-slate-900">{{ $payment->name }}</span>
                                @if($payment->applies_to !== 'all')<span class="text-xs text-slate-500"> · {{ \App\Models\StorePaymentMethod::APPLIES_TO[$payment->applies_to] }} uniquement</span>@endif
                                @if($payment->instructions)<span class="block whitespace-pre-line text-xs text-slate-500">{{ $payment->instructions }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </section>

                <section class="space-y-2 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Récapitulatif</h2>
                    <ul class="space-y-1 text-sm text-slate-700">
                        @foreach($cart['lines'] as $line)
                            <li class="flex justify-between gap-3"><span>{{ $line['quantity'] }} × {{ $line['name'] }}@if($line['detail']) ({{ $line['detail'] }})@endif</span><span>{{ \App\Support\Store\StoreSettings::money($line['total']) }}</span></li>
                        @endforeach
                    </ul>
                    <p class="flex justify-between border-t border-slate-100 pt-2 text-sm font-semibold text-slate-900"><span>Sous-total</span><span>{{ \App\Support\Store\StoreSettings::money($cart['subtotal']) }}</span></p>
                    <p class="text-xs text-slate-500">Les frais de livraison sont calculés à la validation selon le mode et le gouvernorat choisis.</p>
                </section>

                <button type="submit" class="rounded-xl bg-tlink-blue px-6 py-3 text-sm font-semibold text-white hover:opacity-90">Confirmer la commande</button>
            </form>
        @endif
    </main>
</div>
@endsection
