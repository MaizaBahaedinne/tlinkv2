@extends('layouts.base', ['title' => $product->name.' | '.$settings['name']])

@php
    $variants = $product->variants;
    $colors = $variants->pluck('color')->filter()->unique('id')->values();
    $sizes = $variants->pluck('size')->filter()->unique('id')->sortBy('sort_order')->values();
    $photos = $product->gallery();
    $payload = $variants->map(fn ($variant) => [
        'id' => $variant->id,
        'color_id' => $variant->color_id,
        'size_id' => $variant->size_id,
        'stock' => (int) $variant->stock,
        'low' => $variant->isLow(),
        'price' => \App\Support\Store\StoreSettings::money($variant->unitPrice()),
    ])->values();
    $guide = $product->sizeGuideTable();
    $careLines = $product->careLines();
    $specs = array_filter([
        'Type de textile' => $product->material,
        'Composition' => $product->composition,
        'Grammage' => $product->weight,
        'Coupe' => $product->fit,
        'Catégorie' => $product->category?->name,
        'Type d’article' => $product->type?->name,
    ]);
    $paragraphs = collect(preg_split('/\R{2,}/', trim((string) $product->details)))->map(fn ($text) => trim($text))->filter();
    $card = 'rounded-3xl border border-slate-200 bg-white p-6 shadow-soft';
