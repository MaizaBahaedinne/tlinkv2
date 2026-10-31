@extends('admin.layouts.app')

@section('title', 'Retours boutique')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Retours" subtitle="Demandes de retour des membres : acceptation, réception des articles, remboursement ou échange." />

    <article class="card card-flush">
        <div class="tabs">
            <a href="{{ route('admin.store.returns.index') }}" class="tab {{ $status === '' ? 'is-active' : '' }}">Tous</a>
            @foreach(\App\Models\StoreReturn::STATUSES as $code => $label)
                <a href="{{ route('admin.store.returns.index', ['status' => $code]) }}" class="tab {{ $status === $code ? 'is-active' : '' }}">{{ $label }}@if($code === 'requested' && $requested > 0) <span class="n">{{ $requested }}</span>@endif</a>
            @endforeach
        </div>

        @if($returns->count())
            <div class="list">
                @foreach($returns as $return)
                    <div class="list-item">
                        <div class="grow">
                            <a href="{{ route('admin.store.returns.show', $return) }}"><strong style="display:inline">{{ $return->reference }}</strong></a>
                            <x-admin.badge :status="$return->status === 'completed' ? 'approved' : $return->status" :label="$return->statusLabel()" />
                            <div class="text-sm text-muted">Commande {{ $return->order?->reference }} · {{ $return->user?->name ?? 'compte supprimé' }} · {{ $return->resolutionLabel() }} · {{ $return->reason }} · {{ $return->created_at->translatedFormat('d M Y') }}</div>
                        </div>
                        <a href="{{ route('admin.store.returns.show', $return) }}" class="btn btn-secondary btn-sm">Ouvrir</a>
                    </div>
                @endforeach
            </div>
            {{ $returns->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="inbox" title="Aucun retour" text="Les demandes de retour apparaissent ici." />
        @endif
    </article>
@endsection
