@extends('admin.layouts.app')

@php
    $editing = $pack->exists;
    $rows = old('items') !== null
        ? collect(old('items'))->map(fn ($row) => ['ref' => $row['ref'] ?? '', 'quantity' => $row['quantity'] ?? 1])->values()->all()
        : $pack->items->map(fn ($item) => ['ref' => $item->variant_id ? 'v'.$item->variant_id : 'p'.$item->product_id, 'quantity' => $item->quantity])->values()->all();
    $rows = array_pad($rows, max(6, count($rows) + 2), ['ref' => '', 'quantity' => 1]);
@endphp

@section('title', $editing ? $pack->name : 'Nouveau pack')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header :title="$editing ? $pack->name : 'Nouveau pack'" subtitle="Choisissez les articles du pack. Pour chacun, imposez une variante ou laissez l’acheteur choisir sa couleur et sa taille.">
        <x-slot:actions>
            <a href="{{ route('admin.store.packs.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Packs</a>
            @if($editing && $pack->is_active)
                <a href="{{ route('store.packs.show', $pack->slug) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Voir côté membre</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

    <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.store.packs.update', $pack) : route('admin.store.packs.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="grid-3-1">
            <div class="store-aside">
                <article class="card">
                    <div class="card-head"><h2>Informations</h2></div>
                    <div class="card-body form-grid">
                        <x-admin.input name="name" label="Nom du pack" :value="$pack->name" required maxlength="160" span />
                        <x-admin.input name="price" label="Prix du pack (TND)" type="number" step="0.01" min="0" :value="$pack->price" required />
                        <x-admin.input name="summary" label="Résumé" :value="$pack->summary" maxlength="300" hint="Une phrase, affichée dans le catalogue." />
                        <x-admin.textarea name="description" label="Description" :value="$pack->description" rows="4" maxlength="10000" />
                    </div>
                </article>

                <article class="card">
                    <div class="card-head">
                        <div>
                            <h2>Articles du pack</h2>
                            <p class="sub">Laissez vide les lignes inutilisées.</p>
                        </div>
                    </div>
                    <div class="card-body field {{ $errors->has('items') ? 'has-error' : '' }}">
                        <div class="pair-row is-head"><span>Article</span><span>Quantité</span></div>
                        @foreach($rows as $index => $row)
                            <div class="pair-row">
                                <select name="items[{{ $index }}][ref]" aria-label="Article">
                                    <option value="">— Aucun —</option>
                                    @foreach($products as $product)
                                        <optgroup label="{{ $product->name }}">
                                            <option value="p{{ $product->id }}" @selected($row['ref'] === 'p'.$product->id)>{{ $product->name }} — au choix de l’acheteur</option>
                                            @foreach($product->variants as $variant)
                                                <option value="v{{ $variant->id }}" @selected($row['ref'] === 'v'.$variant->id)>{{ $product->name }} — {{ $variant->label() }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                <input type="number" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" min="1" max="20" aria-label="Quantité">
                            </div>
                        @endforeach
                        @error('items')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </article>
            </div>

            <div class="store-aside">
                <article class="card">
                    <div class="card-head"><h2>Photo</h2></div>
                    <div class="card-body store-photo">
                        <span class="store-thumb is-lg">
                            @if($pack->imageUrl())<img src="{{ $pack->imageUrl() }}" alt="">@else<x-admin.icon name="layers" :size="30" />@endif
                        </span>
                        <div class="grow field">
                            <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Photo du pack">
                            <p class="hint">JPG, PNG ou WebP, 4 Mo maximum.</p>
                            @error('image')<p class="error">{{ $message }}</p>@enderror
                            @if($pack->imageUrl())
                                <label class="check"><input type="checkbox" name="remove_image" value="1"> Retirer la photo</label>
                            @endif
                        </div>
                    </div>
                </article>

                <article class="card">
                    <div class="card-head"><h2>Visibilité</h2></div>
                    <div class="card-body check-stack">
                        <label class="check check-card">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pack->is_active))>
                            <span>En vente<small>Visible dans la boutique des membres.</small></span>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer' : 'Créer le pack' }}</button>
                    </div>
                </article>
            </div>
        </div>
    </form>

    @if($editing)
        <article class="card">
            <div class="card-body method-actions">
                <div>
                    <strong>Supprimer ce pack</strong>
                    <p class="text-sm text-muted">Les commandes déjà passées gardent leur contenu.</p>
                </div>
                <form method="post" action="{{ route('admin.store.packs.destroy', $pack) }}" onsubmit="return confirm('Supprimer ce pack ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                </form>
            </div>
        </article>
    @endif
@endsection
