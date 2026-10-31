@extends('layouts.base', ['title' => 'Panier | '.$settings['name']])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('store.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $settings['name'] }}</a>
        <h1 class="font-display text-2xl font-semibold text-slate-900">Mon panier</h1>

        @include('store._flash')

        @if($cart['lines'] === [])
            <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Votre panier est vide.</p>
        @else
            <section class="divide-y divide-slate-100 rounded-3xl border border-slate-200 bg-white shadow-soft">
                @foreach($cart['lines'] as $line)
                    <article class="flex flex-wrap items-center gap-4 p-4">
                        @if($line['image'])
                            <img src="{{ $line['image'] }}" alt="" class="h-16 w-16 rounded-xl object-cover">
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-slate-100 text-slate-400"><i class="fa-solid {{ $line['kind'] === 'pack' ? 'fa-box-open' : 'fa-shirt' }}" aria-hidden="true"></i></div>
                        @endif

                        <div class="min-w-0 flex-1 space-y-0.5">
                            @if($line['url'])
                                <a href="{{ $line['url'] }}" class="font-semibold text-slate-900 hover:underline">{{ $line['name'] }}</a>
                            @else
                                <p class="font-semibold text-slate-900">{{ $line['name'] }}</p>
                            @endif
                            @if($line['detail'])<p class="text-xs text-slate-500">{{ $line['detail'] }}</p>@endif
                            @foreach($line['components'] as $component)
                                <p class="text-xs text-slate-500">{{ $component['quantity'] }} × {{ $component['variant']->product?->name }} — {{ $component['variant']->label() }}</p>
                            @endforeach
                            @foreach($line['issues'] as $issue)
                                <p class="text-xs font-semibold text-rose-600">{{ $issue }}</p>
                            @endforeach
                            <p class="text-sm text-slate-600">{{ \App\Support\Store\StoreSettings::money($line['unit_price']) }}</p>
                        </div>

                        <form method="post" action="{{ route('store.cart.update', $line['id']) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="number" name="quantity" min="0" max="{{ \App\Support\Store\StoreCart::MAX_QUANTITY }}" value="{{ $line['quantity'] }}" class="w-20 rounded-xl border border-slate-300 px-2 py-1.5 text-sm" aria-label="Quantité">
                            <button type="submit" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Mettre à jour</button>
                        </form>

                        <p class="w-24 text-right text-sm font-semibold text-slate-900">{{ \App\Support\Store\StoreSettings::money($line['total']) }}</p>

                        <form method="post" action="{{ route('store.cart.remove', $line['id']) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-600 hover:text-rose-800" aria-label="Retirer"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                        </form>
                    </article>
                @endforeach
            </section>

            <section class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                <p class="text-sm text-slate-600">Sous-total <span class="ml-2 text-lg font-semibold text-slate-900">{{ \App\Support\Store\StoreSettings::money($cart['subtotal']) }}</span></p>
                @if($cart['ok'])
                    <a href="{{ route('store.checkout') }}" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Passer la commande</a>
                @else
                    <p class="text-sm font-semibold text-rose-600">Ajustez les articles signalés pour continuer.</p>
                @endif
            </section>
        @endif
    </main>
</div>
@endsection
