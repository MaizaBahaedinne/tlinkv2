@extends('admin.layouts.app')

@section('title', 'Packs')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Packs" subtitle="Plusieurs articles vendus ensemble à un prix fixe. Le stock des articles est décompté à chaque vente.">
        <x-slot:actions>
            <a href="{{ route('admin.store.packs.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau pack</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        @if($packs->count())
            <div class="list">
                @foreach($packs as $pack)
                    <div class="list-item">
                        <div class="grow">
                            <a href="{{ route('admin.store.packs.edit', $pack) }}"><strong style="display:inline">{{ $pack->name }}</strong></a>
                            <x-admin.badge :status="$pack->is_active ? 'active' : 'inactive'" :label="$pack->is_active ? 'En vente' : 'Masqué'" />
                            <div class="text-sm text-muted">
                                {{ $pack->priceLabel() }} · {{ $pack->items->count() }} article(s) :
                                {{ $pack->items->map(fn ($item) => $item->quantity.'× '.$item->product?->name)->implode(', ') }}
                                · {{ $pack->availableUnits() }} pack(s) disponible(s)
                            </div>
                        </div>
                        <a href="{{ route('admin.store.packs.edit', $pack) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                    </div>
                @endforeach
            </div>
            {{ $packs->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="layers" title="Aucun pack" text="Composez un pack à partir de vos articles (par exemple un T-shirt, une casquette et un mug)." />
        @endif
    </article>
@endsection
