@extends('layouts.base', ['title' => $pack->name.' | '.$settings['name']])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('store.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $settings['name'] }}</a>

        @include('store._flash')

        <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            @if($pack->imageUrl())<img src="{{ $pack->imageUrl() }}" alt="{{ $pack->name }}" class="max-h-72 w-full rounded-2xl object-cover">@endif
            <div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $pack->name }}</h1>
                <p class="mt-1 text-lg font-semibold text-tlink-blue">{{ $pack->priceLabel() }}</p>
            </div>
            @if($pack->summary)<p class="text-sm text-slate-600">{{ $pack->summary }}</p>@endif
            @if($pack->description)<p class="whitespace-pre-line text-sm text-slate-700">{{ $pack->description }}</p>@endif

            <form method="post" action="{{ route('store.cart.add-pack', $pack) }}" class="space-y-4">
                @csrf
                <h2 class="font-display text-lg font-semibold text-slate-900">Contenu du pack</h2>
                <ul class="space-y-3">
                    @foreach($pack->items as $item)
                        <li class="rounded-2xl border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $item->quantity }} × {{ $item->product?->name }}</p>
                            @if($item->variant)
                                <p class="text-sm text-slate-600">{{ $item->variant->label() }}</p>
                            @else
                                <label class="mt-2 block text-sm text-slate-700">Couleur / taille
                                    <select name="choices[{{ $item->id }}]" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                        <option value="">Choisir…</option>
                                        @foreach($item->product?->variants ?? [] as $variant)
                                            <option value="{{ $variant->id }}" @disabled($variant->stock < $item->quantity) @selected((int) old('choices.'.$item->id) === $variant->id)>{{ $variant->label() }}@if($variant->stock < $item->quantity) (indisponible)@endif</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <label class="block text-sm font-semibold text-slate-700">Quantité de packs
                    <input type="number" name="quantity" min="1" max="{{ \App\Support\Store\StoreCart::MAX_QUANTITY }}" value="{{ old('quantity', 1) }}" class="mt-1 block w-24 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </label>

                <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-cart-plus mr-1" aria-hidden="true"></i> Ajouter le pack au panier</button>
            </form>
        </section>
    </main>
</div>
@endsection
