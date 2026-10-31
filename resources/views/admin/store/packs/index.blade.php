@extends('admin.layouts.app')

@section('title', 'Packs')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Packs" subtitle="Plusieurs articles vendus ensemble à un prix fixe. Le stock de chaque article est décompté à chaque vente.">
        <x-slot:actions>
            <a href="{{ route('admin.store.packs.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau pack</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

    <article class="card card-flush">
        @if($packs->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Pack</th>
                            <th>Contenu</th>
                            <th class="num">Prix</th>
                            <th>Disponibilité</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($packs as $pack)
                            @php($units = $pack->availableUnits())
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <span class="store-thumb">
                                            @if($pack->imageUrl())<img src="{{ $pack->imageUrl() }}" alt="">@else<x-admin.icon name="layers" :size="20" />@endif
                                        </span>
                                        <div>
                                            <a href="{{ route('admin.store.packs.edit', $pack) }}" class="cell-main link-strong">{{ $pack->name }}</a>
                                            <div><span class="badge badge-{{ $pack->is_active ? 'success' : 'neutral' }}">{{ $pack->is_active ? 'En vente' : 'Masqué' }}</span></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="truncate-2 text-sm">{{ $pack->items->map(fn ($item) => $item->quantity.' × '.$item->product?->name)->implode(' · ') }}</div>
                                    <div class="cell-sub">{{ $pack->items->count() }} ligne(s)</div>
                                </td>
                                <td class="num cell-main">{{ $pack->priceLabel() }}</td>
                                <td><span class="stock-pill {{ $units === 0 ? 'is-out' : '' }}">{{ $units }} pack(s)</span></td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.store.packs.edit', $pack) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $packs->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="layers" title="Aucun pack" text="Composez un pack à partir de vos articles (par exemple un T-shirt, une casquette et un mug).">
                <a href="{{ route('admin.store.packs.create') }}" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Nouveau pack</a>
            </x-admin.empty>
        @endif
    </article>
@endsection
