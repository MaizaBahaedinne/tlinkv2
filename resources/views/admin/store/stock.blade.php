@extends('admin.layouts.app')

@section('title', 'Stock')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Stock" subtitle="Niveau de stock de chaque variante. Toute entrée, sortie ou correction est tracée dans le journal." />

    @include('admin.store._tabs')

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.store.stock.index', array_filter(['q' => $term])) }}" class="tab {{ $filter === '' ? 'is-active' : '' }}">Tout le stock</a>
            <a href="{{ route('admin.store.stock.index', array_filter(['q' => $term, 'filter' => 'low'])) }}" class="tab {{ $filter === 'low' ? 'is-active' : '' }}">À réapprovisionner <span class="n">{{ $lowCount }}</span></a>
            <a href="{{ route('admin.store.stock.index', array_filter(['q' => $term, 'filter' => 'out'])) }}" class="tab {{ $filter === 'out' ? 'is-active' : '' }}">Épuisé</a>
        </div>
        <div class="toolbar">
            <form method="get" action="{{ route('admin.store.stock.index') }}">
                @if($filter !== '')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Article ou référence…">
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Rechercher</button>
                @if($term !== '')
                    <a href="{{ route('admin.store.stock.index', array_filter(['filter' => $filter])) }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($variants->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Variante</th>
                            <th>Stock</th>
                            <th>Ajuster</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($variants as $variant)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.store.products.edit', $variant->product_id) }}" class="cell-main link-strong">{{ $variant->product?->name }}</a>
                                    @unless($variant->is_active)<span class="badge badge-neutral">Inactive</span>@endunless
                                    @if($variant->sku)<div class="cell-sub">{{ $variant->sku }}</div>@endif
                                </td>
                                <td>@if($variant->color)<span class="swatch" style="background:{{ $variant->color->hex }}"></span> @endif{{ $variant->label() }}</td>
                                <td>
                                    <span class="stock-pill {{ $variant->stock === 0 ? 'is-out' : ($variant->isLow() ? 'is-low' : '') }}">{{ $variant->stock }}</span>
                                    @if($variant->stock > 0 && $variant->isLow())<div class="cell-sub">seuil {{ $variant->low_stock_threshold }}</div>@endif
                                </td>
                                <td>
                                    <form id="adj-{{ $variant->id }}" method="post" action="{{ route('admin.store.stock.adjust', $variant) }}" class="inline-form">
                                        @csrf
                                        <input type="number" name="delta" required placeholder="+10 ou -2" aria-label="Variation" style="width:104px">
                                        <select name="reason" aria-label="Motif" style="min-width:170px">
                                            <option value="restock">Réapprovisionnement</option>
                                            <option value="adjustment">Correction</option>
                                        </select>
                                        <input type="text" name="note" maxlength="250" placeholder="Note (facultatif)" aria-label="Note" style="min-width:160px">
                                    </form>
                                </td>
                                <td class="cell-actions"><button form="adj-{{ $variant->id }}" type="submit" class="btn btn-secondary btn-sm">Appliquer</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $variants->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="package" title="Aucune variante" text="Aucune variante ne correspond à ces critères." />
        @endif
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Derniers mouvements</h2>
                <p class="sub">Les 25 plus récents, tous articles confondus.</p>
            </div>
        </div>
        @if($movements->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Date</th><th>Article</th><th>Motif</th><th class="num">Variation</th><th class="num">Solde</th><th>Par</th></tr>
                    </thead>
                    <tbody>
                        @foreach($movements as $movement)
                            <tr>
                                <td class="nowrap text-sm text-muted">{{ $movement->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <div class="cell-main">{{ $movement->variant?->product?->name }}</div>
                                    <div class="cell-sub">{{ $movement->variant?->label() }}@if($movement->note) · {{ $movement->note }}@endif</div>
                                </td>
                                <td><span class="badge badge-neutral">{{ $movement->reasonLabel() }}</span></td>
                                <td class="num cell-main" style="color:var({{ $movement->delta >= 0 ? '--success' : '--danger' }})">{{ $movement->delta > 0 ? '+' : '' }}{{ $movement->delta }}</td>
                                <td class="num">{{ $movement->balance }}</td>
                                <td class="text-sm text-muted">{{ $movement->user?->name ?? 'Système' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="activity" title="Aucun mouvement" text="Le journal se remplit dès la première entrée ou vente." />
        @endif
    </article>
@endsection
