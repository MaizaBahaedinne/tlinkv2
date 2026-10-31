@extends('admin.layouts.app')

@section('title', 'Articles')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Articles" subtitle="Le catalogue de la boutique : catégorie, type, couleurs et tailles. Le stock se gère par variante.">
        <x-slot:actions>
            <a href="{{ route('admin.store.products.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvel article</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

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
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Catégorie</th>
                            <th class="num">Prix</th>
                            <th class="num">Variantes</th>
                            <th>Stock</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $product)
                            @php
                                $active = $product->variants->where('is_active', true);
                                $stock = (int) $active->sum('stock');
                                $low = $active->filter(fn ($variant) => $variant->isLow())->count();
                                $tone = $stock === 0 ? 'is-out' : ($low > 0 ? 'is-low' : '');
                            @endphp
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="store-thumb">
                                            @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="">@else<x-admin.icon name="package" :size="20" />@endif
                                        </span>
                                        <div>
                                            <a href="{{ route('admin.store.products.edit', $product) }}" class="cell-main link-strong">{{ $product->name }}</a>
                                            <div class="chips" style="gap:6px;margin-top:3px">
                                                <span class="badge badge-{{ $product->is_active ? 'success' : 'neutral' }}">{{ $product->is_active ? 'En vente' : 'Masqué' }}</span>
                                                @if($product->is_featured)<span class="badge badge-info">À la une</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-main">{{ $product->category?->name ?? '—' }}</div>
                                    @if($product->type)<div class="cell-sub">{{ $product->type->name }}</div>@endif
                                </td>
                                <td class="num cell-main">{{ $product->priceLabel() }}</td>
                                <td class="num">{{ $product->variants->count() }}</td>
                                <td>
                                    <span class="stock-pill {{ $tone }}">{{ $stock }} en stock</span>
                                    @if($low > 0 && $stock > 0)<div class="cell-sub">{{ $low }} variante(s) à réapprovisionner</div>@endif
                                </td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.store.products.edit', $product) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $products->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="package" title="Aucun article" text="Créez des catégories, couleurs et tailles, puis votre premier article.">
                <a href="{{ route('admin.store.products.create') }}" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Nouvel article</a>
            </x-admin.empty>
        @endif
    </article>
@endsection
