@extends('layouts.base', ['title' => $product->name.' | '.$settings['name']])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-4xl space-y-5 py-6">
        <a href="{{ route('store.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $settings['name'] }}</a>

        @include('store._flash')

        <section class="grid gap-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft md:grid-cols-2">
            <div>
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full rounded-2xl object-cover">
                @else
                    <div class="flex h-64 items-center justify-center rounded-2xl bg-slate-100 text-5xl text-slate-400"><i class="fa-solid fa-shirt" aria-hidden="true"></i></div>
                @endif
            </div>

            <div class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ $product->category?->name }}@if($product->type) · {{ $product->type->name }}@endif</p>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $product->name }}</h1>
                    <p class="mt-1 text-lg font-semibold text-tlink-blue">{{ $product->priceLabel() }}</p>
                </div>
                @if($product->summary)<p class="text-sm text-slate-600">{{ $product->summary }}</p>@endif

                @if($product->variants->isEmpty())
                    <p class="rounded-2xl bg-slate-100 p-4 text-sm text-slate-600">Cet article n’est pas disponible pour le moment.</p>
                @else
                    <form method="post" action="{{ route('store.cart.add') }}" class="space-y-4">
                        @csrf
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold text-slate-700">Choisissez votre variante</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach($product->variants as $variant)
                                    <label class="{{ $variant->stock < 1 ? 'opacity-50' : 'cursor-pointer' }}">
                                        <input type="radio" name="variant_id" value="{{ $variant->id }}" class="peer sr-only" @checked((int) old('variant_id') === $variant->id) @disabled($variant->stock < 1) required>
                                        <span class="flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm peer-checked:border-tlink-blue peer-checked:bg-blue-50 peer-checked:font-semibold">
                                            @if($variant->color?->hex)<span class="inline-block h-3.5 w-3.5 rounded-full border border-slate-300" style="background: {{ $variant->color->hex }}"></span>@endif
                                            {{ $variant->label() }}
                                            @if($product->hasVariedPrices())<span class="text-xs text-slate-500">· {{ \App\Support\Store\StoreSettings::money($variant->unitPrice()) }}</span>@endif
                                            @if($variant->stock < 1)<span class="text-xs text-rose-600">épuisé</span>@elseif($variant->isLow())<span class="text-xs text-amber-600">{{ $variant->stock }} restant(s)</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="block text-sm font-semibold text-slate-700">Quantité
                            <input type="number" name="quantity" min="1" max="{{ \App\Support\Store\StoreCart::MAX_QUANTITY }}" value="{{ old('quantity', 1) }}" class="mt-1 block w-24 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </label>

                        <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-cart-plus mr-1" aria-hidden="true"></i> Ajouter au panier</button>
                    </form>
                @endif
            </div>
        </section>

        @if($product->description)
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Description</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $product->description }}</p>
            </section>
        @endif
    </main>
</div>
@endsection
