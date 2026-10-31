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
    <x-admin.page-header :title="$editing ? $pack->name : 'Nouveau pack'" subtitle="Choisissez les articles du pack. Pour un article, vous pouvez imposer une variante ou laisser l’acheteur choisir sa couleur et sa taille.">
        <x-slot:actions>
            <a href="{{ route('admin.store.packs.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            @if($editing && $pack->is_active)
                <a href="{{ route('store.packs.show', $pack->slug) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Voir côté membre</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card">
        <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.store.packs.update', $pack) : route('admin.store.packs.store') }}" class="form-grid">
            @csrf
            @if($editing) @method('PUT') @endif

            <x-admin.input name="name" label="Nom du pack" :value="$pack->name" required maxlength="160" span />
            <x-admin.input name="price" label="Prix du pack (TND)" type="number" step="0.01" min="0" :value="$pack->price" required />
            <x-admin.input name="summary" label="Résumé (une phrase)" :value="$pack->summary" maxlength="300" />
            <x-admin.textarea name="description" label="Description" :value="$pack->description" rows="4" maxlength="10000" />

            <div class="field span-2">
                <label for="f-image">Photo</label>
                @if($pack->imageUrl())
                    <img src="{{ $pack->imageUrl() }}" alt="" width="96" height="96" style="border-radius:10px;object-fit:cover;margin-bottom:8px">
                    <label style="font-weight:400"><input type="checkbox" name="remove_image" value="1"> Retirer la photo</label>
                @endif
                <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                @error('image')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field span-2 {{ $errors->has('items') ? 'has-error' : '' }}">
                <label>Articles du pack</label>
                @foreach($rows as $index => $row)
                    <div class="inline-form" style="margin-bottom:6px">
                        <select name="items[{{ $index }}][ref]" aria-label="Article" style="min-width:280px">
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
                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" min="1" max="20" aria-label="Quantité" style="width:80px">
                    </div>
                @endforeach
                @error('items')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="field span-2">
                <label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $pack->is_active))> En vente (visible dans la boutique)</label>
            </div>

            <div class="span-2"><button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer' : 'Créer le pack' }}</button></div>
        </form>

        @if($editing)
            <form method="post" action="{{ route('admin.store.packs.destroy', $pack) }}" onsubmit="return confirm('Supprimer ce pack ?')" style="margin-top:12px">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer le pack</button>
            </form>
        @endif
    </article>
@endsection
