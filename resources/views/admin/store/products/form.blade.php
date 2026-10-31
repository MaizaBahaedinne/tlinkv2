@extends('admin.layouts.app')

@php
    $editing = $product->exists;
@endphp

@section('title', $editing ? $product->name : 'Nouvel article')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header :title="$editing ? $product->name : 'Nouvel article'" subtitle="Fiche de l’article. Les couleurs et tailles se déclinent en variantes, chacune avec son propre stock.">
        <x-slot:actions>
            <a href="{{ route('admin.store.products.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Articles</a>
            @if($editing && $product->is_active)
                <a href="{{ route('store.products.show', $product->slug) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Voir côté membre</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

    @unless($editing)
        <div class="callout"><x-admin.icon name="package" :size="18" /> Après avoir créé l’article, vous pourrez ajouter ses variantes (couleurs et tailles) et leur stock.</div>
    @endunless

    <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.store.products.update', $product) : route('admin.store.products.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="grid-3-1">
            <div class="store-aside">
            <article class="card">
                <div class="card-head"><h2>Informations</h2></div>
                <div class="card-body form-grid">
                    <x-admin.input name="name" label="Nom de l’article" :value="$product->name" required maxlength="160" span />
                    <x-admin.select name="category_id" label="Catégorie" :options="$categories" :value="$product->category_id" placeholder="Sans catégorie" />
                    <x-admin.select name="type_id" label="Type d’article" :options="$types" :value="$product->type_id" placeholder="Sans type" />
                    <x-admin.input name="price" label="Prix de base (TND)" type="number" step="0.01" min="0" :value="$product->price" required hint="Chaque variante peut avoir son propre prix." />
                    <x-admin.input name="summary" label="Résumé" :value="$product->summary" maxlength="300" hint="Une phrase, affichée dans le catalogue." />
                    <x-admin.textarea name="description" label="Description" :value="$product->description" rows="6" maxlength="10000" />
                </div>
            </article>

            <article class="card">
                <div class="card-head"><h2>Fiche détaillée</h2></div>
                <div class="card-body form-grid">
                    <x-admin.input name="material" label="Type de textile" :value="$product->material" maxlength="120" placeholder="Ex. Molleton gratté 280 g/m²" />
                    <x-admin.input name="composition" label="Composition" :value="$product->composition" maxlength="200" placeholder="Ex. 80 % coton, 20 % polyester" />
                    <x-admin.input name="weight" label="Grammage" :value="$product->weight" maxlength="60" placeholder="Ex. 280 g/m²" />
                    <x-admin.input name="fit" label="Coupe" :value="$product->fit" maxlength="80" placeholder="Ex. Coupe droite, unisexe" />
                    <x-admin.textarea name="details" label="Description détaillée" :value="$product->details" rows="6" maxlength="10000" hint="Points forts, finitions, impression, histoire de l’article… Un paragraphe par ligne vide." />
                    <x-admin.textarea name="care" label="Entretien" :value="$product->care" rows="4" maxlength="2000" hint="Une consigne par ligne (ex. Lavage à 30 °C)." />
                    <x-admin.textarea name="size_guide" label="Guide des tailles" :value="$product->size_guide" rows="6" maxlength="4000" hint="Une ligne par taille, colonnes séparées par « | ». La première ligne contient les titres, en cm." placeholder="Taille | Poitrine | Longueur&#10;S | 52 | 68&#10;M | 55 | 70&#10;L | 58 | 72" />
                </div>
            </article>
            </div>

            <div class="store-aside">
                <article class="card">
                    <div class="card-head"><h2>Photo principale</h2></div>
                    <div class="card-body store-photo">
                        <span class="store-thumb is-lg">
                            @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="">@else<x-admin.icon name="package" :size="30" />@endif
                        </span>
                        <div class="grow field">
                            <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" aria-label="Photo de l’article">
                            <p class="hint">JPG, PNG ou WebP, 4 Mo maximum.</p>
                            @error('image')<p class="error">{{ $message }}</p>@enderror
                            @if($product->imageUrl())
                                <label class="check"><input type="checkbox" name="remove_image" value="1"> Retirer la photo</label>
                            @endif
                        </div>
                    </div>
                </article>

                <article class="card">
                    <div class="card-head"><h2>Visibilité</h2></div>
                    <div class="card-body check-stack">
                        <label class="check check-card">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                            <span>En vente<small>Visible dans la boutique des membres.</small></span>
                        </label>
                        <label class="check check-card">
                            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                            <span>À la une<small>Affiché en premier dans le catalogue.</small></span>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ $editing ? 'Enregistrer' : 'Créer l’article' }}</button>
                    </div>
                </article>
            </div>
        </div>
    </form>

    @if($editing)
        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>Galerie photos</h2>
                    <p class="sub">{{ $product->images->count() }} photo(s) en plus de la photo principale. Associez une photo à une couleur pour qu’elle s’affiche quand le membre choisit cette couleur.</p>
                </div>
            </div>

            @if($product->images->count())
                <div class="card-body store-gallery">
                    @foreach($product->images as $photo)
                        <div class="store-gallery-item">
                            <img src="{{ $photo->url() }}" alt="">
                            <form method="post" action="{{ route('admin.store.products.images.update', $photo) }}">
                                @csrf
                                @method('PUT')
                                <select name="color_id" aria-label="Couleur de la photo">
                                    <option value="">Toutes couleurs</option>
                                    @foreach($colors as $color)
                                        <option value="{{ $color->id }}" @selected($photo->color_id === $color->id)>{{ $color->name }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="sort_order" value="{{ $photo->sort_order }}" min="0" aria-label="Ordre" title="Ordre d’affichage">
                                <button type="submit" class="btn btn-secondary btn-sm">OK</button>
                            </form>
                            <form method="post" action="{{ route('admin.store.products.images.destroy', $photo) }}" onsubmit="return confirm('Supprimer cette photo ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="post" enctype="multipart/form-data" action="{{ route('admin.store.products.images.store', $product) }}" class="card-body form-grid">
                @csrf
                <div class="field {{ $errors->has('photos') || $errors->has('photos.*') ? 'has-error' : '' }}">
                    <label for="f-photos">Ajouter des photos</label>
                    <input id="f-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <p class="hint">Jusqu’à 10 photos à la fois, 4 Mo maximum chacune.</p>
                    @error('photos')<p class="error">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="f-photo-color">Couleur concernée</label>
                    <select id="f-photo-color" name="color_id">
                        <option value="">Toutes couleurs</option>
                        @foreach($colors as $color)
                            <option value="{{ $color->id }}">{{ $color->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Envoyer les photos</button></div>
            </form>
        </article>

        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>Variantes et stock</h2>
                    <p class="sub">{{ $product->variants->count() }} variante(s) · {{ $product->variants->sum('stock') }} unité(s) en stock</p>
                </div>
                <a href="{{ route('admin.store.stock.index', ['q' => $product->name]) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="activity" :size="15" /> Ajuster le stock</a>
            </div>

            @if($product->variants->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Variante</th>
                                <th>Stock</th>
                                <th>Référence (SKU)</th>
                                <th class="w-md">Prix propre</th>
                                <th class="w-sm">Seuil</th>
                                <th>Active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->variants->sortBy('id') as $variant)
                                <tr>
                                    <td>
                                        <form id="vu-{{ $variant->id }}" method="post" action="{{ route('admin.store.products.variants.update', $variant) }}">@csrf @method('PUT')</form>
                                        <form id="vd-{{ $variant->id }}" method="post" action="{{ route('admin.store.products.variants.destroy', $variant) }}" onsubmit="return confirm('Supprimer cette variante ?')">@csrf @method('DELETE')</form>
                                        <span class="cell-main">@if($variant->color)<span class="swatch" style="background:{{ $variant->color->hex }}"></span> @endif{{ $variant->label() }}</span>
                                    </td>
                                    <td><span class="stock-pill {{ ! $variant->is_active ? 'is-off' : ($variant->stock === 0 ? 'is-out' : ($variant->isLow() ? 'is-low' : '')) }}">{{ $variant->stock }}</span></td>
                                    <td><input form="vu-{{ $variant->id }}" type="text" name="sku" value="{{ $variant->sku }}" maxlength="60" aria-label="Référence"></td>
                                    <td><input form="vu-{{ $variant->id }}" type="number" name="price" value="{{ $variant->price }}" step="0.01" min="0" placeholder="{{ \App\Support\Store\StoreSettings::money($variant->unitPrice()) }}" aria-label="Prix de la variante"></td>
                                    <td><input form="vu-{{ $variant->id }}" type="number" name="low_stock_threshold" value="{{ $variant->low_stock_threshold }}" min="0" aria-label="Seuil d’alerte"></td>
                                    <td><label class="check"><input form="vu-{{ $variant->id }}" type="checkbox" name="is_active" value="1" @checked($variant->is_active)></label></td>
                                    <td class="cell-actions">
                                        <button form="vu-{{ $variant->id }}" type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                        <button form="vd-{{ $variant->id }}" type="submit" class="btn btn-ghost btn-sm btn-icon" aria-label="Supprimer la variante"><x-admin.icon name="trash" :size="15" /></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="card-note">Le stock se modifie depuis la page Stock : chaque entrée, sortie ou correction y est tracée.</p>
            @endif

            <form method="post" action="{{ route('admin.store.products.variants.store', $product) }}" class="card-body form-grid">
                @csrf
                <div class="span-2">
                    <h3 style="font-size:15px">Ajouter des variantes</h3>
                    <p class="hint">Cochez des couleurs et/ou des tailles : toutes les combinaisons manquantes sont créées. Sans rien cocher, une variante « Unique » est ajoutée.</p>
                </div>
                <div class="field">
                    <label>Couleurs</label>
                    <div class="checks">
                        @forelse($colors as $color)
                            <label class="check"><input type="checkbox" name="colors[]" value="{{ $color->id }}"><span class="swatch" style="background:{{ $color->hex }}"></span> {{ $color->name }}</label>
                        @empty
                            <p class="hint">Aucune couleur : ajoutez-en dans <a class="link-strong" href="{{ route('admin.store.refs.index') }}">Catégories et couleurs</a>.</p>
                        @endforelse
                    </div>
                </div>
                <div class="field">
                    <label>Tailles</label>
                    <div class="checks">
                        @forelse($sizes as $size)
                            <label class="check"><input type="checkbox" name="sizes[]" value="{{ $size->id }}"> {{ $size->name }}</label>
                        @empty
                            <p class="hint">Aucune taille : ajoutez-en dans <a class="link-strong" href="{{ route('admin.store.refs.index') }}">Catégories et couleurs</a>.</p>
                        @endforelse
                    </div>
                </div>
                <x-admin.input name="stock" label="Stock initial (par variante)" type="number" min="0" value="0" />
                <x-admin.input name="price" label="Prix propre (facultatif)" type="number" step="0.01" min="0" hint="Laissez vide pour utiliser le prix de base." />
                <x-admin.input name="low_stock_threshold" label="Seuil d’alerte de stock" type="number" min="0" value="3" />
                <div class="span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter les variantes</button></div>
            </form>
        </article>

        <article class="card">
            <div class="card-body method-actions">
                <div>
                    <strong>Supprimer cet article</strong>
                    <p class="text-sm text-muted">Définitif, avec toutes ses variantes. Impossible s’il a déjà été commandé : masquez-le plutôt.</p>
                </div>
                <form method="post" action="{{ route('admin.store.products.destroy', $product) }}" onsubmit="return confirm('Supprimer cet article et ses variantes ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                </form>
            </div>
        </article>
    @endif
@endsection