@endphp

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-6xl space-y-5 py-6">
        <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="Fil d’Ariane">
            <a href="{{ route('store.index') }}" class="font-semibold text-tlink-blue hover:underline">{{ $settings['name'] }}</a>
            @if($product->category)
                <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                <a href="{{ route('store.index', ['categorie' => $product->category->slug]) }}" class="hover:underline">{{ $product->category->name }}</a>
            @endif
            <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
            <span class="truncate text-slate-700">{{ $product->name }}</span>
        </nav>

        @include('store._flash')

        <section id="buybox" class="grid gap-8 {{ $card }} lg:grid-cols-2" data-variants="{{ $payload->toJson() }}" data-photos="{{ $photos->toJson() }}" data-old-variant="{{ (int) old('variant_id') }}" data-base-price="{{ $product->priceLabel() }}" data-max="{{ \App\Support\Store\StoreCart::MAX_QUANTITY }}">
            {{-- Galerie --}}
            <div class="space-y-3 lg:sticky lg:top-4 lg:self-start">
                <div class="relative aspect-square overflow-hidden rounded-3xl bg-slate-100">
                    @if($photos->isNotEmpty())
                        <img id="gallery-main" src="{{ $photos->first()['url'] }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                        @if($photos->count() > 1)
                            <button type="button" id="gallery-prev" class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-soft hover:bg-white" aria-label="Photo précédente"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                            <button type="button" id="gallery-next" class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-soft hover:bg-white" aria-label="Photo suivante"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                            <span id="gallery-count" class="absolute bottom-3 right-3 rounded-full bg-slate-900/70 px-3 py-1 text-xs font-semibold text-white">1 / {{ $photos->count() }}</span>
                        @endif
                    @else
                        <div class="flex h-full items-center justify-center text-6xl text-slate-300"><i class="fa-solid fa-shirt" aria-hidden="true"></i></div>
                    @endif
                </div>
                @if($photos->count() > 1)
                    <div class="flex gap-2 overflow-x-auto pb-1" role="list" aria-label="Photos de l’article">
                        @foreach($photos as $index => $photo)
                            <button type="button" data-thumb="{{ $index }}" role="listitem" class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border-2 border-transparent opacity-70 transition hover:opacity-100 sm:h-20 sm:w-20" aria-label="Voir la photo {{ $index + 1 }}">
                                <img src="{{ $photo['url'] }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Achat --}}
            <div class="space-y-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $product->category?->name }}@if($product->type) · {{ $product->type->name }}@endif</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold text-slate-900">{{ $product->name }}</h1>

                    <a href="#avis" class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600 hover:text-tlink-blue">
                        @include('store.partials.stars', ['value' => $rating['average']])
                        @if($rating['count'] > 0)
                            <span class="font-semibold">{{ number_format($rating['average'], 1, ',', '') }}</span>
                            <span class="text-slate-500">({{ $rating['count'] }} avis)</span>
                        @else
                            <span class="text-slate-500">Aucun avis pour le moment</span>
                        @endif
                    </a>

                    <p id="price" class="mt-3 text-3xl font-semibold text-tlink-blue">{{ $product->priceLabel() }}</p>
                </div>

                @if($product->summary)<p class="text-sm leading-6 text-slate-600">{{ $product->summary }}</p>@endif

                @if($variants->isEmpty())
                    <p class="rounded-2xl bg-slate-100 p-4 text-sm text-slate-600">Cet article n’est pas disponible pour le moment.</p>
                @else
                    <form method="post" action="{{ route('store.cart.add') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="variant_id" id="variant-id" value="">

                        @if($colors->isNotEmpty())
                            <fieldset>
                                <legend class="flex items-baseline gap-2 text-sm font-semibold text-slate-800">Couleur <span id="color-name" class="font-normal text-slate-500"></span></legend>
                                <div class="mt-3 flex flex-wrap gap-3" role="radiogroup" aria-label="Couleur">
                                    @foreach($colors as $color)
                                        <button type="button" data-color="{{ $color->id }}" role="radio" aria-checked="false" title="{{ $color->name }}" aria-label="{{ $color->name }}" data-name="{{ $color->name }}"
                                            class="relative h-11 w-11 rounded-full border border-slate-300 p-1 outline-offset-2 transition focus-visible:outline-2 focus-visible:outline-tlink-blue [&[aria-checked=true]]:border-tlink-blue [&[aria-checked=true]]:ring-2 [&[aria-checked=true]]:ring-tlink-blue/40 [&[data-out=true]]:opacity-40">
                                            <span class="block h-full w-full rounded-full border border-black/10" style="background: {{ $color->hex ?: '#e2e8f0' }}"></span>
                                        </button>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        @if($sizes->isNotEmpty())
                            <fieldset>
                                <legend class="flex w-full items-baseline justify-between gap-2 text-sm font-semibold text-slate-800">
                                    <span>Taille <span id="size-name" class="font-normal text-slate-500"></span></span>
                                    @if($guide)<a href="#guide-tailles" class="text-xs font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-ruler mr-1" aria-hidden="true"></i>Guide des tailles</a>@endif
                                </legend>
                                <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Taille">
                                    @foreach($sizes as $size)
                                        <button type="button" data-size="{{ $size->id }}" role="radio" aria-checked="false"
                                            class="min-w-12 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-tlink-blue [&[aria-checked=true]]:border-tlink-blue [&[aria-checked=true]]:bg-tlink-blue [&[aria-checked=true]]:text-white [&[data-out=true]]:cursor-not-allowed [&[data-out=true]]:border-dashed [&[data-out=true]]:bg-slate-50 [&[data-out=true]]:text-slate-300 [&[data-out=true]]:line-through">{{ $size->name }}</button>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        <p id="stock-line" class="min-h-5 text-sm font-medium text-slate-500" role="status"></p>

                        <div class="flex flex-wrap items-end gap-3">
                            <label class="block text-sm font-semibold text-slate-800">Quantité
                                <input type="number" id="quantity" name="quantity" min="1" max="{{ \App\Support\Store\StoreCart::MAX_QUANTITY }}" value="{{ old('quantity', 1) }}" class="mt-1 block w-24 rounded-xl border border-slate-300 px-3 py-3 text-sm">
                            </label>
                            <button type="submit" id="add-to-cart" class="inline-flex min-w-56 flex-1 items-center justify-center gap-2 rounded-xl bg-tlink-blue px-6 py-3.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:bg-slate-300" disabled>
                                <i class="fa-solid fa-cart-plus" aria-hidden="true"></i> <span id="add-label">Choisissez une option</span>
                            </button>
                        </div>
                    </form>
                @endif

                @if($product->material || $product->composition)
                    <ul class="grid gap-2 border-t border-slate-100 pt-5 text-sm text-slate-600">
                        @if($product->material)<li><i class="fa-solid fa-shirt mr-2 w-4 text-center text-tlink-blue" aria-hidden="true"></i>{{ $product->material }}</li>@endif
                        @if($product->composition)<li><i class="fa-solid fa-leaf mr-2 w-4 text-center text-tlink-blue" aria-hidden="true"></i>{{ $product->composition }}</li>@endif
                    </ul>
                @endif
            </div>
        </section>

        <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-soft" aria-label="Sections de la fiche">
            @foreach([['description', 'Description'], ['caracteristiques', 'Matière et entretien'], ['guide-tailles', 'Guide des tailles'], ['avis', 'Avis ('.$rating['count'].')']] as [$anchor, $text])
                @continue($anchor === 'guide-tailles' && ! $guide)
                <a href="#{{ $anchor }}" class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-rose-50 hover:text-tlink-blue">{{ $text }}</a>
            @endforeach
        </nav>

        <div class="grid gap-5 lg:grid-cols-5">
            <section id="description" class="scroll-mt-20 {{ $card }} lg:col-span-3">
                <h2 class="font-display text-xl font-semibold text-slate-900">Description</h2>
                @if($product->description)
                    <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $product->description }}</p>
                @endif
                @foreach($paragraphs as $text)
                    <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $text }}</p>
                @endforeach
                @if(! $product->description && $paragraphs->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">La description détaillée de cet article sera bientôt disponible.</p>
                @endif
            </section>

            <section id="caracteristiques" class="scroll-mt-20 {{ $card }} lg:col-span-2">
                <h2 class="font-display text-xl font-semibold text-slate-900">Matière et entretien</h2>
                @if(count($specs))
                    <dl class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach($specs as $label => $value)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="text-right font-medium text-slate-800">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
                @if($careLines)
                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Entretien</h3>
                    <ul class="mt-2 space-y-1.5 text-sm text-slate-700">
                        @foreach($careLines as $line)
                            <li class="flex gap-2"><i class="fa-solid fa-circle-check mt-1 text-xs text-emerald-500" aria-hidden="true"></i>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                @if(! count($specs) && ! $careLines)
                    <p class="mt-3 text-sm text-slate-500">Les informations sur la matière seront bientôt disponibles.</p>
                @endif
            </section>
        </div>

        @if($guide)
            <section id="guide-tailles" class="scroll-mt-20 {{ $card }}">
                <h2 class="font-display text-xl font-semibold text-slate-900">Guide des tailles</h2>
                <p class="mt-1 text-sm text-slate-500">Mesures du produit à plat, en centimètres. Une différence de 1 à 2 cm est normale.</p>
                <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full min-w-96 text-center text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>@foreach($guide['head'] as $cell)<th class="px-4 py-3 {{ $loop->first ? 'text-left' : '' }}">{{ $cell }}</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($guide['rows'] as $row)
                                <tr>@foreach($row as $cell)<td class="px-4 py-3 {{ $loop->first ? 'text-left font-semibold text-slate-900' : 'text-slate-700' }}">{{ $cell }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section id="avis" class="scroll-mt-20 {{ $card }}">
            <h2 class="font-display text-xl font-semibold text-slate-900">Avis des clients</h2>

            <div class="mt-5 grid gap-8 lg:grid-cols-3">
                <div>
                    @if($rating['count'] > 0)
                        <div class="flex items-center gap-4">
                            <p class="font-display text-5xl font-semibold text-slate-900">{{ number_format($rating['average'], 1, ',', '') }}</p>
                            <div>
                                @include('store.partials.stars', ['value' => $rating['average'], 'size' => 'text-lg'])
                                <p class="mt-1 text-sm text-slate-500">{{ $rating['count'] }} avis</p>
                            </div>
                        </div>
                        <ul class="mt-4 space-y-1.5">
                            @foreach($rating['distribution'] as $star => $total)
                                <li class="flex items-center gap-2 text-xs text-slate-600">
                                    <span class="w-4 text-right font-semibold">{{ $star }}</span>
                                    <i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i>
                                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-amber-400" style="width: {{ $rating['count'] > 0 ? round($total / $rating['count'] * 100) : 0 }}%"></span></span>
                                    <span class="w-6 text-right">{{ $total }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500">Cet article n’a pas encore d’avis.</p>
                    @endif

                    <div class="mt-6">
                        @if($canReview)
                            <form method="post" action="{{ route('store.reviews.store', $product) }}" class="space-y-3 rounded-2xl bg-slate-50 p-4" id="review-form">
                                @csrf
                                <h3 class="text-sm font-semibold text-slate-900">{{ $myReview ? 'Modifier mon avis' : 'Donner mon avis' }}</h3>
                                @if($myReview?->status === 'hidden')
                                    <p class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">Votre avis est actuellement masqué par l’équipe.</p>
                                @endif
                                <div class="flex gap-1" role="radiogroup" aria-label="Note">
                                    @for($star = 1; $star <= 5; $star++)
                                        <label class="cursor-pointer text-2xl text-slate-300 has-[:checked]:text-amber-400 [&.is-on]:text-amber-400" data-star="{{ $star }}">
                                            <input type="radio" name="rating" value="{{ $star }}" class="sr-only" @checked((int) old('rating', $myReview?->rating) === $star) required>
                                            <i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only">{{ $star }} sur 5</span>
                                        </label>
                                    @endfor
                                </div>
                                <input type="text" name="title" value="{{ old('title', $myReview?->title) }}" maxlength="120" placeholder="Titre (facultatif)" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <textarea name="body" rows="4" maxlength="2000" placeholder="Qualité, taille, confort…" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('body', $myReview?->body) }}</textarea>
                                <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">Publier mon avis</button>
                            </form>
                        @else
                            <p class="rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-500"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i>Seuls les membres dont la commande contenant cet article a été livrée peuvent donner leur avis.</p>
                        @endif
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-2">
                    @forelse($product->reviews as $review)
                        <article class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                @include('store.partials.stars', ['value' => $review->rating])
                                <p class="text-xs text-slate-500">{{ $review->authorLabel() }} · {{ $review->created_at->translatedFormat('d M Y') }}</p>
                            </div>
                            @if($review->title)<h3 class="mt-2 text-sm font-semibold text-slate-900">{{ $review->title }}</h3>@endif
                            @if($review->body)<p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $review->body }}</p>@endif
                            <p class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-emerald-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Achat vérifié</p>
                        </article>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Soyez le premier à partager votre avis après réception de votre commande.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    const root = document.getElementById('buybox');
    if (!root) return;

    const variants = JSON.parse(root.dataset.variants);
    const photos = JSON.parse(root.dataset.photos);
    const maxQty = parseInt(root.dataset.max, 10);
    const hasColors = root.querySelector('[data-color]') !== null;
    const hasSizes = root.querySelector('[data-size]') !== null;
    const colorBtns = [...root.querySelectorAll('[data-color]')];
    const sizeBtns = [...root.querySelectorAll('[data-size]')];
    const idInput = document.getElementById('variant-id');
    const addBtn = document.getElementById('add-to-cart');
    const addLabel = document.getElementById('add-label');
    const stockLine = document.getElementById('stock-line');
    const qty = document.getElementById('quantity');
    const price = document.getElementById('price');
    const main = document.getElementById('gallery-main');
    const counter = document.getElementById('gallery-count');
    const thumbs = [...root.querySelectorAll('[data-thumb]')];
    const colorName = document.getElementById('color-name');
    const sizeName = document.getElementById('size-name');
    let color = null;
    let size = null;
    let current = 0;

    const find = (c, s) => variants.find(v => v.color_id === c && v.size_id === s);
    const colorInStock = c => variants.some(v => v.color_id === c && v.stock > 0);
    const sizeOk = (c, s) => { const v = find(c, s); return !!v && v.stock > 0; };

    function showPhoto(index) {
        if (!main || !photos.length) return;
        current = (index + photos.length) % photos.length;
        main.src = photos[current].url;
        if (counter) counter.textContent = (current + 1) + ' / ' + photos.length;
        thumbs.forEach((t, i) => {
            t.classList.toggle('border-tlink-blue', i === current);
            t.classList.toggle('border-transparent', i !== current);
            t.classList.toggle('opacity-100', i === current);
            t.classList.toggle('opacity-70', i !== current);
        });
    }

    function render() {
        colorBtns.forEach(b => {
            const id = parseInt(b.dataset.color, 10);
            b.setAttribute('aria-checked', id === color ? 'true' : 'false');
            b.dataset.out = colorInStock(id) ? 'false' : 'true';
        });
        sizeBtns.forEach(b => {
            const id = parseInt(b.dataset.size, 10);
            b.setAttribute('aria-checked', id === size ? 'true' : 'false');
            b.dataset.out = sizeOk(hasColors ? color : null, id) ? 'false' : 'true';
        });
        if (colorName) colorName.textContent = color === null ? '' : '· ' + colorBtns.find(b => parseInt(b.dataset.color, 10) === color).dataset.name;
        if (sizeName) sizeName.textContent = size === null ? '' : '· ' + sizeBtns.find(b => parseInt(b.dataset.size, 10) === size).textContent.trim();

        const ready = (!hasColors || color !== null) && (!hasSizes || size !== null);
        const v = ready ? find(hasColors ? color : null, hasSizes ? size : null) : null;
        idInput.value = v ? v.id : '';

        if (!v) {
            addBtn.disabled = true;
            stockLine.textContent = ready ? 'Cette combinaison n’est pas disponible.' : '';
            addLabel.textContent = ready ? 'Indisponible' : (hasColors && color === null ? 'Choisissez une couleur' : 'Choisissez une taille');
            return;
        }

        price.textContent = v.price;
        if (v.stock < 1) {
            addBtn.disabled = true;
            addLabel.textContent = 'Épuisé';
            stockLine.className = 'min-h-5 text-sm font-medium text-rose-600';
            stockLine.textContent = 'Épuisé dans cette option.';
        } else {
            addBtn.disabled = false;
            addLabel.textContent = 'Ajouter au panier';
            stockLine.className = 'min-h-5 text-sm font-medium ' + (v.low ? 'text-amber-600' : 'text-emerald-600');
            stockLine.textContent = v.low ? 'Plus que ' + v.stock + ' en stock.' : 'En stock.';
            qty.max = Math.min(v.stock, maxQty);
            if (parseInt(qty.value, 10) > parseInt(qty.max, 10)) qty.value = qty.max;
        }
    }

    function pickColor(id) {
        color = id;
        if (hasSizes && (size === null || !sizeOk(color, size))) {
            const first = sizeBtns.map(b => parseInt(b.dataset.size, 10)).find(s => sizeOk(color, s));
            size = first === undefined ? null : first;
        }
        const photo = photos.findIndex(p => p.color_id === id);
        if (photo >= 0) showPhoto(photo);
        render();
    }

    colorBtns.forEach(b => b.addEventListener('click', () => pickColor(parseInt(b.dataset.color, 10))));
    sizeBtns.forEach(b => b.addEventListener('click', () => {
        const id = parseInt(b.dataset.size, 10);
        if (hasColors && color !== null && !sizeOk(color, id) && !find(color, id)) return;
        size = id;
        render();
    }));
    thumbs.forEach((t, i) => t.addEventListener('click', () => showPhoto(i)));
    document.getElementById('gallery-prev')?.addEventListener('click', () => showPhoto(current - 1));
    document.getElementById('gallery-next')?.addEventListener('click', () => showPhoto(current + 1));

    // Sélection initiale : l'option déjà choisie (retour d'erreur), sinon la première en stock.
    const wanted = variants.find(v => v.id === parseInt(root.dataset.oldVariant, 10)) || variants.find(v => v.stock > 0) || variants[0];
    if (wanted) {
        color = hasColors ? wanted.color_id : null;
        size = hasSizes ? wanted.size_id : null;
    }
    showPhoto(0);
    if (hasColors && color !== null) {
        const photo = photos.findIndex(p => p.color_id === color);
        if (photo >= 0) showPhoto(photo);
    }
    render();

    // Étoiles de l'avis : colore toutes les étoiles jusqu'à celle choisie.
    const stars = [...document.querySelectorAll('#review-form [data-star]')];
    const paint = () => {
        const chosen = stars.find(s => s.querySelector('input').checked);
        const value = chosen ? parseInt(chosen.dataset.star, 10) : 0;
        stars.forEach(s => s.classList.toggle('is-on', parseInt(s.dataset.star, 10) <= value));
    };
    stars.forEach(s => s.querySelector('input').addEventListener('change', paint));
    paint();
})();
</script>
@endsection
