{{-- Sous-navigation de Tunivisions Stores : une entrée par page, filtrée selon les droits du poste. --}}
@php
    $storeContext = \App\Support\AdminContext::of(request());
    $storePerms = ['entity.manage'];
    $storeTabs = [
        ['Vue d’ensemble', 'admin.store.index', 'admin.store.index', 'store.orders', null],
        ['Commandes', 'admin.store.orders.index', 'admin.store.orders.*', 'store.orders', \App\Models\StoreOrder::query()->where('status', 'pending')->count()],
        ['Retours', 'admin.store.returns.index', 'admin.store.returns.*', 'store.returns', \App\Models\StoreReturn::query()->where('status', 'requested')->count()],
        ['Articles', 'admin.store.products.index', 'admin.store.products.*', 'store.catalog', null],
        ['Packs', 'admin.store.packs.index', 'admin.store.packs.*', 'store.catalog', null],
        ['Stock', 'admin.store.stock.index', 'admin.store.stock.*', 'store.stock', null],
        ['Avis', 'admin.store.reviews.index', 'admin.store.reviews.*', 'store.catalog', null],
        ['Catégories et couleurs', 'admin.store.refs.index', 'admin.store.refs.*', 'store.catalog', null],
        ['Réglages', 'admin.store.settings.edit', 'admin.store.settings.*', 'store.settings', null],
    ];
@endphp
<nav class="tabs store-tabs" aria-label="Tunivisions Stores">
    @foreach($storeTabs as [$tabLabel, $tabRoute, $tabMatch, $tabModule, $tabCount])
        @if($storeContext->allowsModule($storePerms, $tabModule))
            <a href="{{ route($tabRoute) }}" class="tab {{ request()->routeIs($tabMatch) ? 'is-active' : '' }}">{{ $tabLabel }}@if($tabCount) <span class="n">{{ $tabCount }}</span>@endif</a>
        @endif
    @endforeach
</nav>
