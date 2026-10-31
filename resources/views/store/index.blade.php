@extends('layouts.base', ['title' => $settings['name'].' | Tunivisions'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-6 py-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $settings['name'] }}</h1>
                @if($settings['tagline'] !== '')<p class="text-sm text-slate-600">{{ $settings['tagline'] }}</p>@endif
            </div>
            <nav class="flex gap-2 text-sm font-semibold">
                <a href="{{ route('store.cart.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-tlink-blue hover:bg-slate-50"><i class="fa-solid fa-cart-shopping mr-1" aria-hidden="true"></i> Panier @if(($storeCartCount ?? 0) > 0)({{ $storeCartCount }})@endif</a>
                <a href="{{ route('store.orders.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-tlink-blue hover:bg-slate-50"><i class="fa-solid fa-box mr-1" aria-hidden="true"></i> Mes commandes</a>
            </nav>
        </header>

        @include('store._flash')

        <form method="get" action="{{ route('store.index') }}" class="flex flex-wrap gap-2 rounded-3xl border border-slate-200 bg-white p-4 shadow-soft">
            <input type="search" name="q" value="{{ $term }}" placeholder="Rechercher un article" class="min-w-[180px] flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <select name="categorie" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" aria-label="Catégorie">
                <option value="">Toutes les catégories</option>
                @foreach($categories as $item)<option value="{{ $item->slug }}" @selected($category === $item->slug)>{{ $item->name }}</option>@endforeach
            </select>
            <select name="type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" aria-label="Type">
                <option value="">Tous les types</option>
                @foreach($types as $item)<option value="{{ $item->slug }}" @selected($type === $item->slug)>{{ $item->name }}</option>@endforeach
            </select>
            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Filtrer</button>
        </form>

        @if($packs->isNotEmpty())
            <section class="space-y-3">
                <h2 class="font-display text-lg font-semibold text-slate-900">Packs</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($packs as $pack)
                        <a href="{{ route('store.packs.show', $pack->slug) }}" class="block overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft transition hover:-translate-y-0.5">
                            @if($pack->imageUrl())
                                <img src="{{ $pack->imageUrl() }}" alt="" class="h-44 w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-44 items-center justify-center bg-slate-100 text-3xl text-slate-400"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
                            @endif
                            <div class="space-y-1 p-4">
                                <p class="font-semibold text-slate-900">{{ $pack->name }}</p>
                                <p class="text-sm text-slate-600">{{ $pack->items->sum('quantity') }} article(s) · <span class="font-semibold text-tlink-blue">{{ $pack->priceLabel() }}</span></p>
                                @if($pack->availableUnits() < 1)<p class="text-xs font-semibold text-rose-600">Épuisé</p>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-3">
            <h2 class="font-display text-lg font-semibold text-slate-900">Articles</h2>
            @if($products->isEmpty())
                <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Aucun article pour le moment.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($products as $product)
                        <a href="{{ route('store.products.show', $product->slug) }}" class="block overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft transition hover:-translate-y-0.5">
                            @if($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="" class="h-44 w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-44 items-center justify-center bg-slate-100 text-3xl text-slate-400"><i class="fa-solid fa-shirt" aria-hidden="true"></i></div>
                            @endif
                            <div class="space-y-1 p-4">
                                @if($product->category)<p class="text-xs font-semibold uppercase text-slate-500">{{ $product->category->name }}</p>@endif
                                <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                <p class="text-sm font-semibold text-tlink-blue">{{ $product->priceLabel() }}</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($product->variants->pluck('color')->filter()->unique('id') as $color)
                                        <span class="inline-block h-3.5 w-3.5 rounded-full border border-slate-300" style="background: {{ $color->hex ?: '#e2e8f0' }}" title="{{ $color->name }}"></span>
                                    @endforeach
                                </div>
                                @if($product->availableStock() < 1)<p class="text-xs font-semibold text-rose-600">Épuisé</p>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div>{{ $products->links() }}</div>
            @endif
        </section>
    </main>
</div>
@endsection
