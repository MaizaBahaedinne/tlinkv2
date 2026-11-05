@extends('showcase.layout')

@section('showcase')
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-[#6e0019] via-[#a30027] to-[#d00031] text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-[#f5a623]/25 blur-3xl"></div>
        </div>
        <div class="mx-auto max-w-6xl px-4 py-16 md:py-24">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider">
                <span class="h-1.5 w-1.5 rounded-full bg-[#f5a623]"></span> Fondation Tunivisions
            </span>
            <h1 class="mt-5 max-w-3xl font-display text-4xl font-semibold leading-tight md:text-5xl">{{ $settings['name'] }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-white/85">{{ $settings['tagline'] }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @if($open)
                    <a href="{{ route('store.index') }}" class="rounded-xl bg-[#f5a623] px-6 py-3 font-bold text-slate-900 hover:brightness-110">Découvrir la boutique</a>
                @else
                    <span class="rounded-xl border border-white/40 px-6 py-3 font-bold">Ouverture prochainement</span>
                @endif
                @guest
                    <a href="{{ $registerUrl }}" class="rounded-xl border border-white/40 px-6 py-3 font-bold hover:bg-white/10">Créer mon compte</a>
                @endguest
            </div>
        </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <div class="grid gap-5 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Articles officiels</h2>
                <p class="mt-2 text-sm text-slate-600">Vêtements, accessoires et packs aux couleurs de la Fondation Tunivisions et de ses clubs.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Réservé aux membres</h2>
                <p class="mt-2 text-sm text-slate-600">Connectez-vous avec votre compte T.Link pour commander et suivre vos commandes.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Retours simplifiés</h2>
                <p class="mt-2 text-sm text-slate-600">Un retour peut être demandé jusqu’à {{ $settings['return_days'] }} jours après la livraison.</p>
            </article>
        </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-semibold text-slate-900">Aperçu de la boutique</h2>
        @if($products->isEmpty())
            <p class="mt-4 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                {{ $open ? 'Les premiers articles arrivent bientôt.' : 'La boutique ouvrira prochainement. Revenez bientôt.' }}
            </p>
        @else
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach($products as $product)
                    <a href="{{ route('store.products.show', $product->slug) }}" class="block overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft transition hover:-translate-y-0.5">
                        @if($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="h-44 w-full object-cover" loading="lazy">
                        @else
                            <div class="grid h-44 place-items-center bg-gradient-to-br from-slate-100 to-slate-200 text-3xl text-slate-400"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i></div>
                        @endif
                        <div class="space-y-1 p-4">
                            @if($product->category)<p class="text-xs font-semibold uppercase text-slate-500">{{ $product->category->name }}</p>@endif
                            <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                            <p class="text-sm font-semibold text-tlink-blue">{{ $product->priceLabel() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <p class="mt-6 text-center"><a href="{{ route('store.index') }}" class="rounded-xl bg-[#a30027] px-6 py-3 text-sm font-bold text-white hover:bg-[#8a0021]">Voir toute la boutique</a></p>
        @endif
    </section>

    @if($settings['contact_email'] !== '' || $settings['contact_phone'] !== '')
        <section class="mx-auto mt-12 max-w-6xl px-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Une question sur la boutique ?</h2>
                <p class="mt-2">
                    @if($settings['contact_email'] !== '')<a href="mailto:{{ $settings['contact_email'] }}" class="font-semibold text-tlink-blue">{{ $settings['contact_email'] }}</a>@endif
                    @if($settings['contact_email'] !== '' && $settings['contact_phone'] !== '') · @endif
                    @if($settings['contact_phone'] !== '')<span>{{ $settings['contact_phone'] }}</span>@endif
                </p>
            </div>
        </section>
    @endif
@endsection
