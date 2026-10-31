@extends('admin.layouts.app')

@section('title', 'Articles')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Articles" subtitle="Le catalogue de la boutique : catégorie, type, couleurs et tailles. Le stock se gère par variante.">
        <x-slot:actions>
            <a href="{{ route('admin.store.products.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvel article</a>
        </x-slot:actions>
    </x-admin.page-header>

    @error('product')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.store.products.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Rechercher un article…">
                </div>
                <select name="category" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $item)
                        <option value="{{ $item->id }}" @selected($category === $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
                <select name="state" aria-label="État">
                    <option value="">Tous les états</option>
                    <option value="active" @selected($state === 'active')>En vente</option>
                    <option value="inactive" @selected($state === 'inactive')>Masqués</option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $category > 0 || $state !== '')
                    <a href="{{ route('admin.store.products.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($products->count())
            <div class="list">
                @foreach($products as $product)
                    @php
                        $stock = $product->variants->sum('stock');
                        $low = $product->variants->where('is_active', true)->filter(fn ($variant) => $variant->isLow())->count();
                    @endphp
                    <div class="list-item">
                        @if($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" width="44" height="44" style="border-radius:8px;object-fit:cover">
                        @endif
                        <div class="grow">
                            <a href="{{ route('admin.store.products.edit', $product) }}"><strong style="display:inline">{{ $product->name }}</strong></a>
                            <x-admin.badge :status="$product->is_active ? 'active' : 'inactive'" :label="$product->is_active ? 'En vente' : 'Masqué'" />
                            @if($product->is_featured)<x-admin.badge status="info" label="À la une" />@endif
                            <div class="text-sm text-muted">
                                {{ $product->category?->name ?? 'Sans catégorie' }}@if($product->type) · {{ $product->type->name }}@endif
                                · {{ $product->priceLabel() }} · {{ $product->variants->count() }} variante(s) · {{ $stock }} en stock
                                @if($low > 0) · <span style="color:var(--danger)">{{ $low }} à réapprovisionner</span>@endif
                            </div>
                        </div>
                        <a href="{{ route('admin.store.products.edit', $product) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                    </div>
                @endforeach
            </div>
            {{ $products->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="package" title="Aucun article" text="Créez des catégories, couleurs et tailles, puis votre premier article." />
        @endif
    </article>
@endsection
