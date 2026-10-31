@extends('admin.layouts.app')

@php
    $editing = $product->exists;
@endphp

@section('title', $editing ? $product->name : 'Nouvel article')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header :title="$editing ? $product->name : 'Nouvel article'" subtitle="Fiche de l’article. Les couleurs et tailles se déclinent en variantes, chacune avec son stock.">
        <x-slot:actions>
            <a href="{{ route('admin.store.products.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            @if($editing && $product->is_active)
                <a href="{{ route('store.products.show', $product->slug) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Voir côté membre</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @error('product')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror
    @error('variant')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <article class="card">
        <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.store.products.update', $product) : route('admin.store.products.store') }}" class="form-grid">
            @csrf
            @if($editing) @method('PUT') @endif

            <x-admin.input name="name" label="Nom de l’article" :value="$product->name" required maxlength="160" span />
            <x-admin.select name="category_id" label="Catégorie" :options="$categories" :value="$product->category_id" placeholder="Sans catégorie" />
            <x-admin.select name="type_id" label="Type d’article" :options="$types" :value="$product->type_id" placeholder="Sans type" />
            <x-admin.input name="price" label="Prix de base (TND)" type="number" step="0.01" min="0" :value="$product->price" required hint="Chaque variante peut avoir son propre prix." />
            <x-admin.input name="summary" label="Résumé (une phrase)" :value="$product->summary" maxlength="300" />
            <x-admin.textarea name="description" label="Description" :value="$product->description" rows="5" maxlength="10000" />

            <div class="field span-2">
                <label for="f-image">Photo</label>
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="" width="96" height="96" style="border-radius:10px;object-fit:cover;margin-bottom:8px">
                    <label style="font-weight:400"><input type="checkbox" name="remove_image" value="1"> Retirer la photo</label>
                @endif
                <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                @error('image')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field span-2">
                <label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> En vente (visible dans la boutique)</label>
                <label style="font-weight:400"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Mettre à la une</label>
            </div>

            <div class="span-2" style="display:flex;gap:8px;justify-content:space-between">
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer' : 'Créer l’article' }}</button>
            </div>
        </form>

        @if($editing)
            <form method="post" action="{{ route('admin.store.products.destroy', $product) }}" onsubmit="return confirm('Supprimer cet article et ses variantes ?')" style="margin-top:12px">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer l’article</button>
            </form>
        @endif
    </article>

    @if($editing)
        <article class="card card-flush" style="margin-top:20px">
            <div class="card-head"><h2 class="card-title">Variantes et stock</h2></div>

            @if($product->variants->count())
                <div class="list">
                    @foreach($product->variants->sortBy('id') as $variant)
                        <div class="list-item" style="flex-wrap:wrap;align-items:flex-start">
                            <div style="min-width:170px">
                                @if($variant->color)<span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $variant->color->hex }};border:1px solid #ccc;vertical-align:middle"></span>@endif
                                <strong style="display:inline">{{ $variant->label() }}</strong>
                                <div class="text-sm {{ $variant->stock === 0 ? '' : 'text-muted' }}" @if($variant->stock === 0) style="color:var(--danger)" @endif>{{ $variant->stock }} en stock</div>
                            </div>
                            <form method="post" action="{{ route('admin.store.products.variants.update', $variant) }}" class="inline-form grow">
                                @csrf
                                @method('PUT')
                                <input type="text" name="sku" value="{{ $variant->sku }}" maxlength="60" placeholder="Référence (SKU)" aria-label="Référence">
                                <input type="number" name="price" value="{{ $variant->price }}" step="0.01" min="0" placeholder="Prix propre" aria-label="Prix de la variante" style="width:110px">
                                <input type="number" name="low_stock_threshold" value="{{ $variant->low_stock_threshold }}" min="0" aria-label="Seuil d’alerte" title="Seuil d’alerte de stock" style="width:80px">
                                <label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked($variant->is_active)> Active</label>
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                            </form>
                            <form method="post" action="{{ route('admin.store.products.variants.destroy', $variant) }}" onsubmit="return confirm('Supprimer cette variante ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" aria-label="Supprimer la variante"><x-admin.icon name="trash" :size="15" /></button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <p class="hint" style="padding:12px 16px">Pour changer le stock d’une variante (entrée, correction), utilisez la page <a href="{{ route('admin.store.stock.index', ['q' => $product->name]) }}">Stock</a> : chaque mouvement est tracé.</p>
            @endif

            <form method="post" action="{{ route('admin.store.products.variants.store', $product) }}" class="form-grid" style="padding:16px">
                @csrf
                <div class="field span-2"><strong>Ajouter des variantes</strong><p class="hint">Cochez des couleurs et/ou des tailles : toutes les combinaisons manquantes sont créées. Sans rien cocher, une variante « Unique » est créée.</p></div>
                <div class="field">
                    <label>Couleurs</label>
                    @forelse($colors as $color)
                        <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center;margin-right:10px">
                            <input type="checkbox" name="colors[]" value="{{ $color->id }}">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $color->hex }};border:1px solid #ccc"></span> {{ $color->name }}
                        </label>
                    @empty
                        <p class="hint">Aucune couleur : ajoutez-en dans <a href="{{ route('admin.store.refs.index') }}">Catégories, couleurs, tailles</a>.</p>
                    @endforelse
                </div>
                <div class="field">
                    <label>Tailles</label>
                    @forelse($sizes as $size)
                        <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center;margin-right:10px"><input type="checkbox" name="sizes[]" value="{{ $size->id }}"> {{ $size->name }}</label>
                    @empty
                        <p class="hint">Aucune taille : ajoutez-en dans <a href="{{ route('admin.store.refs.index') }}">Catégories, couleurs, tailles</a>.</p>
                    @endforelse
                </div>
                <div class="field"><label for="v-stock">Stock initial (par variante)</label><input id="v-stock" type="number" name="stock" min="0" value="0"></div>
                <div class="field"><label for="v-price">Prix propre (facultatif)</label><input id="v-price" type="number" name="price" step="0.01" min="0"></div>
                <div class="field"><label for="v-low">Seuil d’alerte</label><input id="v-low" type="number" name="low_stock_threshold" min="0" value="3"></div>
                <div class="span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter les variantes</button></div>
            </form>
        </article>
    @endif
@endsection
