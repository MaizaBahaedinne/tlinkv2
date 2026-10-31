@extends('admin.layouts.app')

@section('title', 'Tunivisions Stores')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Tunivisions Stores" subtitle="La boutique de la Fondation : commandes à traiter, stock à surveiller et état de la mise en route.">
        <x-slot:actions>
            @if($enabled)
                <a href="{{ route('store.index') }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="external" :size="16" /> Voir la boutique</a>
            @endif
            <a href="{{ route('admin.store.products.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvel article</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

    <section class="stats">
        <x-admin.stat label="Commandes à confirmer" :value="$pending" icon="shopping-bag" tone="amber" :hint="$toShip.' à préparer ou expédier'" :href="route('admin.store.orders.index', ['status' => 'pending'])" />
        <x-admin.stat label="Ventes (30 jours)" :value="\App\Support\Store\StoreSettings::money($revenue)" icon="activity" tone="green" hint="Commandes non annulées" />
        <x-admin.stat label="Stock à réapprovisionner" :value="$lowCount" icon="package" :tone="$lowCount > 0 ? 'red' : 'blue'" hint="Variantes sous leur seuil" :href="route('admin.store.stock.index', ['filter' => 'low'])" />
        <x-admin.stat label="Retours à traiter" :value="$returnsOpen" icon="inbox" tone="violet" hint="Demandes en attente" :href="route('admin.store.returns.index', ['status' => 'requested'])" />
    </section>

    @unless($ready)
        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>Mise en route</h2>
                    <p class="sub">Quelques étapes avant que les membres puissent commander.</p>
                </div>
                <span class="badge badge-{{ $enabled ? 'success' : 'warning' }}">{{ $enabled ? 'Boutique ouverte' : 'Boutique fermée' }}</span>
            </div>
            <div class="checklist">
                @foreach($steps as $step)
                    <div class="checklist-item {{ $step['done'] ? 'is-done' : '' }}">
                        <span class="mark"><x-admin.icon :name="$step['done'] ? 'check' : 'circle'" :size="16" /></span>
                        <div class="grow">
                            <strong>{{ $step['title'] }}</strong>
                            <span class="text-sm text-muted">{{ $step['text'] }}</span>
                        </div>
                        @unless($step['done'])
                            <a href="{{ route($step['route']) }}" class="btn btn-secondary btn-sm">{{ $step['label'] }}</a>
                        @endunless
                    </div>
                @endforeach
            </div>
        </article>
    @endunless

    <div class="grid-2">
        <article class="card card-flush">
            <div class="card-head">
                <h2>Dernières commandes</h2>
                <a href="{{ route('admin.store.orders.index') }}" class="btn btn-ghost btn-sm">Tout voir</a>
            </div>
            @if($recentOrders->count())
                <div class="list">
                    @foreach($recentOrders as $order)
                        <a href="{{ route('admin.store.orders.show', $order) }}" class="list-item">
                            <div class="grow">
                                <strong>{{ $order->reference }}</strong>
                                <span class="text-sm text-muted">{{ $order->ship_name }} · {{ $order->created_at->translatedFormat('d M Y') }}</span>
                            </div>
                            <span class="badge badge-{{ $order->statusTone() }}">{{ $order->statusLabel() }}</span>
                            <strong class="nowrap">{{ $order->totalLabel() }}</strong>
                        </a>
                    @endforeach
                </div>
            @else
                <x-admin.empty icon="shopping-bag" title="Aucune commande" text="Les commandes des membres apparaîtront ici." />
            @endif
        </article>

        <article class="card card-flush">
            <div class="card-head">
                <h2>Stock à surveiller</h2>
                <a href="{{ route('admin.store.stock.index') }}" class="btn btn-ghost btn-sm">Gérer le stock</a>
            </div>
            @if($lowVariants->count())
                <div class="list">
                    @foreach($lowVariants as $variant)
                        <a href="{{ route('admin.store.products.edit', $variant->product_id) }}" class="list-item">
                            <div class="grow">
                                <strong>{{ $variant->product?->name }}</strong>
                                <span class="text-sm text-muted">{{ $variant->label() }}</span>
                            </div>
                            <span class="stock-pill {{ $variant->stock === 0 ? 'is-out' : 'is-low' }}">{{ $variant->stock === 0 ? 'Épuisé' : $variant->stock.' en stock' }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <x-admin.empty icon="check" title="Stock sain" text="Aucune variante n’est sous son seuil d’alerte." />
            @endif
        </article>
    </div>
@endsection
