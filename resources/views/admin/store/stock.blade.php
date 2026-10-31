@extends('admin.layouts.app')

@section('title', 'Stock')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Stock" subtitle="Niveau de stock de chaque variante. Toute entrée, sortie ou correction est tracée dans le journal ci-dessous.">
        <x-slot:actions>
            <a href="{{ route('admin.store.stock.index', ['filter' => 'low']) }}" class="btn btn-secondary">{{ $lowCount }} à réapprovisionner</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.store.stock.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Article ou référence…">
                </div>
                <select name="filter" aria-label="Filtre">
                    <option value="">Tout le stock</option>
                    <option value="low" @selected($filter === 'low')>Stock bas</option>
                    <option value="out" @selected($filter === 'out')>Épuisé</option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $filter !== '')
                    <a href="{{ route('admin.store.stock.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($variants->count())
            <div class="list">
                @foreach($variants as $variant)
                    <div class="list-item" style="flex-wrap:wrap">
                        <div class="grow" style="min-width:220px">
                            <a href="{{ route('admin.store.products.edit', $variant->product_id) }}"><strong style="display:inline">{{ $variant->product?->name }}</strong></a>
                            <span class="text-sm text-muted">· {{ $variant->label() }}@if($variant->sku) · {{ $variant->sku }}@endif</span>
                            @unless($variant->is_active)<x-admin.badge status="inactive" label="Inactive" />@endunless
                            <div class="text-sm" @if($variant->stock === 0) style="color:var(--danger)" @elseif($variant->isLow()) style="color:#b7791f" @endif>
                                {{ $variant->stock }} en stock @if($variant->stock === 0) — épuisé @elseif($variant->isLow()) — stock bas (seuil {{ $variant->low_stock_threshold }}) @endif
                            </div>
                        </div>
                        <form method="post" action="{{ route('admin.store.stock.adjust', $variant) }}" class="inline-form">
                            @csrf
                            <input type="number" name="delta" required placeholder="+10 ou -2" aria-label="Variation" style="width:110px">
                            <select name="reason" aria-label="Motif">
                                <option value="restock">Réapprovisionnement</option>
                                <option value="adjustment">Correction</option>
                            </select>
                            <input type="text" name="note" maxlength="250" placeholder="Note (facultatif)" aria-label="Note">
                            <button type="submit" class="btn btn-secondary btn-sm">Appliquer</button>
                        </form>
                    </div>
                @endforeach
            </div>
            {{ $variants->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="package" title="Aucune variante" text="Créez un article et ses variantes pour suivre son stock." />
        @endif
    </article>

    <article class="card card-flush" style="margin-top:20px">
        <div class="card-head"><h2 class="card-title">Derniers mouvements</h2></div>
        <div class="list">
            @forelse($movements as $movement)
                <div class="list-item">
                    <div class="grow">
                        <strong style="display:inline">{{ $movement->variant?->product?->name }}</strong>
                        <span class="text-sm text-muted">· {{ $movement->variant?->label() }}</span>
                        <div class="text-sm text-muted">
                            {{ $movement->reasonLabel() }} · {{ $movement->created_at->translatedFormat('d M Y H:i') }}@if($movement->user) · {{ $movement->user->name }}@endif @if($movement->note) · {{ $movement->note }}@endif
                        </div>
                    </div>
                    <span style="font-weight:700;color:{{ $movement->delta >= 0 ? 'var(--success, #1f7a4d)' : 'var(--danger)' }}">{{ $movement->delta > 0 ? '+' : '' }}{{ $movement->delta }}</span>
                    <span class="text-sm text-muted">→ {{ $movement->balance }}</span>
                </div>
            @empty
                <p class="text-sm text-muted" style="padding:12px 16px">Aucun mouvement pour l’instant.</p>
            @endforelse
        </div>
    </article>
@endsection
